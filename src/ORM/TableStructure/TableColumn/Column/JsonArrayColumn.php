<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeVirtual;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * This column allows only indexed arrays.
 * Example: '["value1", "value2", {"key1": ""}, ...]'.
 */
class JsonArrayColumn extends TableColumnAbstract
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanBeVirtual;

    public function getDataType(): string
    {
        return TableColumnDataType::JSON;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getJsonArrayFormatters();
        // todo: add formatter that can convert every value in array to instance of specific class
        //  similar to 'object' formatter
    }

    protected function shouldValidateValue(mixed $value): bool
    {
        return (
            parent::shouldValidateValue($value)
            && !($value instanceof RecordsArray)
        );
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        // don't call parent: RecordsSet is can't be passed here
        // because RecordsArray should not be validated
        if (
            is_string($value)
            && ($value === 'null' || trim($value) === '')
        ) {
            return null;
        }
        return $value;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if ($isForCondition) {
            // there can be anything, so it is hard to validate it
            return [];
        }

        if (!ValueTypeValidators::isJsonArray($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_ARRAY
                ),
            ];
        }

        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): mixed {
        if ($isFromDb) {
            return is_array($validatedValue)
                ? $this->encodeToJson($validatedValue)
                : $validatedValue;
        }

        if ($validatedValue instanceof RecordsArray) {
            return $this->encodeToJson($validatedValue->toArrays());
        }
        if (ValueTypeValidators::isJsonEncodedString($validatedValue)) {
            return $validatedValue;
        }
        // array
        return $this->encodeToJson($validatedValue);
    }

    protected function encodeToJson(array $array): string
    {
        // convert inner objects to arrays if object have toArray() method
        foreach ($array as &$value) {
            if (is_object($value) && method_exists($value, 'toArray')) {
                $value = $value->toArray();
            }
        }
        unset($value);
        return json_encode($array, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function setValidatedValueToValueContainer(
        RecordValueContainerInterface $valueContainer,
        mixed $originalValue,
        mixed $validatedValue,
        bool $isFromDb
    ): void {
        parent::setValidatedValueToValueContainer(
            $valueContainer,
            $originalValue,
            $validatedValue,
            $isFromDb
        );
        // if $validatedValue is array or can be converted - store it in
        // $valueContainer so that 'array' formatter won't need to decode json
        if (isset($this->formatters[ColumnValueFormatters::FORMAT_ARRAY])) {
            if ($validatedValue instanceof RecordsArray) {
                $validatedValue = $validatedValue->toArrays();
            }
            if (is_array($validatedValue)) {
                ColumnValueFormatters::rememberFormattedValueInValueContainer(
                    $valueContainer,
                    ColumnValueFormatters::FORMAT_ARRAY,
                    $validatedValue
                );
            }
        }
    }
}