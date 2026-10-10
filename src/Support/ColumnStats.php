<?php

declare(strict_types=1);

namespace ZachWatkins\InferLaravelBlueprint\Support;

/**
 * Mutable accumulator of per-column value statistics, built incrementally while
 * streaming rows so that memory usage stays proportional to the number of distinct
 * columns rather than the number of rows.
 *
 * @internal
 */
final class ColumnStats
{
    public int $totalCount = 0;

    public int $nullCount = 0;

    public int $nonNullSeenCount = 0;

    public int $precision = 0;

    public int $scale = 0;

    public bool $allBool = true;

    public bool $allInt = true;

    public bool $allNumeric = true;

    public bool $allYear = true;

    public bool $allDate = true;

    public bool $allDateTime = true;

    public bool $allTime = true;

    public bool $allJson = true;

    public bool $allIpAddress = true;

    public bool $allMacAddress = true;

    public bool $allUuid = true;

    public bool $allUlid = true;

    public bool $hasNegative = false;

    public int|float $minValue = 0;

    public int|float $maxValue = 0;

    public int $maxStringLength = 0;

    public bool $allStringLengthsSame = true;

    public bool $sequenceIntact = true;

    /**
     * @var array<string, int>
     */
    private array $seenValues = [];

    private bool $sequenceBaseSet = false;

    private int $sequenceExpectedNext = 0;

    private ?int $firstStringLength = null;

    public function record(mixed $value): void
    {
        $this->totalCount++;

        if ($value === null) {
            $this->nullCount++;
            $this->sequenceIntact = false;

            return;
        }

        $this->nonNullSeenCount++;

        if ($value instanceof \DateTimeInterface) {
            $this->recordDateTime($value);

            return;
        }

        if (! \is_scalar($value)) {
            $this->recordUnsupported($value);

            return;
        }

        $this->recordScalar($value);
    }

    /**
     * @return array<int, string>
     */
    public function getSeenValues(): array
    {
        return array_keys($this->seenValues);
    }

    public function isNullable(): bool
    {
        return $this->nullCount > 0;
    }

    public function isUnique(): bool
    {
        return $this->nonNullSeenCount > 0 && \count($this->seenValues) === $this->nonNullSeenCount;
    }

    public function isUnsigned(): bool
    {
        return $this->nonNullSeenCount > 0 && ($this->allInt || $this->allNumeric) && ! $this->hasNegative;
    }

    public function isAutoIncrement(): bool
    {
        return $this->nullCount === 0
            && $this->nonNullSeenCount > 1
            && $this->allInt
            && $this->sequenceIntact
            && $this->isUnique()
            && $this->isUnsigned();
    }

    public function containsMultiByteEncoding(): bool
    {
        foreach (\array_keys($this->seenValues) as $value) {
            if (\mb_strlen($value, '8bit') !== \strlen($value)) {
                return true;
            }
        }

        return false;
    }

    public function maxStringByteLength(): int
    {
        if ($this->containsMultiByteEncoding()) {
            $max = 0;
            foreach (\array_keys($this->seenValues) as $value) {
                $max = \max($max, \mb_strlen($value, '8bit'));
            }

            return $max;
        }

        return $this->maxStringLength;
    }

    private function recordDateTime(\DateTimeInterface $value): void
    {
        $stringValue = $value->format('Y-m-d H:i:s.u');
        $this->trackSeen($stringValue);
        $this->maxStringLength = \max($this->maxStringLength, \strlen($stringValue));

        $this->allBool = false;
        $this->allInt = false;
        $this->allNumeric = false;
        $this->allJson = false;
        $this->sequenceIntact = false;
        $this->allJson = false;
        $this->allIpAddress = false;
        $this->allMacAddress = false;
        $this->allUuid = false;
        $this->allUlid = false;

        if ($value->format('H:i:s') !== '00:00:00') {
            $this->allDate = false;
        } else {
            $this->allTime = false;
        }
    }

    private function recordUnsupported(mixed $value): void
    {
        $this->trackSeen('object:'.\spl_object_id((object) $value));

        $this->allBool = false;
        $this->allInt = false;
        $this->allNumeric = false;
        $this->allDate = false;
        $this->allDateTime = false;
        $this->allTime = false;
        $this->sequenceIntact = false;
        $this->allJson = false;
        $this->allIpAddress = false;
        $this->allMacAddress = false;
        $this->allUuid = false;
        $this->allUlid = false;
        $this->allYear = false;
    }

