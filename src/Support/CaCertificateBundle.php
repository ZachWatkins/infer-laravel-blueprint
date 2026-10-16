<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Support;

final class CaCertificateBundle
{
    private static ?string $path = null;

    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        $archivePath = \Phar::running(true);
        if ($archivePath !== '') {
            $archiveFilePath = \str_starts_with($archivePath, 'phar://')
                ? \substr($archivePath, \strlen('phar://'))
                : $archivePath;
            $sidecarPath = \dirname($archiveFilePath).DIRECTORY_SEPARATOR.'cacert.pem';
            if (\is_file($sidecarPath)) {
                $resolvedPath = \realpath($sidecarPath);
                if ($resolvedPath === false) {
                    throw new \RuntimeException(\sprintf('Unable to resolve the CA certificate bundle at %s.', $sidecarPath));
                }

                return self::$path = $resolvedPath;
            }

            $embeddedBundlePath = 'phar://'.$archiveFilePath.'/resources/cacert.pem';
            if (! \is_file($embeddedBundlePath)) {
                throw new \RuntimeException(\sprintf('Bundled CA certificate file not found at %s.', $embeddedBundlePath));
            }

            $bundleContents = \file_get_contents($embeddedBundlePath);
        } else {
            $bundlePath = \dirname(__DIR__, 2).'/resources/cacert.pem';
            if (! \is_file($bundlePath)) {
                self::downloadBundle($bundlePath);
            }

            $resolvedPath = \realpath($bundlePath);
            if ($resolvedPath === false) {
                throw new \RuntimeException(\sprintf('Unable to resolve the CA certificate bundle at %s.', $bundlePath));
            }

            return self::$path = $resolvedPath;
        }

        if ($bundleContents === false) {
            throw new \RuntimeException('Unable to read the bundled CA certificate file.');
        }

        $temporaryPath = \tempnam(\sys_get_temp_dir(), 'infer-ca-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create a temporary CA certificate file.');
        }

        if (\file_put_contents($temporaryPath, $bundleContents) === false) {
            \unlink($temporaryPath);

            throw new \RuntimeException('Unable to extract the bundled CA certificate file.');
        }

        \register_shutdown_function(static function () use ($temporaryPath): void {
            if (\is_file($temporaryPath) && ! \unlink($temporaryPath)) {
                \error_log(\sprintf('Unable to remove temporary CA certificate file at %s.', $temporaryPath));
            }
        });

        return self::$path = $temporaryPath;
    }

    private static function downloadBundle(string $bundlePath): void
    {
        $sourceUrl = 'https://curl.se/ca/cacert.pem';
        $context = \stream_context_create([
            'http' => ['timeout' => 30],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $contents = \file_get_contents($sourceUrl, false, $context);
        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to download the CA certificate bundle from %s.', $sourceUrl));
        }

        if (! \str_contains($contents, '-----BEGIN CERTIFICATE-----')
            || ! \str_contains($contents, '-----END CERTIFICATE-----')) {
            throw new \RuntimeException(\sprintf('Downloaded CA certificate bundle from %s is invalid.', $sourceUrl));
        }

        $directoryPath = \dirname($bundlePath);
        if (! \is_dir($directoryPath) && ! \mkdir($directoryPath, 0777, true) && ! \is_dir($directoryPath)) {
            throw new \RuntimeException(\sprintf('Unable to create the CA certificate directory at %s.', $directoryPath));
        }

        $temporaryPath = \tempnam($directoryPath, 'cacert-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create a temporary CA certificate file.');
        }

        if (\file_put_contents($temporaryPath, $contents) === false) {
            \unlink($temporaryPath);

            throw new \RuntimeException('Unable to save the downloaded CA certificate bundle.');
        }

        if (! \rename($temporaryPath, $bundlePath)) {
            \unlink($temporaryPath);

            throw new \RuntimeException(\sprintf('Unable to save the CA certificate bundle at %s.', $bundlePath));
        }
    }
}
