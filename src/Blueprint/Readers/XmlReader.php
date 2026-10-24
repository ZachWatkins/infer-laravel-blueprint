<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Blueprint\Readers;

use ZachWatkins\InferLaravelBlueprint\Blueprint\Inferrers\BlueprintColumnTypeInferrer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnCollectionInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintColumnTypeInferrerInterface;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Interfaces\BlueprintParserInterface;

use function Flow\ETL\Adapter\XML\from_xml;
use function Flow\ETL\DSL\data_frame;

final class XmlReader implements BlueprintParserInterface
{
    public function __construct(
        private readonly string $xmlNodePath = '',
        private readonly BlueprintColumnTypeInferrerInterface $inferrer = new BlueprintColumnTypeInferrer,
    ) {}

    public function parse(
        string $source,
    ): BlueprintColumnCollectionInterface {
        $rawRows = data_frame()->read(from_xml($source, $this->xmlNodePath))->getEachAsArray();

        return $this->inferrer->infer($this->flatten($rawRows));
    }

    /**
     * @param  iterable<array<string, mixed>>  $rawRows
     * @return \Generator<int, array<string, mixed>>
     */
    private function flatten(iterable $rawRows): \Generator
    {
        foreach ($rawRows as $rawRow) {
            $element = $this->parseFragment($this->fragment($rawRow));

            foreach ($this->recordElements($element) as $recordElement) {
                yield $this->flattenElement($recordElement);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rawRow
     */
    private function fragment(array $rawRow): string
    {
        $value = \reset($rawRow);

        if ($value instanceof \DOMDocument) {
            $fragment = $value->saveXML($value->documentElement);

            if ($fragment === false) {
                throw new \RuntimeException('Failed to serialize XML fragment from DOMDocument.');
            }

            return $this->normalizeFragment($fragment);
        }

        $fragment = (string) $value;

        return $this->normalizeFragment($fragment);
    }

    private function parseFragment(string $fragment): \SimpleXMLElement
    {
        $previous = \libxml_use_internal_errors(true);

        try {
            $element = \simplexml_load_string($fragment, \SimpleXMLElement::class, \LIBXML_NONET);

            if ($element instanceof \SimpleXMLElement) {
                return $element;
            }

            $messages = \array_map(
                static fn (\LibXMLError $error): string => \trim($error->message),
                \libxml_get_errors(),
            );

            throw new \RuntimeException(
                'Failed to parse XML fragment: '.\implode('; ', $messages),
            );
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return \Generator<int, \SimpleXMLElement>
     */
    private function recordElements(\SimpleXMLElement $element): \Generator
    {
        if ($this->xmlNodePath === '' && $this->shouldExpandRootChildren($element)) {
            foreach ($element->children() as $child) {
                /** @var \SimpleXMLElement $child */
                yield $child;
            }

            return;
        }

        yield $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenElement(\SimpleXMLElement $element): array
    {
        $flat = [];

        foreach ($element->children() as $child) {
            /** @var \SimpleXMLElement $child */
            $name = $child->getName();
            $text = \trim((string) $child);

            $flat[$name] = $text === '' ? null : $text;
        }

        return $flat;
    }

    private function normalizeFragment(string $fragment): string
    {
        return \preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $fragment) ?? $fragment;
    }

    private function shouldExpandRootChildren(\SimpleXMLElement $element): bool
    {
        foreach ($element->children() as $child) {
            /** @var \SimpleXMLElement $child */
            return \count($child->children()) > 0;
        }

        return false;
    }
}
