<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\Select\SelectQueryBuilderInterface;
use Swayok\Utils\NormalizeValue;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class ColumnValueProcessingHelpers
{
    
    public static function isValidDbColumnValue(
        TableColumn $column,
        mixed $value,
        bool $isFromDb,
        bool $isForCondition,
        array $errorMessages = []
    ): array {
        if ($value instanceof DbExpr || $value instanceof SelectQueryBuilderInterface) {
            return [];
        }
        $preprocessedValue = static::preprocessColumnValue($column, $value, $isFromDb, true);
        // null value?
        if ($preprocessedValue === null) {
            if ($isForCondition) {
                return [];
            }

            if (($isFromDb && $value !== null) || $column->isValueCanBeNull()) {
                // db value is not null but was preprocessed into null - it is not an error
                return [];
            }

            return [static::getErrorMessage($errorMessages, $column::VALUE_CANNOT_BE_NULL)];
        }
        // data type validation
        $errors = static::isValueFitsDataType($preprocessedValue, $column->getType(), $isForCondition, $errorMessages);
        if (!empty($errors)) {
            return $errors;
        }
        return [];
    }
    
    /**
     * Preprocess value using $column->getValuePreprocessor(). This will perform basic processing like
     * converting empty string to null, trimming and lowercasing if any required
     */
    public static function preprocessColumnValue(TableColumnInterface $column, mixed $value, bool $isDbValue, bool $isForValidation): mixed
    {
        return call_user_func($column->getValuePreprocessor(), $value, $isDbValue, $isForValidation, $column);
    }
    
    /**
     * Test if $value fits data type ($type)
     * @param mixed $value
     * @param string $type - one of TableColumn::TYPE_*
     * @param bool $isForCondition - true: validate less strictly | false: validate strictly
     * @param array $errorMessages
     * @return array
     */
    public static function isValueFitsDataType(mixed $value, string $type, bool $isForCondition, array $errorMessages = []): array
    {
        switch ($type) {
            case TableColumn::TYPE_BOOL:
                if (!ValidateValue::isBoolean($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_BOOLEAN)];
                }
                break;
            case TableColumn::TYPE_INT:
                if (!ValidateValue::isInteger($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_INTEGER)];
                }
                break;
            case TableColumn::TYPE_FLOAT:
                if (!ValidateValue::isFloat($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_FLOAT)];
                }
                break;
            case TableColumn::TYPE_ENUM:
                if (!is_string($value) && !is_numeric($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_STRING_OR_NUMERIC)];
                }
                break;
            case TableColumn::TYPE_DATE:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_DATE)];
                }
                break;
            case TableColumn::TYPE_TIME:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_TIME)];
                }
                break;
            case TableColumn::TYPE_TIMESTAMP:
            case TableColumn::TYPE_UNIX_TIMESTAMP:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_TIMESTAMP)];
                }
                break;
            case TableColumn::TYPE_TIMESTAMP_WITH_TZ:
                if (!ValidateValue::isDateTimeWithTz($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_TIMESTAMP_WITH_TZ)];
                }
                break;
            case TableColumn::TYPE_TIMEZONE_OFFSET:
                if (!ValidateValue::isTimezoneOffset($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_TIMEZONE_OFFSET)];
                }
                break;
            case TableColumn::TYPE_IPV4_ADDRESS:
                if (!is_string($value) || (!$isForCondition && !ValidateValue::isIpAddress($value))) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_IPV4_ADDRESS)];
                }
                break;
            case TableColumn::TYPE_JSON:
            case TableColumn::TYPE_JSONB:
                if ($isForCondition && !is_string($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_STRING)];
                }

                if (!$isForCondition && !ValidateValue::isJson($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_JSON)];
                }
                break;
            case TableColumn::TYPE_FILE:
                if (!ValidateValue::isUploadedFile($value, true)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_FILE)];
                }
                break;
            case TableColumn::TYPE_IMAGE:
                if (!ValidateValue::isUploadedImage($value, true)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_IMAGE)];
                }
                break;
            case TableColumn::TYPE_EMAIL:
                if ($isForCondition && !is_string($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_STRING)];
                }

                if (!$isForCondition && !ValidateValue::isEmail($value)) {
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_EMAIL)];
                }
                break;
            case TableColumn::TYPE_PASSWORD:
            case TableColumn::TYPE_TEXT:
            case TableColumn::TYPE_STRING:
                if (
                    !is_string($value)
                    && !is_numeric($value)
                    && !(
                        is_object($value)
                        && method_exists($value, '__toString')
                    )
                ) {
                    //^ numbers can be normally converted to strings
                    return [static::getErrorMessage($errorMessages, TableColumn::VALUE_MUST_BE_STRING)];
                }
                break;
        }
        return [];
    }
    
    /**
     * Test if value is present in $column->getAllowedValues() (if any)
     * Notes:
     * - $value is not allowed be an object or resouce
     * - if $value is array - all entries of this array will be validated to be contained in $column->getAllowedValues();
     * > if $column->isEnum() === true
     *   - it is expected to have not empty $column->getAllowedValues()
     *   - empty string $value when $column->isEmptyStringMustBeConvertedToNull() === true will be treated as null
     *   - null $value is allowed only when $column->isValueCanBeNull() === true
     *   - in other cases empty string $value will be validated to be contained in $column->getAllowedValues()
     * > if $column->isEnum() === false
     *   - validation will be ignored when $column->getAllowedValues() is empty
     *   - validation will be ignored when $column->isValueCanBeNull() === true and value is null or
     *     empty string with option $column->isEmptyStringMustBeConvertedToNull() === true;
     */
    public static function isValueWithinTheAllowedValuesOfTheColumn(
        TableColumn $column,
        int|float|bool|string|array|null $value,
        bool $isFromDb,
        array $errorMessages = []
    ): array {
        $allowedValues = $column->getAllowedValues();
        $isEnum = $column->isEnum();
        if (count($allowedValues) === 0) {
            if ($isEnum) {
                throw new \UnexpectedValueException(
                    "Enum column [{$column->getName()}] is required to have a list of allowed values"
                );
            }

            return [];
        }
        $preprocessedValue = static::preprocessColumnValue($column, $value, $isFromDb, true);
        // column is nullable and value is null or should be converted to null
        if ($preprocessedValue === null && ($column->isValueCanBeNull() || ($isFromDb && $value !== null))) {
            return [];
        }
        // can value be used?
        if (!is_scalar($preprocessedValue) && !is_array($preprocessedValue)) {
            throw new \InvalidArgumentException(
                '$value argument must be a string, integer, float or array to be able to validate if it is within allowed values'
            );
        }
        // validate
        if (is_array($preprocessedValue)) {
            // compare if $value array is contained inside $allowedValues array
            if (count(array_diff($preprocessedValue, $allowedValues)) > 0) {
                return [static::getErrorMessage($errorMessages, TableColumn::ONE_OF_VALUES_IS_NOT_ALLOWED)];
            }
        } elseif (!in_array($preprocessedValue, $allowedValues, true)) {
            return [str_replace(':value', $preprocessedValue, static::getErrorMessage($errorMessages, TableColumn::VALUE_IS_NOT_ALLOWED))];
        }
        return [];
    }
    
    public static function getErrorMessage(array $errorMessages, string $key): string
    {
        return array_key_exists($key, $errorMessages) ? $errorMessages[$key] : $key;
    }
    
    /**
     * Normalize $value according to expected data type ($type)
     * @param mixed $value
     * @param string $type - one of TableColumn::TYPE_*
     * @return mixed
     */
    public static function normalizeValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DbExpr || $value instanceof SelectQueryBuilderInterface) {
            return $value;
        }

        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($type) {
            case TableColumn::TYPE_BOOL:
                return NormalizeValue::normalizeBoolean($value);
            case TableColumn::TYPE_INT:
            case TableColumn::TYPE_UNIX_TIMESTAMP:
                return NormalizeValue::normalizeInteger($value);
            case TableColumn::TYPE_FLOAT:
                return NormalizeValue::normalizeFloat($value);
            case TableColumn::TYPE_DATE:
                return NormalizeValue::normalizeDate($value);
            case TableColumn::TYPE_TIME:
            case TableColumn::TYPE_TIMEZONE_OFFSET:
                return NormalizeValue::normalizeTime($value);
            case TableColumn::TYPE_TIMESTAMP:
                return NormalizeValue::normalizeDateTime($value);
            case TableColumn::TYPE_TIMESTAMP_WITH_TZ:
                return NormalizeValue::normalizeDateTimeWithTz($value);
            case TableColumn::TYPE_JSON:
            case TableColumn::TYPE_JSONB:
                return NormalizeValue::normalizeJson($value);
            case TableColumn::TYPE_FILE:
            case TableColumn::TYPE_IMAGE:
                return static::normalizeFile($value);
            case TableColumn::TYPE_STRING:
            case TableColumn::TYPE_IPV4_ADDRESS:
            case TableColumn::TYPE_EMAIL:
            case TableColumn::TYPE_PASSWORD:
            case TableColumn::TYPE_TEXT:
                return (string)$value;
            default:
                return $value;
        }
    }
    
    /**
     * Normalize $value received form DB according to expected data type ($type)
     * Note: lighter version of normalizeValue() to optimize processing of large amount of records
     * @param mixed $value
     * @param string $type - one of TableColumn::TYPE_*
     * @return mixed|null
     */
    public static function normalizeValueReceivedFromDb(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DbExpr) {
            return $value;
        }

        return match ($type) {
            TableColumn::TYPE_BOOL => NormalizeValue::normalizeBoolean($value),
            TableColumn::TYPE_INT, TableColumn::TYPE_UNIX_TIMESTAMP => NormalizeValue::normalizeInteger($value),
            TableColumn::TYPE_FLOAT => NormalizeValue::normalizeFloat($value),
            default => $value,
        };
    }
    
    public static function normalizeFile(array|UploadedFile $value): UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        return new UploadedFile(
            $value['tmp_name'],
            $value['name'],
            $value['type'],
            $value['error'],
            !is_uploaded_file($value['tmp_name'])
        );
    }
}
