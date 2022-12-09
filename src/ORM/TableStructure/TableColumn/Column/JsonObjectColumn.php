<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ConvertsValueToClassInstanceInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanConvertValueToClassInstance;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * This column allows only key-value objects.
 * Example: '{"key1": "value", "key2": ["v1", "v2"], "key3": {"k1": ""}, "0": "", ...}'.
 * Note: value '[]' (empty array) is allowed and handled like empty object: '{}'.
 */
class JsonObjectColumn extends RealTableColumnAbstract implements ConvertsValueToClassInstanceInterface
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanConvertValueToClassInstance;

    public function getDataType(): string
    {
        return TableColumnDataType::JSON;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getJsonObjectFormatters();
    }

    protected function shouldValidateValue(mixed $value): bool
    {
        return (
            parent::shouldValidateValue($value)
            && !($value instanceof RecordInterface)
        );
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        // don't call parent: RecordsSet is can't be passed here
        // because RecordsArray should not be validated
        if (
            is_object($value)
            && method_exists($value, 'toArray')
        ) {
            $value = $value->toArray();
        }
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

        if (!ValueTypeValidators::isJsonObject($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_OBJECT
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

        if ($validatedValue instanceof RecordInterface) {
            return $this->encodeToJson($validatedValue->toArray());
        }
        if (ValueTypeValidators::isJsonEncodedString($validatedValue)) {
            return $validatedValue;
        }
        // array
        return $this->encodeToJson($validatedValue);
    }

    protected function encodeToJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
            if ($validatedValue instanceof RecordInterface) {
                $validatedValue = $validatedValue->toArray();
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