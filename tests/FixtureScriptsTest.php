<?php

describe('fixture build scripts', function () {
    it('can run the Blueprint fixture builder from scripts/', function () {
        $root = dirname(__DIR__);

        $scriptPath = $root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'build-test-fixtures-blueprint.php';
        expect(file_exists($scriptPath))->toBeTrue();

        $command = sprintf('php %s 2>&1', escapeshellarg($scriptPath));
        exec($command, $output, $exitCode);

        expect($exitCode)->toBe(0, implode(PHP_EOL, $output));
    });
});
