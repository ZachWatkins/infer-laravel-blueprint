<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces;

/**
 * Reads a data source and infers its Laravel Blueprint columns.
 */
interface BlueprintParserInterface
{
    /**
     * @param  string  $source  Path or URI to the data source to parse.
     */
    public function parse(
        string $source
    ): BlueprintColumnCollectionInterface;
}
