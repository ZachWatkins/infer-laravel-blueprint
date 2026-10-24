<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Readers;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Inferrers\BlueprintColumnTypeInferrer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintParserInterface;

use function Flow\ETL\Adapter\JSON\from_json;
use function Flow\ETL\DSL\data_frame;

final class JsonReader implements BlueprintParserInterface
{
    public function __construct(
        private readonly BlueprintColumnTypeInferrerInterface $inferrer = new BlueprintColumnTypeInferrer,
    ) {}

    public function parse(
        string $source,
    ): BlueprintColumnCollectionInterface {
        $rows = data_frame()
            ->read(from_json($source))
            ->getEachAsArray();

        return $this->inferrer->infer($rows);
    }
}
