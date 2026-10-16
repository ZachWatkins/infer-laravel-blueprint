<?php

/**
 * Copy the agent skill and all script files to ~/.copilot/skills/infer-laravel-blueprint in a way that is compatible with Mac, Linux, and Windows.
 */

declare(strict_types=1);

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Get the --destination option from the command line arguments.
$destination = $isWindows
    ? getenv('USERPROFILE').'\\.copilot\\skills\\infer-laravel-blueprint\\'
    : getenv('HOME').'/.copilot/skills/infer-laravel-blueprint/';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--destination=')) {
        $destination = realpath(substr($arg, strlen('--destination=')));
        if (! $destination) {
            throw new RuntimeException('Invalid destination path.');
        }
        if (! is_dir($destination)) {
            throw new RuntimeException('Destination path is not a directory.');
        }
        break;
    }
}

$architecture = php_uname('m');
if (! in_array($architecture, ['x86_64', 'arm64', 'AMD64'])) {
    throw new RuntimeException(sprintf('Unsupported architecture: %s', $architecture));
}
$phpackerFilePath = match (true) {
    PHP_OS_FAMILY === 'Darwin' => match ($architecture) {
        'x86_64', 'AMD64' => 'build/mac/mac-x64',
        'arm64' => 'build/mac/mac-arm',
        default => throw new RuntimeException(sprintf('Unsupported architecture: %s', $architecture)),
    },
    ! $isWindows => match ($architecture) {
        'x86_64', 'AMD64' => 'build/linux/linux-x64',
        'arm64' => 'build/linux/linux-arm',
        default => throw new RuntimeException(sprintf('Unsupported architecture: %s', $architecture)),
    },
    $isWindows => 'build/windows/windows-x64',
    default => throw new RuntimeException(sprintf('Unsupported platform: %s', PHP_OS_FAMILY)),
};

if (! $isWindows) {
    if (is_dir($destination)) {
        // Empty the directory before copying new files.
        $files = glob($destination.'/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                shell_exec(sprintf('rm -rf %s', escapeshellarg($file)));
            } else {
                unlink($file);
            }
        }
    } else {
        mkdir($destination, 0777, true);
    }
    copy('LICENSE', $destination.'/LICENSE');
    copy('SKILL.md', $destination.'/SKILL.md');
    if (! copy('resources/cacert.pem', $destination.'/cacert.pem')) {
        throw new RuntimeException(sprintf('Unable to copy the CA certificate bundle to %s.', $destination.'/cacert.pem'));
    }
    $binaryDestination = $destination.'/infer-laravel-blueprint';
    if (! copy($phpackerFilePath, $binaryDestination)) {
        throw new RuntimeException(sprintf('Unable to copy the executable from %s to %s.', $phpackerFilePath, $binaryDestination));
    }
    if (! chmod($binaryDestination, 0755)) {
        throw new RuntimeException(sprintf('Unable to make the executable at %s executable.', $binaryDestination));
    }
} else {
    if (is_dir($destination)) {
        // Empty the directory before copying new files.
        $files = glob($destination.'\\*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                shell_exec(sprintf('rmdir /S /Q %s', escapeshellarg($file)));
            } else {
                unlink($file);
            }
        }
    } else {
        mkdir($destination, 0777, true);
    }
    copy('LICENSE', $destination.'/LICENSE');
    copy('SKILL.md', $destination.'/SKILL.md');
    if (! copy('resources/cacert.pem', $destination.'\\cacert.pem')) {
        throw new RuntimeException(sprintf('Unable to copy the CA certificate bundle to %s.', $destination.'\\cacert.pem'));
    }
    $binaryDestination = $destination.'\\infer-laravel-blueprint';
    if (! copy($phpackerFilePath, $binaryDestination)) {
        throw new RuntimeException(sprintf('Unable to copy the executable from %s to %s.', $phpackerFilePath, $binaryDestination));
    }
}
