<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces;

/**
 * Reads a data source and infers its SQL column schema.
 */
interface DataReaderInterface
{
    /**
     * @param  string  $source  Path or URI to the data source to read.
     */
    public function read(
        string $source
    ): BlueprintColumnCollectionInterface;
}
