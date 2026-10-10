<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnInterface;

/**
 * This class contains the data necessary to represent a Laravel Shift Blueprint model.
 */
class Blueprint
{
    /**
     * @param  iterable<int, BlueprintColumnInterface>  $columns
     */
    public function __construct(
        public string $modelName = 'Model',
        protected iterable $columns = []
    ) {}
}
