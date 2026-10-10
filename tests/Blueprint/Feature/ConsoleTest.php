<?php

declare(strict_types=1);

namespace Tests\Blueprint\Features;

use GuzzleHttp\Psr7\PumpStream;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Lexers\BlueprintFileLexer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintModel;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\CsvParser;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\ExcelParser;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\HttpParser;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\HttpResponseSizeLimitException;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\JsonParser;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\XmlParser;
use ZachWatkins\InferLaravelBlueprint\Console;

$runConsole = static function (array $argv, ?array $parserClasses = null, ?ClientInterface $httpClient = null): array {
    $stdout = \fopen('php://temp', 'w+');
    $stderr = \fopen('php://temp', 'w+');

    if ($stdout === false || $stderr === false) {
        throw new \RuntimeException('Unable to create in-memory output streams.');
    }

    $exitCode = (new Console($stdout, $stderr, $parserClasses, $httpClient))->run($argv);

    \rewind($stdout);
    \rewind($stderr);

    $stdoutOutput = (string) \stream_get_contents($stdout);
    $stderrOutput = (string) \stream_get_contents($stderr);

    \fclose($stdout);
    \fclose($stderr);

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdoutOutput,
        'stderr' => $stderrOutput,
    ];
};

afterEach(function () {
    $expectedSavePath = realpath(__DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data/model-blueprint.yaml'));
    if ($expectedSavePath && file_exists($expectedSavePath)) {
        unlink($expectedSavePath);
    }
});

it('prints usage when no source argument is provided', function () use ($runConsole) {
    $result = $runConsole(['infer-laravel-blueprint']);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain(Console::help())
        ->and($result['stderr'])->toContain('Default: 30 seconds. Accepts: 1-3600.');
});

it('outputs blueprint YAML file contents to the console if --save is not provided', function () use ($runConsole) {
    $dataFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data/test_mysql.csv');
    $blueprintFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../fixtures/blueprint/test_mysql_basic.yaml');

    $result = $runConsole(['infer-laravel-blueprint', '--model=Model', $dataFixturePath]);

    $expectedOutput = file_get_contents($blueprintFixturePath);

    expect($expectedOutput)->not->toBeFalse();

    expect($result['exitCode'])->toBe(0)
        ->and($result['stdout'])->toContain(file_get_contents($blueprintFixturePath));
});

it('outputs blueprint YAML from an HTTP source using an injected client', function () use ($runConsole) {
    $blueprintFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../fixtures/blueprint/test_mysql_basic.yaml');
    $httpFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data/test_mysql.http');
    $client = new class($httpFixturePath) implements ClientInterface
    {
        public ?RequestInterface $lastRequest = null;

        public function __construct(private readonly string $fixturePath) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->lastRequest = $request;

            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                file_get_contents($this->fixturePath)
            );
        }
    };

    $result = $runConsole([
        'infer-laravel-blueprint',
        '--model=Model',
        '--http-header=Accept: application/vnd.example+json',
        '--http-timeout=5',
        'https://example.com/users',
    ], httpClient: $client);

    expect($result['exitCode'])->toBe(0)
        ->and($result['stderr'])->toBe('')
        ->and(\rtrim($result['stdout']))->toBe(\rtrim(file_get_contents($blueprintFixturePath)))
        ->and($client->lastRequest)->not->toBeNull()
        ->and($client->lastRequest->getMethod())->toBe('GET')
        ->and($client->lastRequest->getHeaderLine('Accept'))->toBe('application/vnd.example+json');

    $outputDirectory = \dirname($httpFixturePath);
    $expectedSavePath = realpath($outputDirectory).DIRECTORY_SEPARATOR.'model-blueprint.yaml';
    $saveResult = $runConsole([
        'infer-laravel-blueprint',
        '--model=Model',
        '--cwd='.$outputDirectory,
        '--save',
        'https://example.com/users',
    ], httpClient: $client);

    expect($saveResult['exitCode'])->toBe(0)
        ->and($saveResult['stdout'])->toBe('Blueprint file saved to '.$expectedSavePath)
        ->and(\rtrim(file_get_contents($expectedSavePath)))->toBe(\rtrim(file_get_contents($blueprintFixturePath)));
});

it('rejects credential HTTP headers', function () use ($runConsole) {
    foreach (['Authorization', 'Proxy-Authorization', 'Cookie'] as $headerName) {
        $result = $runConsole([
            'infer-laravel-blueprint',
            '--model=Model',
            '--http-header='.$headerName.': secret',
            'https://example.com/users',
        ]);

        expect($result['exitCode'])->toBe(1)
            ->and($result['stdout'])->toBe('')
            ->and($result['stderr'])->toContain('Credential HTTP headers are not allowed.');
    }
});

it('rejects URL userinfo before sending an HTTP request', function () use ($runConsole) {
    $client = new class implements ClientInterface
    {
        public int $requestCount = 0;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requestCount++;

            return new Response(200, ['Content-Type' => 'application/json'], '[]');
        }
    };

    $source = 'https://user:secret@example.com/users.json';
    $result = $runConsole([
        'infer-laravel-blueprint',
        '--model=Model',
        $source,
    ], httpClient: $client);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain('HTTP URLs must not contain userinfo credentials.')
        ->and($client->requestCount)->toBe(0);

    expect(fn () => (new HttpParser($client))->parse($source))
        ->toThrow(\InvalidArgumentException::class, 'HTTP URLs must not contain userinfo credentials.');
    expect($client->requestCount)->toBe(0);
});

