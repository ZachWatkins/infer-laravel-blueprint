<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Inferrers\BlueprintColumnTypeInferrer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintParserInterface;
use ZachWatkins\InferLaravelBlueprint\Support\HttpUrl;

final class HttpParser implements BlueprintParserInterface
{
    private readonly ?BlueprintParserInterface $parser;

    /**
     * @param  list<array{string, string}>  $headers
     * @param  BlueprintParserInterface|null  $parser  File parser used for non-JSON response formats.
     * @param  string  $sourceExtension  Extension used for the temporary response file.
     * @param  string  $accept  Default HTTP Accept media type.
     * @param  int  $maxResponseBytes  Maximum response body size in bytes.
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly BlueprintColumnTypeInferrerInterface $inferrer = new BlueprintColumnTypeInferrer,
        ?BlueprintParserInterface $parser = null,
        private readonly string $sourceExtension = 'json',
        private readonly string $accept = 'application/json',
        private readonly array $headers = [],
        private readonly int $maxResponseBytes = 67108864,
    ) {
        $this->parser = $parser;
    }

    public function parse(string $source, string $databaseType = 'sqlite'): BlueprintColumnCollectionInterface
    {
        HttpUrl::assertHasNoUserInfo($source);

        $request = (new Psr17Factory)
            ->createRequest('GET', $source)
            ->withHeader('Accept', $this->accept);

        $customAcceptHeader = false;
        foreach ($this->headers as [$name, $value]) {
            if (\strcasecmp($name, 'Accept') === 0) {
                $request = $customAcceptHeader
                    ? $request->withAddedHeader($name, $value)
                    : $request->withHeader($name, $value);
                $customAcceptHeader = true;

                continue;
            }

            $request = $request->withAddedHeader($name, $value);
        }

        $headResponse = $this->client->sendRequest($request->withMethod('HEAD'));
        if ($headResponse->getStatusCode() >= 200 && $headResponse->getStatusCode() < 300) {
            $this->assertContentLengthWithinLimit($headResponse->getHeaderLine('Content-Length'));
        }

        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(\sprintf('HTTP request failed with status code %d.', $response->getStatusCode()));
        }

        $responseBody = $response->getBody();
        $responseSize = $responseBody->getSize();
        if ($responseSize !== null && $responseSize > $this->maxResponseBytes) {
            throw HttpResponseSizeLimitException::forSizes((string) $responseSize, $this->maxResponseBytes);
        }
        if ($responseBody->isSeekable()) {
            $responseBody->rewind();
        }
        $content = $this->readResponseBody($responseBody);

        if ($this->parser === null) {
            $body = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            return $this->inferrer->infer(\is_array($body) ? $this->extractRecords($body) ?? [] : []);
        }

        $temporaryFile = \tempnam(\sys_get_temp_dir(), 'infer-schema-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Unable to create a temporary file for the HTTP response.');
        }

        $parserPath = $temporaryFile.'.'.$this->sourceExtension;
        if (! \rename($temporaryFile, $parserPath)) {
            \unlink($temporaryFile);

            throw new \RuntimeException('Unable to prepare a temporary file for the HTTP response.');
        }

        try {
            if (\file_put_contents($parserPath, $content) === false) {
                throw new \RuntimeException('Unable to write the HTTP response to a temporary file.');
            }

            return $this->parser->parse($parserPath);
        } finally {
            if (\file_exists($parserPath)) {
                \unlink($parserPath);
            }
        }
    }

    private function assertContentLengthWithinLimit(string $contentLength): void
    {
        $contentLength = \trim($contentLength);
        if (\preg_match('/^\d+$/D', $contentLength) !== 1) {
            return;
        }

        $normalizedLength = \ltrim($contentLength, '0');
        $normalizedLength = $normalizedLength === '' ? '0' : $normalizedLength;
        $limit = (string) $this->maxResponseBytes;

        if (\strlen($normalizedLength) > \strlen($limit)
            || (\strlen($normalizedLength) === \strlen($limit) && \strcmp($normalizedLength, $limit) > 0)) {
            throw HttpResponseSizeLimitException::forSizes($normalizedLength, $this->maxResponseBytes);
        }
    }

    /**
     * Reads no more than the configured maximum plus one byte, so unknown-length bodies stay bounded.
     */
    private function readResponseBody(StreamInterface $body): string
    {
        $content = '';

        while (! $body->eof()) {
            $remainingBytes = $this->maxResponseBytes - \strlen($content);
            $chunk = $body->read(\min(8192, \max(1, $remainingBytes + 1)));

            if ($chunk === '') {
                if ($body->eof()) {
                    break;
                }

                throw new \RuntimeException('Unable to read the HTTP response body.');
            }

            $content .= $chunk;
            if (\strlen($content) > $this->maxResponseBytes) {
                throw HttpResponseSizeLimitException::forSizes((string) \strlen($content), $this->maxResponseBytes);
            }
        }

        return $content;
    }

    /**
     * Extracts the list of records from the given HTTP response body array. Returns null if no valid record list is found. Allows partial record lists and nested structures.
     *
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>|null
     */
    private function extractRecords(array $body): ?array
    {
        if ($this->isRecordList($body)) {
            return $body;
        }

        $arrayValue = null;
        foreach ($body as $value) {
            if (\is_array($value)) {
                $arrayValue = $value;
                break;
            }
        }

        if ($arrayValue === null || ! $this->isRecordList($arrayValue)) {
            return null;
        }

        return $arrayValue;
    }

    /**
     * Determines if the given array represents a list of records. A record list is a non-empty list of associative arrays with string keys.
     *
     * @param  array<mixed>  $value
     *
     * @phpstan-assert-if-true list<array<string, mixed>> $value
     */
    private function isRecordList(array $value): bool
    {
        if (! \array_is_list($value)) {
            return false;
        }

        foreach ($value as $record) {
            if (! \is_array($record) || \array_is_list($record)) {
                return false;
            }

            foreach ($record as $key => $_) {
                if (! \is_string($key)) {
                    return false;
                }
            }
        }

        return true;
    }
}
