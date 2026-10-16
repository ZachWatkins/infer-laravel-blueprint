<?php

declare(strict_types=1);

use ZachWatkins\InferLaravelBlueprint\Support\CaCertificateBundle;

it('resolves the bundled Mozilla CA certificates', function () {
    $certificatePath = CaCertificateBundle::path();

    expect($certificatePath)->toBe(realpath(__DIR__.'/../../../resources/cacert.pem'))
        ->and(file_get_contents($certificatePath))->toContain('Certificate data from Mozilla');
});
