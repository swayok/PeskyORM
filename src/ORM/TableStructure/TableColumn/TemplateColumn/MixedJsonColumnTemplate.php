<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsCollectionInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * This column allows any value accepted by json_decode() / json_encode()
 * including numbers, strings and boolean values.
 * Use more strict columns to accept only arrays or objects:
 * @see JsonArrayColumnTemplate
 * @see JsonObjectColumnTemplate
 */
class MixedJsonColumnTemplate extends RealTableColumnAbstract
{
    public function getDataType(): string
    {
        return TableColumnDataType::JSON;
    }

    protected function isOnlyJsonArraysAndObjectsAllowed(): bool
    {
        return false;
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
            && !($value instanceof RecordsCollectionInterface)
        );
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        // don't call parent: SelectedRecordsArray is can't be passed here
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
        if (
            $isForCondition
            && (
                !is_object($normalizedValue)
                || $normalizedValue instanceof DbExpr
                || $normalizedValue instanceof SelectQueryBuilderInterface
            )
        ) {
            // There can be anything, so it is hard to validate it,
            // but most objects are not allowed
            return [];
        }


        if ($this->isOnlyJsonArraysAndObjectsAllowed()) {
            if (is_array($normalizedValue)) {
                return [];
            }
            if (
                is_string($normalizedValue)
                && (
                    ValueTypeValidators::isJsonArray($normalizedValue)
                    || ValueTypeValidators::isJsonObject($normalizedValue)
                )
            ) {
                return [];
            }

            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_ARRAY_OR_OBJECT
                ),
            ];
        }

        if (
            !ValueTypeValidators::isJsonEncodedString($normalizedValue)
            && !ValueTypeValidators::isJsonable($normalizedValue, false)
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
        if ($validatedValue instanceof RecordInterface) {
            return $this->encodeToJson($validatedValue->toArray());
        }
        if ($validatedValue instanceof RecordsCollectionInterface) {
            return $this->encodeToJson($validatedValue->toArrays());
        }

        if (ValueTypeValidators::isJsonEncodedString($validatedValue)) {
            return $validatedValue;
        }
        // number, bool, null, array, object, non-json string
        return $this->encodeToJson($validatedValue);
    }

    protected function encodeToJson(mixed $value): string
    {
        // convert inner objects to arrays if object have toArray() method
        if (is_array($value)) {
            foreach ($value as &$arrayValue) {
                if (is_object($arrayValue) && method_exists($arrayValue, 'toArray')) {
                    $arrayValue = $arrayValue->toArray();
                }
            }
            unset($arrayValue);
        }
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
            if ($validatedValue instanceof RecordsCollectionInterface) {
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