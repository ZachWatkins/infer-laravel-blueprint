<?php

declare(strict_types=1);

use Tests\Blueprint\Fixtures\Generators\TestBlueprintGenerator;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigResource;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigView;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;

require_once __DIR__.'/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$sourceDataPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Blueprint'.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'sample.json';
$outDir = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Blueprint'.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'blueprint'.DIRECTORY_SEPARATOR;
$existingFiles = glob($outDir.'*.yaml');
if ($existingFiles === false) {
    throw new RuntimeException('Unable to find existing Blueprint fixtures.');
}
foreach ($existingFiles as $file) {
    unlink($file);
}
$blueprintGenerator = new TestBlueprintGenerator;
$blueprintGenerator->generate($sourceDataPath, $outDir.'sample_basic.yaml');
$blueprintGenerator->generate($sourceDataPath, $outDir.'sample_inertia_basic.yaml', new BlueprintConfig(
    view: BlueprintConfigView::Inertia,
    resources: [BlueprintConfigResource::Web],
    seeders: true
));
$blueprintGenerator->generate($sourceDataPath, $outDir.'sample_inertia_view_only.yaml', new BlueprintConfig(
    view: BlueprintConfigView::Inertia,
    resources: [BlueprintConfigResource::Index, BlueprintConfigResource::Show]
));
$blueprintGenerator->generate($sourceDataPath, $outDir.'sample_crud_with_custom_methods.yaml', new BlueprintConfig(
    methods: array_merge(BlueprintConfigResource::webMethods(), ['customMethod'])
));
$blueprintGenerator->generate($sourceDataPath, $outDir.'sample_api_methods.yaml', new BlueprintConfig(
    methods: BlueprintConfigResource::apiMethods()
));
