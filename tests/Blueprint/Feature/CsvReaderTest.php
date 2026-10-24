<?php

declare(strict_types=1);

use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigResource;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigView;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Lexers\BlueprintFileLexer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintModel;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Readers\CsvReader;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Readers\JsonReader;

it('infers the expected Blueprint file from the MySQL CSV fixture', function () {
    $dataFixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/../SQL/fixtures/data/test_mysql.csv');
    $reader = new CsvReader;
    $columns = $reader->read($dataFixture);
    $model = new BlueprintModel('Model', $columns);
    $config = new BlueprintConfig(models: [$model]);
    $lexer = new BlueprintFileLexer;
    $output = $lexer->toString($config);
    $fixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/fixtures/blueprint/test_mysql_basic.yaml');
    expect($output)->toBeString();
    expect($output)->toContain(file_get_contents($fixture));
});

it('infers the expected Blueprint Inertia web resource file from the MySQL JSON fixture', function () {
    $dataFixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/../SQL/fixtures/data/test_mysql.json');
    $reader = new JsonReader;
    $columns = $reader->read($dataFixture);
    $model = new BlueprintModel('Model', $columns);
    $config = new BlueprintConfig(
        models: [$model],
        view: BlueprintConfigView::Inertia,
        resources: [BlueprintConfigResource::Web],
        seeders: true
    );
    $lexer = new BlueprintFileLexer;
    $output = $lexer->toString($config);
    $fixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/fixtures/blueprint/test_mysql_inertia_basic.yaml');
    expect($output)->toBeString();
    expect($output)->toContain(file_get_contents($fixture));
});

it('infers the expected Blueprint Inertia view-only web resource file from the MySQL JSON fixture', function () {
    $dataFixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/../SQL/fixtures/data/test_mysql.json');
    $reader = new JsonReader;
    $columns = $reader->read($dataFixture);
    $model = new BlueprintModel('Model', $columns);
    $config = new BlueprintConfig(
        models: [$model],
        view: BlueprintConfigView::Inertia,
        resources: [BlueprintConfigResource::Index, BlueprintConfigResource::Show]
    );
    $lexer = new BlueprintFileLexer;
    $output = $lexer->toString($config);
    $fixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/fixtures/blueprint/test_mysql_inertia_view_only.yaml');
    expect($output)->toBeString();
    expect($output)->toContain(file_get_contents($fixture));
});

it('infers the expected Blueprint CRUD with custom methods web resource file from the MySQL JSON fixture', function () {
    $dataFixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/../SQL/fixtures/data/test_mysql.json');
    $reader = new JsonReader;
    $columns = $reader->read($dataFixture);
    $model = new BlueprintModel('Model', $columns);
    $config = new BlueprintConfig(
        models: [$model],
        methods: array_merge(BlueprintConfigResource::webMethods(), ['customMethod'])
    );
    $lexer = new BlueprintFileLexer;
    $output = $lexer->toString($config);
    $fixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/fixtures/blueprint/test_mysql_crud_with_custom_methods.yaml');
    expect($output)->toBeString();
    expect($output)->toContain(file_get_contents($fixture));
});

it('infers the expected Blueprint API methods file from the MySQL JSON fixture', function () {
    $dataFixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/../SQL/fixtures/data/test_mysql.json');
    $reader = new JsonReader;
    $columns = $reader->read($dataFixture);
    $model = new BlueprintModel('Model', $columns);
    $config = new BlueprintConfig(
        models: [$model],
        methods: BlueprintConfigResource::apiMethods()
    );
    $lexer = new BlueprintFileLexer;
    $output = $lexer->toString($config);
    $fixture = dirname(__DIR__).str_replace('/', DIRECTORY_SEPARATOR, '/fixtures/blueprint/test_mysql_api_methods.yaml');
    expect($output)->toBeString();
    expect($output)->toContain(file_get_contents($fixture));
});
