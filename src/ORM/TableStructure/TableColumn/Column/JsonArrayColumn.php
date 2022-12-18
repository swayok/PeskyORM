<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsCollectionInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * This column allows only indexed arrays.
 * Example: '["value1", "value2", {"key1": ""}, ...]'.
 */
class JsonArrayColumn extends RealTableColumnAbstract
{
    use CanBeNullable;
    use CanBeHeavy;

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

    protected function shouldValidateValue(mixed $value, bool $isFromDb): bool
    {
        return (
            parent::shouldValidateValue($value, $isFromDb)
            && !($value instanceof RecordsCollectionInterface)
        );
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        // don't call parent: RecordsSet is can't be passed here
        // because RecordsCollectionInterface should not be validated
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

        if ($normalizedValue !== '{}' && !ValueTypeValidators::isJsonArray($normalizedValue)) {
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
        if ($validatedValue === '{}') {
            return '[]';
        }

        if ($validatedValue instanceof RecordsCollectionInterface) {
            return $this->encodeToJson($validatedValue->toArrays());
        }

        if ($isFromDb) {
            if (is_array($validatedValue)) {
                return $this->encodeToJson($validatedValue);
            }
            return $validatedValue;
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
        $valueContainer->setValue(
            $originalValue,
            $this->normalizeValidatedValue($validatedValue, $isFromDb),
            $isFromDb,
            false
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