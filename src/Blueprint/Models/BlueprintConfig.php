<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigResource;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigView;

/**
 * @property array<int, BlueprintModel> $models
 * @property array<int, BlueprintConfigResource> $resources
 * @property array<int, string> $methods
 * @property bool $seeders
 * @property BlueprintConfigView $view
 */
class BlueprintConfig
{
    /** @var array<int, BlueprintModel> */
    private array $models;

    /** @var array<int, BlueprintConfigResource> */
    public readonly array $resources;

    /** @var array<int, string> */
    public readonly array $methods;

    public readonly bool $seeders;

    public readonly BlueprintConfigView $view;

    /**
     * @param  array<int, BlueprintModel>  $models
     * @param  array<int, BlueprintConfigResource|string>  $resources
     * @param  array<int, string>  $methods
     */
    public function __construct(
        array $models = [],
        array $resources = [],
        array $methods = [],
        bool $seeders = false,
        BlueprintConfigView|string|null $view = BlueprintConfigView::Blade,
    ) {
        $this->models = \array_values(\array_map(
            static fn (BlueprintModel $model): BlueprintModel => $model,
            $models
        ));
        $this->resources = \array_values(\array_unique(\array_map(
            static fn (BlueprintConfigResource|string $resource): BlueprintConfigResource => $resource instanceof BlueprintConfigResource
                ? $resource
                : BlueprintConfigResource::from($resource),
            $resources,
        ), SORT_REGULAR));
        $this->methods = \array_values(\array_unique(\array_map(
            static fn (string $method): string => $method,
            $methods
        )));
        $this->seeders = $seeders;
        $this->view = $view instanceof BlueprintConfigView
            ? $view
            : BlueprintConfigView::from($view ?? BlueprintConfigView::Blade->value);
    }

    public function addModel(BlueprintModel $model): void
    {
        $this->models[] = $model;
    }

    /**
     * @return array<int, BlueprintModel>
     */
    public function getModels(): array
    {
        return $this->models;
    }
}
