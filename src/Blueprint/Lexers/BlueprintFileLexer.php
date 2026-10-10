<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Lexers;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigResource;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Enums\BlueprintConfigView;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintModel;

class BlueprintFileLexer
{
    public function toString(BlueprintConfig $config): string
    {
        $tree = $this->toTree($config);
        $output = '';
        if ($tree['models']) {
            $output .= "models:\n";
            foreach ($tree['models'] as $modelName => $columns) {
                $output .= "  {$modelName}:\n";
                foreach ($columns as $columnName => $columnValue) {
                    $output .= "    {$columnName}: {$columnValue}\n";
                }
            }
        }
        if ($tree['controllers']) {
            if ($tree['models']) {
                $output .= "\n";
            }
            $output .= "controllers:\n";
            foreach ($tree['controllers'] as $modelName => $methods) {
                $output .= "  {$modelName}:\n";
                foreach ($methods as $method => $actions) {
                    if (is_array($actions)) {
                        $output .= "    {$method}:\n";
                        foreach ($actions as $action => $value) {
                            $output .= "      {$action}: {$value}\n";
                        }
                    } elseif (is_string($actions)) {
                        $output .= "    {$method}: {$actions}\n";
                    }
                }
            }
        }
        if ($tree['seeders']) {
            if ($tree['controllers']) {
                $output .= "\n";
            }
            $output .= 'seeders: '.\implode(', ', $tree['seeders']);
            $output .= "\n";
        }

        return $output;
    }

