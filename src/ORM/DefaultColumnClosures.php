<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbExpr;

class DefaultColumnClosures implements ColumnClosuresInterface
{
    
    /**
     * @throws \BadMethodCallException
     */
    public static function valueSetter(mixed $newValue, bool $isFromDb, RecordValue $valueContainer, bool $trustDataReceivedFromDb): RecordValue
    {
        $column = $valueContainer->getColumn();
        if (!$isFromDb && !$column->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "Column '{$column->getName()}' restricts value modification"
            );
        }
        if ($isFromDb && $trustDataReceivedFromDb) {
            $normalziedValue = RecordValueHelpers::normalizeValueReceivedFromDb($newValue, $column->getType());
            $valueContainer->setRawValue($newValue, $normalziedValue, true);
            $valueContainer->setValidValue($normalziedValue, $newValue);
        } else {
            $preprocessedValue = call_user_func($column->getValuePreprocessor(), $newValue, $isFromDb, false, $column);
            if ($preprocessedValue === null && $column->hasDefaultValue()) {
                $preprocessedValue = $column->getValidDefaultValue();
            }
            if (
                $valueContainer->hasValue()
                && (
                    $valueContainer->getRawValue() === $preprocessedValue
                    || $valueContainer->getValue() === $preprocessedValue
                )
            ) {
                // received same value as current one (raw or normalized)
                if ($isFromDb && !$valueContainer->isItFromDb()) {
                    // value has changed its satatus to 'received from db'
                    $valueContainer->setIsFromDb(true);
                }
            } else {
                $valueContainer->setRawValue($newValue, $preprocessedValue, $isFromDb);
                $errors = $column->validateValue($valueContainer->getValue(), $isFromDb, false);
                if (count($errors) > 0) {
                    $valueContainer->setValidationErrors($errors);
                } else {
                    $normalziedValue = call_user_func(
                        $column->getValueNormalizer(),
                        $preprocessedValue,
                        $isFromDb,
                        $column
                    );
                    $valueContainer->setValidValue($normalziedValue, $newValue);
                }
            }
        }
        return $valueContainer;
    }
    
    public static function valuePreprocessor(mixed $value, bool $isFromDb, bool $isForValidation, Column $column): mixed
    {
        if ($isFromDb && !$isForValidation) {
            return $value;
        }
        if (is_string($value)) {
            if (!$isFromDb && $column->isValueTrimmingRequired()) {
                $value = trim($value);
            }
            if ($value === '' && $column->isEmptyStringMustBeConvertedToNull()) {
                return null;
            }
            if (!$isFromDb && $column->isValueLowercasingRequired()) {
                $value = mb_strtolower($value);
            }
        } elseif ($value instanceof RecordsSet) {
            $value = $value->getOrmSelect();
        }
        return $value;
    }
    
    public static function valueGetter(RecordValue $valueContainer, ?string $format = null): mixed
    {
        if ($format !== null) {
            return call_user_func(
                $valueContainer->getColumn()->getValueFormatter(),
                $valueContainer,
                $format
            );
        } else {
            return $valueContainer->getValueOrDefault();
        }
    }
    
    public static function valueExistenceChecker(RecordValue $valueContainer, bool $checkDefaultValue = false): bool
    {
        return $checkDefaultValue ? $valueContainer->hasValueOrDefault() : $valueContainer->hasValue();
    }
    
    /**
     * @throws \UnexpectedValueException
     */
    public static function valueValidator(mixed $value, bool $isFromDb, bool $isForCondition, Column $column): array
    {
        $errors = RecordValueHelpers::isValidDbColumnValue(
            $column,
            $value,
            $isFromDb,
            $isForCondition,
            $column::getValidationErrorsMessages()
        );
        if (count($errors) > 0) {
            return $errors;
        }
        if (is_object($value) && ($value instanceof DbExpr || is_subclass_of($value, AbstractSelect::class))) {
            // can't be validated in any other way
            return [];
        }
        $errors = call_user_func($column->getValueIsAllowedValidator(), $value, $isFromDb, $isForCondition, $column);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Allowed value validator closure must return an array');
        } elseif (count($errors) > 0) {
            return $errors;
        }
        $errors = call_user_func($column->getValueValidatorExtender(), $value, $isFromDb, $isForCondition, $column);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Value validator extender closure must return an array');
        }
        return $errors;
    }
    
    public static function valueIsAllowedValidator(mixed $value, bool $isFromDb, Column $column): array
    {
        return RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn(
            $column,
            $value,
            $isFromDb,
            $column::getValidationErrorsMessages()
        );
    }
    
    public static function valueNormalizer(mixed $value, bool $isFromDb, Column $column): mixed
    {
        return $isFromDb
            ? RecordValueHelpers::normalizeValueReceivedFromDb($value, $column->getType())
            : RecordValueHelpers::normalizeValue($value, $column->getType());
    }
    
    public static function valueSavingExtender(RecordValue $valueContainer, bool $isUpdate, array $savedData): void
    {
    }
    
    public static function valueDeleteExtender(RecordValue $valueContainer, bool $deleteFiles): void
    {
    }
    
    public static function valueValidatorExtender(mixed $value, bool $isFromDb, Column $column): array
    {
        return [];
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public static function valueFormatter(RecordValue $valueContainer, string $format): mixed
    {
        $column = $valueContainer->getColumn();
        $customFormatters = $column->getCustomValueFormatters();
        if (isset($customFormatters[$format])) {
            return $customFormatters[$format]($valueContainer);
        }
        $typeFormatters = $column->getValueFormattersForColumnType();
        if (isset($typeFormatters[$format])) {
            return $typeFormatters[$format]($valueContainer);
        }
        $formats = $column->getValueFormattersNames();
        throw new \InvalidArgumentException(
            "Value format '{$format}' is not supported for column '{$column->getName()}'."
            . ' Supported formats: ' . (count($formats) ? implode(', ', $formats) : 'none')
        );
    }
}
