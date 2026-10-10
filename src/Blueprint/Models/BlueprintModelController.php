<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Models;

class BlueprintModelController
{
    private const METHODS = ['index', 'create', 'read', 'update', 'delete'];

    /** @var array<string, mixed> */
    public readonly array $methods;

    /**
     * @param  array<int, string>  $methodsParam
     */
    public function __construct(
        protected BlueprintModel $model,
        array $methodsParam,
        protected string $view = 'blade',
        protected bool $resource = false,
    ) {
        $invalidMethods = array_diff($methodsParam, self::METHODS);
        if (! empty($invalidMethods)) {
            throw new \RuntimeException(
                'Invalid controller methods: '.implode(', ', $invalidMethods).'. Accepts: '.implode(', ', self::METHODS)
            );
        }

        $this->methods = $this->assembleMethods($methodsParam);
    }

    /**
     * @param  array<int, string>  $methodsParam
     * @return array<string, mixed>
     */
    protected function assembleMethods(array $methodsParam): array
    {
        $tree = [];
        $renderKey = match ($this->view) {
            'blade' => 'render',
            'inertia' => 'inertia',
            default => 'render',
        };
        foreach ($methodsParam as $method) {
            switch ($method) {
                case 'resource':
                    if (! isset($tree['resource'])) {
                        $tree['resource'] = [];
                    }
                    $tree['resource'][] = true;
                    break;
                case 'resource:api':
                    if (! isset($tree['resource'])) {
                        $tree['resource'] = [];
                    }
                    $tree['resource'][] = 'api';
                    break;
                case 'resource:web':
                    if (! isset($tree['resource'])) {
                        $tree['resource'] = [];
                    }
                    $tree['resource'][] = 'web';
                    break;
                case 'index':
                    $renderValue = match ($this->view) {
                        'inertia' => sprintf('%s/Index with:%s,%s', $this->model->name, $this->model->tableNameSingular, $this->model->tableNamePlural),
                        default => sprintf('%s.index with:%s', $this->model->tableNamePlural, $this->model->tableNamePlural),
                    };
                    $tree['index'] = [
                        'query' => sprintf('all:%s', $this->model->tableNamePlural),
                        $renderKey => $renderValue,
                    ];
                    break;
                case 'create':
                    $renderValue = match ($this->view) {
                        'inertia' => sprintf('%s/Create with:%s', $this->model->name, $this->model->tableNameSingular),
                        default => sprintf('%s.create with:%s', $this->model->tableNamePlural, $this->model->tableNameSingular),
                    };
                    $tree['create'] = [
                        $renderKey => $renderValue,
                    ];
                    break;
                case 'store':
                    $tree['store'] = [
                        'validate' => implode(', ', $this->model->columnNames()),
                        'save' => $this->model->tableNameSingular,
                        'redirect' => sprintf('%s.index', $this->model->tableNamePlural),
                    ];
                    break;
                case 'show':
                    $renderValue = match ($this->view) {
                        'inertia' => sprintf('%s/Show with:%s', $this->model->name, $this->model->tableNameSingular),
                        default => sprintf('%s.show with:%s', $this->model->tableNamePlural, $this->model->tableNameSingular),
                    };
                    $tree['show'] = [
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        $renderKey => $renderValue,
                    ];
                    break;
                case 'edit':
                    $renderValue = match ($this->view) {
                        'inertia' => sprintf('%s/Edit with:%s', $this->model->name, $this->model->tableNameSingular),
                        default => sprintf('%s.edit with:%s', $this->model->tableNamePlural, $this->model->tableNameSingular),
                    };
                    $tree['edit'] = [
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        $renderKey => $renderValue,
                    ];
                    break;
                case 'update':
                    $tree['update'] = [
                        'validate' => implode(', ', $this->model->columnNames()),
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        'save' => $this->model->tableNameSingular,
                        'redirect' => sprintf('%s.index', $this->model->tableNamePlural),
                    ];
                    break;
                case 'destroy':
                    $tree['destroy'] = [
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        'delete' => $this->model->tableNameSingular,
                    ];
                    break;
                case 'api.index':
                    $tree['api.index'] = [
                        'query' => 'all',
                        'resource' => sprintf('collection:%s', $this->model->tableNamePlural),
                    ];
                    break;
                case 'api.store':
                    $tree['api.store'] = [
                        'validate' => implode(', ', $this->model->columnNames()),
                        'save' => $this->model->tableNameSingular,
                        'resource' => $this->model->tableNameSingular,
                    ];
                    break;
                case 'api.show':
                    $tree['api.show'] = [
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        'resource' => $this->model->tableNameSingular,
                    ];
                    break;
                case 'api.update':
                    $tree['api.update'] = [
                        'validate' => implode(', ', $this->model->columnNames()),
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        'save' => $this->model->tableNameSingular,
                        'resource' => $this->model->tableNameSingular,
                    ];
                    break;
                case 'api.destroy':
                    $tree['api.destroy'] = [
                        'find' => sprintf('%s.id', $this->model->tableNameSingular),
                        'delete' => $this->model->tableNameSingular,
                        'redirect' => sprintf('%s.index', $this->model->tableNamePlural),
                    ];
                    break;
                case 'invokable':
                    $tree['invokable'] = true;
                    break;
            }
        }

        return $tree;
    }
}
