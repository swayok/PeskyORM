<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ConvertsValueToClassInstanceInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeVirtual;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanConvertValueToClassInstance;
use PeskyORM\Utils\ValueTypeValidators;

class JsonColumn extends TableColumnAbstract implements ConvertsValueToClassInstanceInterface
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanBeVirtual;
    use CanConvertValueToClassInstance;

    public function getDataType(): string
    {
        return TableColumnDataType::JSON;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getJsonFormatters();
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        if ($value instanceof RecordValueContainerInterface) {
            $value = $value->getValue();
        }
        if (
            $value instanceof RecordsArray
            || $value instanceof RecordInterface
        ) {
            return $value;
        }
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
        $isString = is_string($normalizedValue);
        $isArray = is_array($normalizedValue);
        if ($isForCondition) {
            if (!$isString && !$isArray) {
                return [
                    $this->getValueValidationMessage(
                        ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_OR_ARRAY
                    ),
                ];
            }
            return [];
        }

        if ($isFromDb) {
            if (
                !$isString
                && !$isArray
                && $normalizedValue !== null
            ) {
                return [
                    $this->getValueValidationMessage(
                        ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_OR_ARRAY
                    ),
                ];
            }
            return [];
        }

        if (is_object($normalizedValue)) {
            return [];
        }
        if (!ValueTypeValidators::isJson($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_JSON_OR_ARRAY
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
            $validatedValue = $validatedValue->toArray();
        } elseif ($validatedValue instanceof RecordsArray) {
            $validatedValue = $validatedValue->toArrays();
        } elseif (is_object($validatedValue)) {
            $validatedValue = method_exists($validatedValue, 'toArray')
                ? $validatedValue->toArray()
                : $validatedValue->__toString();
        }
        if (is_array($validatedValue)) {
            return $this->encodeToJson($validatedValue);
        }
        // number, boolean or string
        return ValueTypeValidators::isJson($validatedValue)
            ? (string)$validatedValue
            : $this->encodeToJson($validatedValue);
    }

    protected function encodeToJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

}