    /**
     * @return array{
     *     models: array<string, array<string, string>>,
     *     controllers: array<string, array<string, array<string, mixed>|string>>,
     *     seeders: list<string>
     * }
     */
    public function toTree(BlueprintConfig $config): array
    {
        $tree = [
            'models' => [],
            'controllers' => [],
            'seeders' => [],
        ];
        foreach ($config->getModels() as $model) {
            $tree['models'][$model->name] = [];
            foreach ($model->columns as $column) {
                // Laravel Shift Blueprint is intentionally designed to only properly resolve model primary keys using the id column.
                if ($column->getName() === 'id') {
                    $values = ['id'];
                } else {
                    $values = [$column->getType()->value];
                    if ($column->hasAttributes()) {
                        $values[0] .= ':'.implode(',', $column->getAttributes());
                    }
                    if ($column->hasModifiers()) {
                        foreach ($column->getModifiers() as $modifier) {
                            $values[] = $modifier->value;
                        }
                    }
                }
                $tree['models'][$model->name][$column->getName()] = implode(' ', $values);
            }
            $controller = $this->getControllerTree($model, $config);
            if (! empty($controller)) {
                $tree['controllers'][$model->name] = $controller;
            }
        }
        if ($config->seeders) {
            foreach ($config->getModels() as $model) {
                $tree['seeders'][] = $model->name;
            }
        }

        return $tree;
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getControllerTree(BlueprintModel $model, BlueprintConfig $config): array
    {
        if ($config->view === BlueprintConfigView::Inertia) {
            return $this->getInertiaControllerTree($model, $config);
        }
        $template = [
            'index' => [
                'query' => 'all:[plural]',
                'render' => '[singular].index with:[plural]',
            ],
            'create' => [
                'render' => '[singular].create',
            ],
            'store' => [
                'validate' => '[columns]',
                'save' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'show' => [
                'render' => '[singular].show with:[singular]',
            ],
            'edit' => [
                'render' => '[singular].edit with:[singular]',
            ],
            'update' => [
                'validate' => '[columns]',
                'update' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'destroy' => [
                'delete' => '[singular]',
                'redirect' => '[plural].index',
            ],
            'api.index' => [
                'query' => 'all:[plural]',
                'resource' => 'collection:[plural]',
            ],
            'api.store' => [
                'validate' => '[columns]',
                'save' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.show' => [
                'resource' => '[singular]',
            ],
            'api.update' => [
                'validate' => '[columns]',
                'update' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.destroy' => [
                'delete' => '[singular]',
                'respond' => 204,
            ],
        ];
        $result = [];
        if ($config->resources) {
            $resources = \implode(', ', \array_filter(\array_map(fn ($resource) => $resource->value, $config->resources)));
            if ($resources) {
                $result['resource'] = $resources;
            }
        }
        foreach (\array_keys($template) as $templateMethod) {
            if (\in_array($templateMethod, $config->methods)) {
                $result[$templateMethod] = $template[$templateMethod];
            }
        }
        $result = $this->resolveConflictingControllerMethods($result);
        $result = $this->resolveModelControllerPlaceholders($result, $model);
        $customMethods = \array_diff($config->methods, BlueprintConfigResource::webMethods(), BlueprintConfigResource::apiMethods());
        foreach ($customMethods as $method) {
            if (! isset($result[$method])) {
                $result[$method] = [
                    '# <action>' => '<parameters>',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getInertiaControllerTree(BlueprintModel $model, BlueprintConfig $config): array
    {
        $template = [
            'resource' => [],
            'index' => [
                'query' => 'all:[plural]',
                'inertia' => '[model]/Index with:[plural]',
            ],
            'create' => [
                'inertia' => '[model]/Create',
            ],
            'store' => [
                'validate' => '[columns]',
                'save' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'show' => [
                'inertia' => '[model]/Show with:[singular]',
            ],
            'edit' => [
                'inertia' => '[model]/Edit with:[singular]',
            ],
            'update' => [
                'validate' => '[columns]',
                'update' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'destroy' => [
                'delete' => '[singular]',
                'redirect' => '[plural].index',
            ],
            'api.index' => [
                'query' => 'all:[plural]',
                'resource' => 'collection:[plural]',
            ],
            'api.store' => [
                'validate' => '[columns]',
                'save' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.show' => [
                'resource' => '[singular]',
            ],
            'api.update' => [
                'validate' => '[columns]',
                'update' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.destroy' => [
                'delete' => '[singular]',
                'respond' => 204,
            ],
        ];
        $result = [];
        if ($config->resources) {
            // Extract web-related resource declarations and move them to the controllers, since Laravel Shift Blueprint does not resolve these shorthands for Inertia views at this time.
            $filteredResources = array_flip(array_map(fn ($resource) => $resource->value, $config->resources));
            $webResourceMethods = [];
            if (isset($filteredResources['web'])) {
                $webResourceMethods = array_flip(BlueprintConfigResource::webMethods());
                unset($filteredResources['web']);
            }
            // Look at each resource declaration and move web methods to extractedResourceMethods.
            foreach (BlueprintConfigResource::webMethods() as $method) {
                if (isset($filteredResources[$method])) {
                    if (! isset($webResourceMethods[$method])) {
                        $webResourceMethods[$method] = true;
                    }
                    unset($filteredResources[$method]);
                }
            }
            if (! empty($filteredResources)) {
                $result['resource'] = implode(', ', array_keys($filteredResources));
            }
            if (! empty($webResourceMethods)) {
                foreach (\array_keys($template) as $method) {
                    if (isset($webResourceMethods[$method])) {
                        $result[$method] = $template[$method];
                    } else {
                        // To preserve the declared order of methods for future assignment, set a null value and remove it later.
                        $result[$method] = null;
                    }
                }
            }
        }
        foreach ($config->methods as $method) {
            // Apply web resource methods declared in $config->methods.
            if (BlueprintConfigResource::isWebMethod($method) && isset($result[$method]) && $result[$method] === null) {
                $result[$method] = $template[$method];
            }
            // Apply API resource methods declared in $config->methods.
            if (BlueprintConfigResource::isApiMethod($method) && isset($result[$method]) && $result[$method] === null) {
                $result[$method] = $template[$method];
            }
        }
        foreach ($result as $method => $actions) {
            if ($actions === null) {
                unset($result[$method]);
            }
        }
        $result = $this->resolveConflictingControllerMethods($result);
        $result = $this->resolveModelControllerPlaceholders($result, $model);
        $customMethods = \array_diff($config->methods, BlueprintConfigResource::webMethods(), BlueprintConfigResource::apiMethods());
        foreach ($customMethods as $method) {
            if (! isset($result[$method])) {
                $result[$method] = [
                    '# <action>' => '<parameters>',
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>|string|null>  $tree
     * @return array<string, array<string, mixed>|string|null>
     */
    private function resolveModelControllerPlaceholders(array $tree, BlueprintModel $model): array
    {
        foreach ($tree as $method => $actions) {
            if (is_array($actions)) {
                foreach ($actions as $action => $parameters) {
                    if (is_string($parameters)) {
                        $resolved = $this->resolveModelControllerPlaceholder($parameters, $model);
                        if ($resolved !== $tree[$method][$action]) {
                            $tree[$method][$action] = $resolved;
                        }
                    } elseif (is_array($parameters)) {
                        foreach ($parameters as $key => $value) {
                            if (is_string($value)) {
                                $resolved = $this->resolveModelControllerPlaceholder($value, $model);
                                if ($resolved !== $parameters[$key]) {
                                    $parameters[$key] = $resolved;
                                }
                            }
                        }
                        $tree[$method][$action] = $parameters;
                    }
                }
            }
        }

        return $tree;
    }

    private function resolveModelControllerPlaceholder(string $parameters, BlueprintModel $model): string
    {
        if (str_contains($parameters, '[singular]')) {
            $parameters = str_replace('[singular]', $model->tableNameSingular, $parameters);
        }
        if (str_contains($parameters, '[plural]')) {
            $parameters = str_replace('[plural]', $model->tableNamePlural, $parameters);
        }
        if (str_contains($parameters, '[model]')) {
            $parameters = str_replace('[model]', $model->name, $parameters);
        }
        if (str_contains($parameters, '[columns]')) {
            $parameters = str_replace('[columns]', implode(', ', $model->columnNames()), $parameters);
        }

        return $parameters;
    }

    /**
     * @param  array<string, array<string, mixed>|string|null>  $tree
     * @return array<string, array<string, mixed>|string|null>
     */
    private function resolveConflictingControllerMethods(array $tree): array
    {
        foreach ($tree as $method => $actions) {
            if (\str_starts_with($method, 'api.')) {
                $baseMethod = substr($method, 4);
                if (isset($tree[$baseMethod])) {
                    unset($tree[$method]);
                } else {
                    $tree[$baseMethod] = $actions;
                    unset($tree[$method]);
                }
            }
        }

        return $tree;
    }
}
