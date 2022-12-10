<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
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
 * This column allows any value accepted by json_decode() / json_encode()
 * including numbers, strings and boolean values.
 * Use more strict columns to accept only arrays or objects:
 * @see JsonArrayColumn
 * @see JsonObjectColumn
 */
class MixedJsonColumn extends RealTableColumnAbstract implements ConvertsValueToClassInstanceInterface
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanConvertValueToClassInstance;

    protected bool $allowsOnlyJsonArraysAndObjects = false;

    public function getDataType(): string
    {
        return TableColumnDataType::JSON;
    }

    /**
     * Allow: json array, json object.
     * Forbid: bool, number, string, others.
     * Nulls controlled by self::isNullableValues()
     */
    public function allowsOnlyJsonArraysAndObjects(): static
    {
        $this->allowsOnlyJsonArraysAndObjects = true;
        return $this;
    }

    public function isOnlyJsonArraysAndObjectsAllowed(): bool
    {
        return $this->allowsOnlyJsonArraysAndObjects;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getJsonFormatters();
    }

    protected function shouldValidateValue(mixed $value, bool $isFromDb): bool
    {
        return (
            parent::shouldValidateValue($value, $isFromDb)
            && !($value instanceof RecordInterface)
            && !($value instanceof RecordsArray)
        );
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        // don't call parent: RecordsSet is can't be passed here
        // because RecordsArray should not be validated
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                $value = $value->toArray();
            } elseif (method_exists($value, '__toString')) {
                $value = $value->__toString();
            }
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

        $isString = is_string($normalizedValue);
        if ($isFromDb && $isString) {
            return [];
        }

        /** @noinspection NotOptimalIfConditionsInspection */
        if (
            $this->isOnlyJsonArraysAndObjectsAllowed()
            && !$isString
            && !is_array($normalizedValue)
        ) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_ARRAY_OR_OBJECT
                ),
            ];
        }

        if (
            !ValueTypeValidators::isJsonEncodedString($normalizedValue)
            && !ValueTypeValidators::isJsonable($normalizedValue, !$isFromDb)
        ) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_OR_JSONABLE
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
        if ($validatedValue instanceof RecordsArray) {
            return $this->encodeToJson($validatedValue->toArrays());
        }

        if (ValueTypeValidators::isJsonEncodedString($validatedValue)) {
            return $validatedValue;
        }
        // number, bool, null, array, object, non-json string
        return $this->encodeToJson($validatedValue);
    }

    private function encodeToJson(mixed $value): string
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