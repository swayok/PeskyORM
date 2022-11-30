<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;

/**
 * This column should be a base for all columns that
 * store values that are based on strings in DB:
 * - text
 * - date, time, timestamps (except unix timestamp which is integer)
 * - timezone offset
 * - ip address
 * - other string-based types
 * All these columns represented as strings, some in specific format,
 * but in PHP all values will be strings.
 */
class StringColumn extends TableColumnAbstract
{
    use CanBeUnique;

    protected bool $trimValues = false;
    protected bool $lowercaseValues = false;
    /**
     * Values:
     * = null - autodetect depending on $this->isNullableValues() value:
     *          - convert '' to null if null value allowed;
     *          - leave '' as is if null not allowed.
     * = true - always convert '' to null;
     * = false - always leave '' as is;
     */
    protected ?bool $convertEmptyStringValueToNull = null;

    public function getDataType(): string
    {
        return TableColumnDataType::STRING;
    }

    public function trimsValues(): static
    {
        $this->trimValues = true;
        return $this;
    }

    protected function shouldTrimStringValues(): bool
    {
        return $this->trimValues;
    }

    protected function allowsNullValues(): static
    {
        $this->valueCanBeNull = true;
        return $this;
    }

    public function lowercasesValues(): static
    {
        $this->lowercaseValues = true;
        return $this;
    }

    protected function shouldLowercaseValues(): bool
    {
        return $this->lowercaseValues;
    }

    protected function shouldConvertEmptyStringToNull(): bool
    {
        return $this->convertEmptyStringValueToNull ?? $this->isNullableValues();
    }

    public function convertsEmptyStringValuesToNull(): static
    {
        $this->convertEmptyStringValueToNull = true;
        return $this;
    }

    protected function validateValueDataType(mixed $normalizedValue, bool $isForCondition): array
    {
        if (!is_string($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_INTEGER
                )
            ];
        }
        return [];
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value)) {
            return $this->normalizeStringValue($value, $isFromDb);
        }
        return $value;
    }

    /**
     * Apply string value normalizations based on Column options like
     * shouldTrimValues(), shouldConvertEmptyStringToNull(), shouldLowercaseValues()
     */
    protected function normalizeStringValue(string $value, bool $isFromDb): ?string
    {
        if ($isFromDb) {
            // do not modify DB value to avoid unintended changes
            return $value;
        }
        if ($this->shouldTrimStringValues()) {
            $value = trim($value);
        }
        if ($value === '' && $this->shouldConvertEmptyStringToNull()) {
            return null;
        }
        if ($this->shouldLowercaseValues()) {
            $value = mb_strtolower($value);
        }
        return $value;
    }

    protected function normalizeValidatedValueType(mixed $validatedValue): string
    {
        return (string)$validatedValue;
    }
}