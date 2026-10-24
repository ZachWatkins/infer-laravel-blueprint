<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\ColumnModifier;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\LaravelColumnType;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnInterface;

/**
 * An immutable representation of a single inferred Blueprint column.
 */
final class BlueprintColumn implements BlueprintColumnInterface
{
    /**
     * @param  array<int, string>  $attributes
     * @param  array<int, ColumnModifier>  $modifiers
     */
    public function __construct(
        private readonly string $name,
        private readonly LaravelColumnType $type,
        private readonly array $attributes = [],
        private readonly array $modifiers = [],
    ) {}

    public function getName(): string
    {
        $sanitized = trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($this->name)) ?? '', '_');
        if (str_ends_with($sanitized, '_id')) {
            $sanitized .= '_original';
        }

        return $sanitized;
    }

    public function getType(): LaravelColumnType
    {
        return $this->type;
    }

    /**
     * @return array<int, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttributes(): bool
    {
        return $this->attributes !== [];
    }

    /**
     * @return array<int, ColumnModifier>
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    public function hasModifiers(): bool
    {
        return $this->modifiers !== [];
    }

    public function hasModifier(ColumnModifier $modifier): bool
    {
        return \in_array($modifier, $this->modifiers, true);
    }
}
