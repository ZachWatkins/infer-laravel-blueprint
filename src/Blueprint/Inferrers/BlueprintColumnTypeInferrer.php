<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Inferrers;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\ColumnModifier;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\LaravelColumnType;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintColumn;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintColumnCollection;
use ZachWatkins\InferLaravelBlueprint\Parsers;
use ZachWatkins\InferLaravelBlueprint\Support\ColumnStats;

/**
 * Evaluates every value of every column in a stream of rows to infer the safest possible
 * SQL column type and modifiers (nullable, unique, unsigned, auto-incrementing) for a
 * given database engine.
 *
 * This is the single shared inference contract used by every parser in
 * {@see Parsers} so that type-selection behavior is
 * identical regardless of the data source format.
 */
final class BlueprintColumnTypeInferrer implements BlueprintColumnTypeInferrerInterface
{
    public function infer(
        iterable $rows,
    ): BlueprintColumnCollectionInterface {
        /** @var array<string, ColumnStats> $stats */
        $stats = [];
        /** @var array<int, string> $order */
        $order = [];
        $totalRows = 0;

        foreach ($rows as $row) {
            $totalRows++;

            foreach ($row as $column => $value) {
                if (! isset($stats[$column])) {
                    $stats[$column] = new ColumnStats;
                    $order[] = $column;
                }

                $stats[$column]->record($value);
            }
        }

        $collection = new BlueprintColumnCollection;

        foreach ($order as $column) {
            $columnStats = $stats[$column];

            // A row that never mentioned this column is treated exactly like an
            // explicit null value, so parsers do not need to pre-normalize rows to a
            // consistent superset of keys (e.g. optional fields in JSON/XML documents).
            for ($missing = $totalRows - $columnStats->totalCount; $missing > 0; $missing--) {
                $columnStats->record(null);
            }

            $collection->add(new BlueprintColumn(
                $column,
                $this->resolveType($columnStats),
                $this->resolveAttributes($columnStats),
                $this->resolveModifiers($columnStats),
            ));
        }

        return $collection;
    }

    private function resolveType(ColumnStats $stats): LaravelColumnType
    {
        return match (true) {
            $stats->allBool => LaravelColumnType::boolean,
            $stats->allInt => $this->resolveIntegerType($stats),
            $stats->allNumeric => LaravelColumnType::decimal,
            $stats->allDate => LaravelColumnType::date,
            $stats->allDateTime => LaravelColumnType::dateTime,
            $stats->allTime => LaravelColumnType::time,
            default => $this->resolveStringType($stats),
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveAttributes(ColumnStats $stats): array
    {
        $type = $this->resolveType($stats);
        // If the type is a decimal, return an array with precision and scale.
        if (\in_array($type, [LaravelColumnType::decimal, LaravelColumnType::float, LaravelColumnType::double], true)) {
            return [(string) $stats->precision, (string) $stats->scale];
        }
        if ($type === LaravelColumnType::char && $stats->allStringLengthsSame) {
            return [(string) $stats->maxStringLength];
        }
        if (\in_array($type, [LaravelColumnType::enum, LaravelColumnType::set], true) && ! empty($stats->getSeenValues())) {
            return $stats->getSeenValues();
        }

        return [];
    }

    /**
     * @return array<int, ColumnModifier>
     */
    private function resolveModifiers(ColumnStats $stats): array
    {
        $modifiers = [];

        if ($stats->isNullable()) {
            $modifiers[] = ColumnModifier::Nullable;
        }

        if ($stats->isUnique()) {
            $modifiers[] = ColumnModifier::Unique;
        }

        if ($stats->isUnsigned()) {
            if ($stats->allInt) {
                $modifiers[] = ColumnModifier::Unsigned;
            }
        }

        if ($stats->isAutoIncrement()) {
            $modifiers[] = ColumnModifier::AutoIncrement;
        }

        return $modifiers;
    }

    private function resolveIntegerType(ColumnStats $stats): LaravelColumnType
    {
        $max = (int) $stats->maxValue;
        $min = (int) \abs($stats->minValue);

        if (! $stats->hasNegative) {
            if ($stats->allYear) {
                return LaravelColumnType::year;
            }
            if ($max <= 255) {
                if ($min === 1 && $stats->sequenceIntact) {
                    return LaravelColumnType::tinyIncrements;
                }

                return LaravelColumnType::tinyInteger;
            }
            if ($max <= 65_535) {
                if ($min === 1 && $stats->sequenceIntact) {
                    return LaravelColumnType::smallIncrements;
                }

                return LaravelColumnType::smallInteger;
            }
            if ($max <= 16_777_215) {
                if ($min === 1 && $stats->sequenceIntact) {
                    return LaravelColumnType::mediumIncrements;
                }

                return LaravelColumnType::mediumInteger;
            }
            $result = bccomp((string) $max, '4294967295');
            if ($result <= 0) {
                if ($min === 1 && $stats->sequenceIntact) {
                    return LaravelColumnType::increments;
                }

                return LaravelColumnType::integer;
            }
            if ($min === 1 && $stats->sequenceIntact) {
                return LaravelColumnType::bigIncrements;
            }

            return LaravelColumnType::bigInteger;
        }

        if ($min >= -128 && $max <= 127) {
            return LaravelColumnType::tinyInteger;
        }

        if ($min >= -32_768 && $max <= 32_767) {
            return LaravelColumnType::smallInteger;
        }

        if ($min >= -8_388_608 && $max <= 8_388_607) {
            return LaravelColumnType::mediumInteger;
        }

        $result = bccomp((string) $min, '-2147483648');
        if ($result >= 0) {
            $result = bccomp((string) $max, '2147483647');
            if ($result <= 0) {
                return LaravelColumnType::integer;
            }
        }

        return LaravelColumnType::bigInteger;
    }

    private function resolveStringType(ColumnStats $stats): LaravelColumnType
    {
        if ($stats->allJson) {
            return LaravelColumnType::json;
        }
        if ($stats->allIpAddress) {
            return LaravelColumnType::ipAddress;
        }
        if ($stats->allMacAddress) {
            return LaravelColumnType::macAddress;
        }
        if ($stats->allUuid) {
            return LaravelColumnType::uuid;
        }
        if ($stats->allUlid) {
            return LaravelColumnType::ulid;
        }
        if ($stats->allYear) {
            return LaravelColumnType::year;
        }
        if ($stats->allStringLengthsSame && $stats->maxStringLength <= 255) {
            return LaravelColumnType::char;
        }
        if ($stats->maxStringLength <= 255) {
            return LaravelColumnType::tinyText;
        }

        $maximumBytes = $stats->maxStringByteLength();

        if ($maximumBytes <= 65_535) {
            return LaravelColumnType::string;
        }
        if ($stats->maxStringLength <= 65_535) {
            return LaravelColumnType::text;
        }
        if ($stats->maxStringLength <= 16_777_215) {
            return LaravelColumnType::mediumText;
        }

        return LaravelColumnType::longText;
    }
}
