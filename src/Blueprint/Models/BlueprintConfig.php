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
     * @param  array<int, mixed>  $models
     * @param  array<int, mixed>  $resources
     * @param  array<int, mixed>  $methods
     */
    public function __construct(
        array $models = [],
        array $resources = [],
        array $methods = [],
        bool $seeders = false,
        BlueprintConfigView|string|null $view = BlueprintConfigView::Blade,
    ) {
        $this->models = $this->resolveModels($models);
        $this->resources = $this->resolveResources($resources);
        $this->methods = $this->resolveMethods($methods);
        $this->seeders = $seeders;
        $this->view = $this->resolveView($view ?? BlueprintConfigView::Blade);
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

    /**
     * @param  array<int, mixed>  $models
     * @return array<int, BlueprintModel>
     */
    private function resolveModels(array $models): array
    {
        $resolved = [];
        foreach ($models as $model) {
            if ($model instanceof BlueprintModel) {
                $resolved[] = $model;
            } else {
                throw new \RuntimeException(\sprintf(
                    "Model '%s' is not a valid BlueprintModel instance.",
                    \is_object($model) ? \get_class($model) : \gettype($model)
                ));
            }
        }

        return $resolved;
    }

    private function resolveView(BlueprintConfigView|string $view): BlueprintConfigView
    {
        if ($view instanceof BlueprintConfigView) {
            return $view;
        }

        $resolved = BlueprintConfigView::tryFrom($view);
        if ($resolved === null) {
            throw new \RuntimeException(\sprintf(
                "View '%s' is not a valid option. Accepts: %s.",
                $view,
                \implode(', ', \array_column(BlueprintConfigView::cases(), 'value'))
            ));
        }

        return $resolved;
    }

    /**
     * @param  array<int, mixed>  $methods
     * @return array<int, string>
     */
    private function resolveMethods(array $methods): array
    {
        $resolved = [];
        foreach (array_unique(array_filter($methods)) as $method) {
            if (\is_string($method)) {
                $resolved[] = $method;
            } else {
                throw new \RuntimeException(\sprintf(
                    "Method '%s' (type: '%s') is not a valid option.",
                    \is_object($method) ? \get_class($method) : \gettype($method),
                    \gettype($method)
                ));
            }
        }

        return $resolved;
    }

    /**
     * Resolves an array of resource strings, ensuring each item is a valid string.
     *
     * @param  array<int, mixed>  $resource
     * @return array<int, BlueprintConfigResource>
     */
    private function resolveResources(array $resource): array
    {
        $resolved = [];
        foreach (array_unique(array_filter($resource), SORT_REGULAR) as $item) {
            if ($item instanceof BlueprintConfigResource) {
                $resolved[] = $item;
            } else {
                $item = BlueprintConfigResource::tryFrom($item);
                if ($item instanceof BlueprintConfigResource) {
                    $resolved[] = $item;
                } else {
                    throw new \RuntimeException(\sprintf(
                        "Resource '%s' (type: '%s') is not a valid option.",
                        \is_object($item) ? \get_class($item) : \gettype($item),
                        \gettype($item)
                    ));
                }
            }
        }

        return $resolved;
    }
}
