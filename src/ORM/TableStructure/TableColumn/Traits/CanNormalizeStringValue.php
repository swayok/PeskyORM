<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanNormalizeStringValue
{
    use CanTrimStringValue;
    use CanLowercaseStringValue;
    use CanConvertEmptyStringValueToNull;

    /**
     * Apply string value normalizations based on Column options like
     * shouldTrimValues(), shouldConvertEmptyStringToNull(), shouldLowercaseValues()
     */
    protected function normalizeStringValue(string $value, bool $isFromDb): ?string
    {
        if ($this->shouldTrimStringValues($isFromDb)) {
            $value = trim($value);
        }
        if ($value === '' && $this->shouldConvertEmptyStringToNull($isFromDb)) {
            return null;
        }
        if ($this->shouldLowercaseValues($isFromDb)) {
            $value = mb_strtolower($value);
        }
        return $value;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = $value->__toString();
        }
        if (is_string($value)) {
            return $this->normalizeStringValue($value, $isFromDb);
        }
        return $value;
    }
}