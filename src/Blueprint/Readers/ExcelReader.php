<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Readers;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Inferrers\BlueprintColumnTypeInferrer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\DataReaderInterface;

use function Flow\ETL\Adapter\Excel\DSL\from_excel;
use function Flow\ETL\DSL\data_frame;

final class ExcelReader implements DataReaderInterface
{
    public function __construct(
        private readonly BlueprintColumnTypeInferrerInterface $inferrer = new BlueprintColumnTypeInferrer,
    ) {}

    public function read(
        string $source
    ): BlueprintColumnCollectionInterface {
        $rows = data_frame()->read(from_excel($source))->getEachAsArray();

        return $this->inferrer->infer($rows);
    }
}
