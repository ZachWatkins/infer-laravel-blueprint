<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Enums;

/**
 * Controller resource types supported by the Laravel Shift Blueprint YAML file.
 */
enum BlueprintConfigResource: string
{
    case Web = 'web';
    case Api = 'api';
    case Index = 'index';
    case Create = 'create';
    case Store = 'store';
    case Edit = 'edit';
    case Update = 'update';
    case Show = 'show';
    case Destroy = 'destroy';
    case ApiIndex = 'api.index';
    case ApiStore = 'api.store';
    case ApiUpdate = 'api.update';
    case ApiShow = 'api.show';
    case ApiDestroy = 'api.destroy';

    public static function isApiMethod(string $resource): bool
    {
        return \in_array($resource, [
            self::ApiIndex->value,
            self::ApiStore->value,
            self::ApiUpdate->value,
            self::ApiShow->value,
            self::ApiDestroy->value,
        ], true);
    }

    public static function isWebMethod(string $resource): bool
    {
        return \in_array($resource, [
            self::Index->value,
            self::Create->value,
            self::Store->value,
            self::Edit->value,
            self::Update->value,
            self::Show->value,
            self::Destroy->value,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function webMethods(): array
    {
        return [
            self::Index->value,
            self::Create->value,
            self::Store->value,
            self::Edit->value,
            self::Update->value,
            self::Show->value,
            self::Destroy->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function apiMethods(): array
    {
        return [
            self::ApiIndex->value,
            self::ApiStore->value,
            self::ApiUpdate->value,
            self::ApiShow->value,
            self::ApiDestroy->value,
        ];
    }
}
