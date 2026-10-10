<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\SQL\Parsers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\DatabaseType;
use ZachWatkins\InferLaravelBlueprint\SQL\Inferrers\ColumnTypeInferrer;
use ZachWatkins\InferLaravelBlueprint\SQL\Interfaces\ColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\SQL\Interfaces\ParserInterface;
use ZachWatkins\InferLaravelBlueprint\SQL\Interfaces\SQLColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Support\HttpUrl;

use function Flow\ETL\Adapter\Http\from_static_http_requests;
use function Flow\ETL\DSL\data_frame;

final class HttpParser implements ParserInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly ColumnTypeInferrerInterface $inferrer = new ColumnTypeInferrer,
    ) {}

    public function parse(
        string $source,
        string $databaseType = 'sqlite',
    ): SQLColumnCollectionInterface {
        HttpUrl::assertHasNoUserInfo($source);

        if (! in_array($databaseType, array_map(fn ($case) => $case->value, DatabaseType::cases()), true)) {
            throw new \InvalidArgumentException("Invalid database type: $databaseType, accepts: ".implode(', ', array_map(fn ($case) => $case->value, DatabaseType::cases())));
        }
        $databaseType = DatabaseType::from($databaseType);
        $request = (new Psr17Factory)
            ->createRequest('GET', $source)
            ->withHeader('Accept', 'application/json');

        $rows = data_frame()
            ->read(from_static_http_requests($this->client, $this->requests($request)))
            ->getEachAsArray();

        return $this->inferrer->infer($this->flatten($rows), $databaseType);
    }

    /**
     * Supports the same narrow scope as the file-based parsers: a flat list of flat records.
     *
     * This extracts only `response_body` from Flow's HTTP meta rows and accepts either:
     * - a top-level list of associative-array records, or
     * - an associative wrapper with exactly one array-valued key containing that list.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @return \Generator<array<string, mixed>>
     */
    private function flatten(iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            $body = $row['response_body'] ?? null;

            if (! \is_array($body)) {
                continue;
            }

            $records = $this->extractRecords($body);

            if ($records === null) {
                continue;
            }

            foreach ($records as $record) {
                if ($this->isRecord($record)) {
                    yield $record;
                }
            }
        }
    }

    /**
     * @param  array<mixed>  $body
     * @return list<mixed>|null
     */
    private function extractRecords(array $body): ?array
    {
        if (\array_is_list($body)) {
            return $body;
        }

        $arrayValues = [];

        foreach ($body as $value) {
            if (\is_array($value)) {
                $arrayValues[] = $value;
            }
        }

        if (\count($arrayValues) !== 1) {
            return null;
        }

        $records = $arrayValues[0];

        if (! \array_is_list($records)) {
            return null;
        }

        return $records;
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private function isRecord(mixed $value): bool
    {
        if (! \is_array($value) || \array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $_) {
            if (! \is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return \Generator<RequestInterface>
     */
    private function requests(RequestInterface $request): \Generator
    {
        yield $request;
    }
}