    private function recordScalar(bool|int|float|string $value): void
    {
        $stringValue = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $this->trackSeen($stringValue);
        $this->maxStringLength = \max($this->maxStringLength, \strlen($stringValue));

        if (\is_string($value)) {
            $stringLength = \strlen($value);

            if ($this->firstStringLength === null) {
                $this->firstStringLength = $stringLength;
            } elseif ($this->firstStringLength !== $stringLength) {
                $this->allStringLengthsSame = false;
            }
            if (! self::isJsonLike($value)) {
                $this->allJson = false;
            }
            if (! self::isIpAddressLike($value)) {
                $this->allIpAddress = false;
            }
            if (! self::isMacAddressLike($value)) {
                $this->allMacAddress = false;
            }
            if (! self::isUuidLike($value)) {
                $this->allUuid = false;
            }
            if (! self::isUlidLike($value)) {
                $this->allUlid = false;
            }
            if ($stringLength !== 4 || ! \is_numeric($value) || (int) $value < 1901 || (int) $value > 2155) {
                $this->allYear = false;
            }
        }

        if (! self::isBoolLike($value)) {
            $this->allBool = false;
        }

        if (! self::isDateLike($value)) {
            $this->allDate = false;
        }

        if (! self::isDateTimeLike($value)) {
            $this->allDateTime = false;
        }

        if (! self::isTimeLike($value)) {
            $this->allTime = false;
        }

        $isInt = self::isIntLike($value);
        $isFloat = ! $isInt && self::isFloatLike($value);

        if (! $isInt && ! $isFloat) {
            $this->allInt = false;
            $this->allNumeric = false;
            $this->sequenceIntact = false;

            return;
        }

        if (! $isInt) {
            $this->allInt = false;
        }

        if ($isFloat) {
            $precision = 0;
            $scale = 0;
            $parts = \explode('.', (string) $value);
            $scale = isset($parts[1]) ? \strlen($parts[1]) : 0;
            $precision = \strlen($parts[0]) + $scale;
            $this->precision = \max($this->precision, $precision);
            $this->scale = \max($this->scale, $scale);
        }

        $numericValue = $isInt ? (int) $value : (float) $value;

        if ($numericValue < 0) {
            $this->hasNegative = true;
            $this->allYear = false;
        }

        if (! $isInt || $value < 1901 || $value > 2155) {
            $this->allYear = false;
        }

        if ($this->nonNullSeenCount === 1) {
            $this->minValue = $numericValue;
            $this->maxValue = $numericValue;
        } else {
            $this->minValue = \min($this->minValue, $numericValue);
            $this->maxValue = \max($this->maxValue, $numericValue);
        }

        if ($isInt) {
            $this->updateSequence($numericValue);
        } else {
            $this->sequenceIntact = false;
        }
    }

    private function updateSequence(int $value): void
    {
        if (! $this->sequenceBaseSet) {
            $this->sequenceBaseSet = true;
            $this->sequenceExpectedNext = $value + 1;

            return;
        }

        if ($value === $this->sequenceExpectedNext) {
            $this->sequenceExpectedNext++;

            return;
        }

        $this->sequenceIntact = false;
    }

    private function trackSeen(string $key): void
    {
        $this->seenValues[$key] = ($this->seenValues[$key] ?? 0) + 1;
    }

    private static function isBoolLike(bool|int|float|string $value): bool
    {
        if (\is_bool($value)) {
            return true;
        }

        return \is_string($value) && \in_array(\strtolower(\trim($value)), ['true', 'false'], true);
    }

    private static function isIntLike(bool|int|float|string $value): bool
    {
        if (\is_int($value)) {
            return true;
        }

        if (! \is_string($value)) {
            return false;
        }

        $trimmed = \trim($value);

        if (\preg_match('/^-?\d+$/', $trimmed) !== 1) {
            return false;
        }

        $digits = \ltrim($trimmed, '-');

        // A "significant" leading zero (e.g. a zip code of "02134") means the value is
        // an opaque string/code, not a true integer, so it must not lose that digit.
        return ! (\strlen($digits) > 1 && $digits[0] === '0');
    }

    private static function isFloatLike(bool|int|float|string $value): bool
    {
        if (\is_float($value)) {
            return true;
        }

        if (! \is_string($value)) {
            return false;
        }

        $trimmed = \trim($value);

        // Require an explicit decimal point or exponent so plain digit strings
        // (already rejected as int-like due to a significant leading zero) are not
        // misclassified as floats.
        return $trimmed !== '' && \preg_match('/^-?\d+(?:\.\d+(?:[eE][+-]?\d+)?|[eE][+-]?\d+)$/', $trimmed) === 1;
    }

    private static function isDateLike(bool|int|float|string $value): bool
    {
        if (! \is_string($value) || \preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', \trim($value), $matches) !== 1) {
            return false;
        }

        return \checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private static function isDateTimeLike(bool|int|float|string $value): bool
    {
        if (! \is_string($value)) {
            return false;
        }

        $pattern = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/';

        if (\preg_match($pattern, \trim($value), $matches) !== 1) {
            return false;
        }

        return \checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
            && (int) $matches[4] <= 23
            && (int) $matches[5] <= 59
            && (int) $matches[6] <= 59;
    }

    private static function isTimeLike(bool|int|float|string $value): bool
    {
        if (! \is_string($value) || \preg_match('/^(\d{2}):(\d{2}):(\d{2})(\.\d+)?$/', \trim($value), $matches) !== 1) {
            return false;
        }

        return (int) $matches[1] <= 23 && (int) $matches[2] <= 59 && (int) $matches[3] <= 59;
    }

    private static function isJsonLike(string $value): bool
    {
        $trimmed = \trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (\strlen($trimmed) < 2) {
            return false;
        }

        // Silently try to parse JSON and do not update the global error state.
        // If the string begins and ends with certain characters, it might be JSON.
        try {
            \json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }

    private static function isIpAddressLike(string $value): bool
    {
        return \filter_var($value, \FILTER_VALIDATE_IP) !== false;
    }

    private static function isMacAddressLike(string $value): bool
    {
        return \preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value) === 1;
    }

    private static function isUuidLike(string $value): bool
    {
        return \preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }

    private static function isUlidLike(string $value): bool
    {
        return \preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }
}
