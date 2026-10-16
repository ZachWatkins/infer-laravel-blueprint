<?php

declare(strict_types=1);
use ZachWatkins\InferLaravelBlueprint\Support\CaCertificateBundle;

$projectRoot = dirname(__DIR__);
$autoloadPath = $projectRoot.'/vendor/autoload.php';
if (! is_file($autoloadPath)) {
    throw new RuntimeException(sprintf('Composer autoloader not found at %s.', $autoloadPath));
}
require $autoloadPath;

$certificatePath = CaCertificateBundle::path();

$executables = glob($projectRoot.'/build/*/*');
if ($executables === false) {
    throw new RuntimeException('Unable to find PHPacker build outputs.');
}

foreach ($executables as $executablePath) {
    if (! is_file($executablePath)) {
        continue;
    }

    $destinationPath = dirname($executablePath).'/cacert.pem';
    if (! copy($certificatePath, $destinationPath)) {
        throw new RuntimeException(sprintf('Unable to copy the CA certificate bundle to %s.', $destinationPath));
    }
}
