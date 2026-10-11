<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;

/**
 * @property string $name
 * @property-read string $tableNameSingular
 * @property-read string $tableNamePlural
 */
class BlueprintModel
{
    protected Inflector $inflector;

    public readonly BlueprintColumnCollectionInterface $columns;

    public function __construct(
        public string $name,
        BlueprintColumnCollectionInterface $columns
    ) {
        $this->columns = $columns;
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * Resolve table name accessors through property syntax.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'tableNameSingular' => $this->inflector->tableize($this->name),
            'tableNamePlural' => $this->inflector->pluralize($this->inflector->tableize($this->name)),
            default => throw new \OutOfBoundsException("Undefined property: {$name}"),
        };
    }

    /**
     * @return array<int, string>
     */
    public function columnNames(): array
    {
        $names = [];
        foreach ($this->columns as $column) {
            $names[] = $column->getName();
        }

        return $names;
    }
}
