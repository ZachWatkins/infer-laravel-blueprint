<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces;

/**
 * Infers a {@see BlueprintColumnCollectionInterface} from a stream of data rows.
 *
 * Implementations must evaluate every row for every column in order to determine
 * the safest possible Laravel column type and modifiers (nullable, unique, unsigned,
 * auto-incrementing).
 *
 * Rows do not need to share a consistent set of keys: a column absent from a given
 * row is treated the same as an explicit null value for that row, so parsers may
 * pass rows exactly as extracted (e.g. optional fields in JSON/XML documents)
 * without pre-normalizing them to a superset of keys.
 */
interface BlueprintColumnTypeInferrerInterface
{
    /**
     * @param  iterable<array<string, mixed>>  $rows  Each row is an associative array keyed by column name.
     */
    public function infer(
        iterable $rows
    ): BlueprintColumnCollectionInterface;
}
