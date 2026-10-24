<?php

/**
 * Generates test fixture schema files using the source data.
 */

namespace Tests\Blueprint\Fixtures\Generators;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintReaderInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Lexers\BlueprintFileLexer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintModel;

class TestBlueprintGenerator
{
    /**
     * Generates a test blueprint file from the given data file and configuration.
     *
     * @param  string  $dataFilePath  The path to the source data file.
     * @param  string  $blueprintFilePath  The path to the output blueprint file.
     * @param  BlueprintConfig  $config  The configuration options for generating the blueprint file.
     */
    public function generate(string $dataFilePath, string $blueprintFilePath, BlueprintConfig $config = new BlueprintConfig): void
    {
        $reader = $this->resolveReader(basename($dataFilePath));
        $columns = $reader->read($dataFilePath);
        $model = new BlueprintModel('Model', $columns);
        $config->addModel($model);
        $lexer = new BlueprintFileLexer;
        $output = $lexer->toString($config);

        file_put_contents($blueprintFilePath, $output);
    }

    private function resolveReader(string $dataFileName): BlueprintReaderInterface
    {
        $extension = pathinfo($dataFileName, PATHINFO_EXTENSION);
        if (! $extension) {
            throw new \InvalidArgumentException("Unable to determine the file extension for $dataFileName");
        }
        $className = '\\ZachWatkins\\InferLaravelBlueprint\\Blueprint\\Readers\\'.ucfirst($extension).'Reader';
        if (! class_exists($className)) {
            throw new \InvalidArgumentException("Reader class $className does not exist for file extension $extension");
        }
        $reader = new $className;
        if (! $reader instanceof BlueprintReaderInterface) {
            throw new \InvalidArgumentException("Reader class $className must implement BlueprintReaderInterface");
        }

        return $reader;
    }
}
