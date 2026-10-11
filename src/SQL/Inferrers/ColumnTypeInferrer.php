<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\SQL\Inferrers;

use ZachWatkins\InferLaravelBlueprint\Parsers;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\ColumnModifier;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\DatabaseType;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\MySQLColumnType;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\SQLiteColumnType;
use ZachWatkins\InferLaravelBlueprint\SQL\Enums\SQLServerColumnType;
use ZachWatkins\InferLaravelBlueprint\SQL\Interfaces\ColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\SQL\Interfaces\SQLColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\SQL\Models\SQLColumn;
use ZachWatkins\InferLaravelBlueprint\SQL\Models\SQLColumnCollection;
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
final class ColumnTypeInferrer implements ColumnTypeInferrerInterface
{
    public function infer(
        iterable $rows,
        DatabaseType $databaseType = DatabaseType::SQLite,
    ): SQLColumnCollectionInterface {
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

        $collection = new SQLColumnCollection;

        foreach ($order as $column) {
            $columnStats = $stats[$column];

            // A row that never mentioned this column is treated exactly like an
            // explicit null value, so parsers do not need to pre-normalize rows to a
            // consistent superset of keys (e.g. optional fields in JSON/XML documents).
            for ($missing = $totalRows - $columnStats->totalCount; $missing > 0; $missing--) {
                $columnStats->record(null);
            }

            $collection->add(new SQLColumn(
                $column,
                $this->resolveType($columnStats, $databaseType),
                $this->resolveModifiers($columnStats, $databaseType),
            ));
        }

        return $collection;
    }

    /**
     * @return array<int, ColumnModifier>
     */
    private function resolveModifiers(ColumnStats $stats, DatabaseType $databaseType): array
    {
        $modifiers = [];

        if ($stats->isNullable()) {
            $modifiers[] = ColumnModifier::Nullable;
        }

        if ($stats->isUnique()) {
            $modifiers[] = ColumnModifier::Unique;
        }

        if ($stats->isUnsigned()) {
            $type = $this->resolveType($stats, $databaseType);
            switch ($databaseType) {
                case DatabaseType::MySQL:
                    if (! \in_array($type, [MySQLColumnType::Boolean->value, MySQLColumnType::Bit->value], true)) {
                        $modifiers[] = ColumnModifier::Unsigned;
                    }
                    break;
                case DatabaseType::SQLServer:
                    if ($type !== SQLServerColumnType::Bit->value) {
                        $modifiers[] = ColumnModifier::Unsigned;
                    }
                    break;
                default:
                    $modifiers[] = ColumnModifier::Unsigned;
                    break;
            }
        }

        if ($stats->isAutoIncrement()) {
            $modifiers[] = ColumnModifier::AutoIncrement;
        }

        return $modifiers;
    }

    private function resolveType(ColumnStats $stats, DatabaseType $databaseType): string
    {
        return match ($databaseType) {
            DatabaseType::SQLite => $this->resolveSQLiteType($stats)->value,
            DatabaseType::MySQL => $this->resolveMySQLType($stats)->value,
            DatabaseType::SQLServer => $this->resolveSQLServerType($stats)->value,
        };
    }

    private function resolveSQLiteType(ColumnStats $stats): SQLiteColumnType
    {
        return match (true) {
            $stats->nonNullSeenCount === 0 => SQLiteColumnType::Text,
            $stats->allBool => SQLiteColumnType::Integer,
            $stats->allInt => SQLiteColumnType::Integer,
            $stats->allNumeric => SQLiteColumnType::Real,
            $stats->allDate => SQLiteColumnType::Text,
            $stats->allDateTime => SQLiteColumnType::Text,
            $stats->allTime => SQLiteColumnType::Text,
            default => SQLiteColumnType::Text,
        };
    }

    private function resolveMySQLType(ColumnStats $stats): MySQLColumnType
    {
        return match (true) {
            $stats->nonNullSeenCount === 0 => MySQLColumnType::Varchar,
            $stats->allBool => MySQLColumnType::Boolean,
            $stats->allInt => $this->resolveMySQLIntegerType($stats),
            $stats->allNumeric => MySQLColumnType::Decimal,
            $stats->allDate => MySQLColumnType::Date,
            $stats->allDateTime => MySQLColumnType::DateTime,
            $stats->allTime => MySQLColumnType::Time,
            $stats->maxStringLength > 255 => MySQLColumnType::Text,
            default => MySQLColumnType::Varchar,
        };
    }

    private function resolveMySQLIntegerType(ColumnStats $stats): MySQLColumnType
    {
        $max = (int) $stats->maxValue;

        if (! $stats->hasNegative) {
            if ($max <= 255) {
                return MySQLColumnType::TinyInt;
            }
            if ($max <= 65_535) {
                return MySQLColumnType::SmallInt;
            }
            if ($max <= 16_777_215) {
                return MySQLColumnType::MediumInt;
            }
            $result = bccomp((string) $max, '4294967295');
            if ($result <= 0) {
                return MySQLColumnType::Int;
            }

            return MySQLColumnType::BigInt;
        }

        $min = (int) \abs($stats->minValue);

        if ($min >= -128 && $max <= 127) {
            return MySQLColumnType::TinyInt;
        }

        if ($min >= -32_768 && $max <= 32_767) {
            return MySQLColumnType::SmallInt;
        }

        if ($min >= -8_388_608 && $max <= 8_388_607) {
            return MySQLColumnType::MediumInt;
        }

        $result = bccomp((string) $min, '-2147483648');
        if ($result >= 0) {
            $result = bccomp((string) $max, '2147483647');
            if ($result <= 0) {
                return MySQLColumnType::Int;
            }
        }

        return MySQLColumnType::BigInt;
    }

    private function resolveSQLServerType(ColumnStats $stats): SQLServerColumnType
    {
        return match (true) {
            $stats->allBool => SQLServerColumnType::Bit,
            $stats->allInt => $this->resolveSQLServerIntegerType($stats),
            $stats->allNumeric => SQLServerColumnType::Decimal,
            $stats->allDate => SQLServerColumnType::Date,
            $stats->allDateTime => SQLServerColumnType::DateTime,
            $stats->allTime => SQLServerColumnType::Time,
            $stats->allStringLengthsSame && $stats->maxStringLength > 0 => SQLServerColumnType::Char,
            default => SQLServerColumnType::Varchar,
        };
    }

    private function resolveSQLServerIntegerType(ColumnStats $stats): SQLServerColumnType
    {
        if ($stats->minValue === 0 && $stats->maxValue === 1) {
            return SQLServerColumnType::Bit;
        }

        // SQL Server has no unsigned integer types; TINYINT is the sole 0-255 exception.
        if (! $stats->hasNegative && $stats->maxValue <= 255) {
            return SQLServerColumnType::TinyInt;
        }

        $min = (int) \abs($stats->minValue);
        $max = (int) $stats->maxValue;

        return match (true) {
            $min >= -32_768 && $max <= 32_767 => SQLServerColumnType::SmallInt,
            $min >= -2_147_483_648 && $max <= 2_147_483_647 => SQLServerColumnType::Int,
            default => SQLServerColumnType::BigInt,
        };
    }
}
