<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;

class StringColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeNullable;
    use CanBeUnique;
    use CanBePrivate;

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

    protected function shouldTrimStringValues(bool $isFromDb): bool
    {
        return $this->trimValues;
    }

    public function lowercasesValues(): static
    {
        $this->lowercaseValues = true;
        return $this;
    }

    protected function shouldLowercaseValues(bool $isFromDb): bool
    {
        return $this->lowercaseValues;
    }

    public function convertsEmptyStringValuesToNull(): static
    {
        $this->convertEmptyStringValueToNull = true;
        return $this;
    }

    protected function shouldConvertEmptyStringToNull(bool $isFromDb): bool
    {
        return $this->convertEmptyStringValueToNull ?? $this->isNullableValues();
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = $value->__toString();
        }
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

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!is_string($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_STRING
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        return (string)$validatedValue;
    }
}