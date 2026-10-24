<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces;

/**
 * Represents an ordered collection of inferred Blueprint columns.
 *
 * @extends \IteratorAggregate<int, BlueprintColumnInterface>
 */
interface BlueprintColumnCollectionInterface extends \Countable, \IteratorAggregate
{
    public function add(BlueprintColumnInterface $column): static;

    public function get(string $name): ?BlueprintColumnInterface;

    /**
     * @return array<int, BlueprintColumnInterface>
     */
    public function getColumns(): array;
}
