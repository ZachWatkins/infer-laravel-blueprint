<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Enums;

/**
 * Modifiers that describe additional constraints or attributes of a Blueprint column,
 * evaluated across every value observed for that column.
 */
enum ColumnModifier: string
{
    case Nullable = 'nullable';
    case Unique = 'unique';
    case Unsigned = 'unsigned';
    case AutoIncrement = 'auto_increment';
}
