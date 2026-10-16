<?php

declare(strict_types=1);
use ZachWatkins\InferLaravelBlueprint\Support\CaCertificateBundle;

$projectRoot = dirname(__DIR__);
$autoloadPath = $projectRoot.'/vendor/autoload.php';
if (! is_file($autoloadPath)) {
    throw new RuntimeException(sprintf('Composer autoloader not found at %s.', $autoloadPath));
}
require $autoloadPath;

CaCertificateBundle::path();
