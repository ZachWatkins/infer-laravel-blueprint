<?php

/**
 * Copy the PHP application and agent skill to the global Copilot skill directory.
 */

declare(strict_types=1);

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

$homeDirectory = getenv($isWindows ? 'USERPROFILE' : 'HOME');
if (! is_string($homeDirectory) || $homeDirectory === '') {
    throw new RuntimeException('Unable to determine the home directory.');
}

$destination = $homeDirectory.DIRECTORY_SEPARATOR.'.copilot'.DIRECTORY_SEPARATOR.'skills'.DIRECTORY_SEPARATOR.'infer-laravel-blueprint';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--destination=')) {
        $destination = substr($arg, strlen('--destination='));
        break;
    }
}

if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
    throw new RuntimeException(sprintf('Unable to create the skill directory at %s.', $destination));
}

$projectRoot = dirname(__DIR__);
$copyFile = static function (string $source, string $target): void {
    if (! copy($source, $target)) {
        throw new RuntimeException(sprintf('Unable to copy %s to %s.', $source, $target));
    }
};

$copyDirectory = static function (string $source, string $target) use ($copyFile): void {
    if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
        throw new RuntimeException(sprintf('Unable to create directory %s.', $target));
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        $relativePath = substr($fileInfo->getPathname(), strlen($source) + 1);
        $targetPath = $target.DIRECTORY_SEPARATOR.$relativePath;

        if ($fileInfo->isDir()) {
            if (! is_dir($targetPath) && ! mkdir($targetPath, 0777, true) && ! is_dir($targetPath)) {
                throw new RuntimeException(sprintf('Unable to create directory %s.', $targetPath));
            }

            continue;
        }

        $targetDirectory = dirname($targetPath);
        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Unable to create directory %s.', $targetDirectory));
        }

        $copyFile($fileInfo->getPathname(), $targetPath);
    }
};

$copyFile($projectRoot.'/LICENSE', $destination.DIRECTORY_SEPARATOR.'LICENSE');
$copyFile($projectRoot.'/SKILL.md', $destination.DIRECTORY_SEPARATOR.'SKILL.md');
$copyFile($projectRoot.'/index.php', $destination.DIRECTORY_SEPARATOR.'index.php');
$copyDirectory($projectRoot.'/src', $destination.DIRECTORY_SEPARATOR.'src');
$copyDirectory($projectRoot.'/vendor', $destination.DIRECTORY_SEPARATOR.'vendor');

$obsoleteExecutable = $destination.DIRECTORY_SEPARATOR.'infer-laravel-blueprint';
if (is_file($obsoleteExecutable) && ! unlink($obsoleteExecutable)) {
    throw new RuntimeException(sprintf('Unable to remove obsolete executable %s.', $obsoleteExecutable));
}