it('rejects an oversized response from HEAD without requesting its body', function () use ($runConsole) {
    $client = new class implements ClientInterface
    {
        /** @var list<string> */
        public array $methods = [];

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->methods[] = $request->getMethod();

            return $request->getMethod() === 'HEAD'
                ? new Response(200, ['Content-Length' => (string) PHP_INT_MAX])
                : new Response(200, ['Content-Type' => 'application/json'], '[]');
        }
    };

    $result = $runConsole([
        'infer-laravel-blueprint',
        '--model=Model',
        'https://example.com/large.json',
    ], httpClient: $client);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain('exceeds the safe limit')
        ->and($client->methods)->toBe(['HEAD']);
});

it('rejects an oversized GET body when HEAD has no content length', function () {
    $client = new class implements ClientInterface
    {
        /** @var list<string> */
        public array $methods = [];

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->methods[] = $request->getMethod();

            return $request->getMethod() === 'HEAD'
                ? new Response(200)
                : new Response(200, ['Content-Type' => 'application/json'], '[{"id":1}]');
        }
    };

    $parser = new HttpParser($client, maxResponseBytes: 4);

    expect(fn () => $parser->parse('https://example.com/users.json'))
        ->toThrow(HttpResponseSizeLimitException::class);
    expect($client->methods)->toBe(['HEAD', 'GET']);
});

it('stops reading an unknown-length response as soon as the limit is exceeded', function () {
    $bytesRead = 0;
    $body = new PumpStream(function (int $length) use (&$bytesRead): string {
        $chunk = \substr(\str_repeat('x', 100), $bytesRead, $length);
        $bytesRead += \strlen($chunk);

        return $chunk;
    });

    $client = new class($body) implements ClientInterface
    {
        public function __construct(private readonly PumpStream $body) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return $request->getMethod() === 'HEAD'
                ? new Response(200)
                : new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $parser = new HttpParser($client, maxResponseBytes: 4);

    expect(fn () => $parser->parse('https://example.com/users.json'))
        ->toThrow(HttpResponseSizeLimitException::class);
    expect($bytesRead)->toBe(5);
});

it('rejects an invalid HTTP timeout', function () use ($runConsole) {
    $result = $runConsole([
        'infer-laravel-blueprint',
        '--model=Model',
        '--http-timeout=0',
        'https://example.com/users.json',
    ]);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stderr'])->toContain('HTTP timeout must be an integer between 1 and 3600 seconds.');
});

it('selects a matching parser and Accept header for an HTTP file URL', function () use ($runConsole) {
    $fixturesDirectory = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data');
    $formats = [
        ['csv', 'text/csv', 'test_mysql.csv'],
        ['json', 'application/json', 'test_mysql.json'],
        ['xml', 'application/xml', 'test_mysql.xml'],
        ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test_mysql.xlsx'],
        ['xls', 'application/vnd.ms-excel', 'test_mysql.xlsx'],
    ];
    $parserClasses = [
        'csv' => CsvParser::class,
        'json' => JsonParser::class,
        'xml' => XmlParser::class,
        'xlsx' => ExcelParser::class,
        'xls' => ExcelParser::class,
        'ods' => ExcelParser::class,
    ];

    foreach ($formats as [$extension, $accept, $fixtureName]) {
        $fixturePath = $fixturesDirectory.DIRECTORY_SEPARATOR.$fixtureName;
        $expectedColumns = (new $parserClasses[$extension])->parse($fixturePath);
        $expectedOutput = (new BlueprintFileLexer)->toString(new BlueprintConfig(
            models: [new BlueprintModel('Model', $expectedColumns)],
        ));
        $client = new class($fixturePath) implements ClientInterface
        {
            public ?RequestInterface $lastRequest = null;

            public function __construct(private readonly string $fixturePath) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(
                    200,
                    ['Content-Type' => $request->getHeaderLine('Accept')],
                    file_get_contents($this->fixturePath)
                );
            }
        };

        $result = $runConsole([
            'infer-laravel-blueprint',
            '--model=Model',
            'https://example.com/users.'.$extension.'?download=1',
        ], httpClient: $client);

        expect($result['exitCode'])->toBe(0)
            ->and($result['stdout'])->toBe($expectedOutput)
            ->and($client->lastRequest)->not->toBeNull()
            ->and($client->lastRequest->getHeaderLine('Accept'))->toBe($accept);
    }
});

it('saves a blueprint YAML file to disk in same folder as data source if --save is provided', function () use ($runConsole) {
    $dataFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data/test_mysql.csv');
    $expectedSavePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../../SQL/fixtures/data/model-blueprint.yaml');
    $blueprintFixturePath = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, '/../fixtures/blueprint/test_mysql_basic.yaml');
    $result = $runConsole(['infer-laravel-blueprint', '--model=Model', '--save', $dataFixturePath]);
    expect(file_exists(realpath($expectedSavePath)))->toBeTrue();
    expect($result['exitCode'])->toBe(0)
        ->and($result['stdout'])->toContain('Blueprint file saved to '.realpath($expectedSavePath))
        ->and(file_exists(realpath($expectedSavePath)))->toBeTrue()
        ->and(\rtrim(file_get_contents(realpath($expectedSavePath))))->toBe(\rtrim(file_get_contents($blueprintFixturePath)));
});
