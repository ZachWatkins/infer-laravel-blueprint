<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnInterface;

/**
 * An ordered collection of inferred Blueprint columns.
 *
 * @implements \IteratorAggregate<int, BlueprintColumnInterface>
 */
final class BlueprintColumnCollection implements BlueprintColumnCollectionInterface
{
    /**
     * @var array<int, BlueprintColumnInterface>
     */
    private array $columns = [];

    /**
     * @param  iterable<BlueprintColumnInterface>  $columns
     */
    public function __construct(iterable $columns = [])
    {
        foreach ($columns as $column) {
            $this->add($column);
        }
    }

    public function add(BlueprintColumnInterface $column): static
    {
        $this->columns[] = $column;

        return $this;
    }

    public function get(string $name): ?BlueprintColumnInterface
    {
        foreach ($this->columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array<int, BlueprintColumnInterface>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function count(): int
    {
        return \count($this->columns);
    }

    /**
     * @return \ArrayIterator<int, BlueprintColumnInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->columns);
    }
}
