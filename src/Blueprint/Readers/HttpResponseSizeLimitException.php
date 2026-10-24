<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Readers;

final class HttpResponseSizeLimitException extends \RuntimeException
{
    /**
     * Creates an exception describing a response rejected by its declared or actual size.
     */
    public static function forSizes(string $responseBytes, int $maximumBytes): self
    {
        return new self(\sprintf(
            'HTTP response size (%s bytes) exceeds the safe limit (%d bytes); the response was not parsed.',
            $responseBytes,
            $maximumBytes,
        ));
    }
}
