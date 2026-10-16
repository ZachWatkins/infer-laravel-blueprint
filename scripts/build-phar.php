<?php

declare(strict_types=1);

/**
 * Build the CLI application into a PHAR archive for PHPacker.
 *
 * @param  string  $projectRoot  The project root directory.
 * @param  string  $archivePath  The output PHAR path.
 */
function buildPharArchive(string $projectRoot, string $archivePath, array $paths = []): void
{
    if (! class_exists(Phar::class)) {
        throw new RuntimeException('The Phar extension is required to build the archive.');
    }

    if (ini_get('phar.readonly') === '1') {
        throw new RuntimeException('Set phar.readonly=0 before running the build script.');
    }

    $archiveDirectory = dirname($archivePath);

    if (! is_dir($archiveDirectory) && ! mkdir($archiveDirectory, 0777, true) && ! is_dir($archiveDirectory)) {
        throw new RuntimeException(sprintf('Unable to create the build directory at %s.', $archiveDirectory));
    }

    if (file_exists($archivePath) && ! unlink($archivePath)) {
        throw new RuntimeException(sprintf('Unable to remove the existing archive at %s.', $archivePath));
    }

    $phar = new Phar($archivePath);
    $phar->startBuffering();
    addProjectFilesToPhar($phar, $projectRoot, $paths);
    $phar->setStub($phar->createDefaultStub('index.php'));
    $phar->stopBuffering();
}

/**
 * Add the application files to a PHAR archive.
 *
 * @param  Phar  $phar  The archive instance.
 * @param  string  $projectRoot  The project root directory.
 * @param  array<int, string>  $paths  The project-relative files and directories to include.
 */
function addProjectFilesToPhar(Phar $phar, string $projectRoot, array $paths): void
{
    $resolvedProjectRoot = realpath($projectRoot);

    if ($resolvedProjectRoot === false) {
        throw new RuntimeException(sprintf('Unable to resolve the project root at %s.', $projectRoot));
    }

    foreach ($paths as $path) {
        $absolutePath = $resolvedProjectRoot.DIRECTORY_SEPARATOR.$path;

        if (is_dir($absolutePath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $filePath = $fileInfo->getPathname();
                $relativePath = substr($filePath, strlen($resolvedProjectRoot) + 1);
                $phar->addFile($filePath, str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
            }

            continue;
        }

        if (! is_file($absolutePath)) {
            throw new RuntimeException(sprintf('Required build input not found at %s.', $absolutePath));
        }

        $phar->addFile($absolutePath, $path);
    }
}

try {
    buildPharArchive(getcwd(), getcwd().'/.phar/infer-laravel-blueprint.phar', [
        'src',
        'vendor',
        'resources',
        'index.php',
        'composer.json',
    ]);

    fwrite(STDOUT, 'Built .phar/infer-laravel-blueprint.phar'.PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: '.$exception->getMessage().PHP_EOL);

    exit(1);
}
