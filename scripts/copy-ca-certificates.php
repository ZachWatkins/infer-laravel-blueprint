<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$certificatePath = $projectRoot.'/resources/cacert.pem';

if (! is_file($certificatePath)) {
    throw new RuntimeException(sprintf('CA certificate bundle not found at %s.', $certificatePath));
}

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
