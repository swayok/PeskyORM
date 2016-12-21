<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use Swayok\Utils\NormalizeValue;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class RecordValueHelpers {

    /**
     * @param Column $column
     * @param mixed $value
     * @param array $errorMessages
     * @return array
     */
    static public function isValidDbColumnValue(Column $column, $value, array $errorMessages = []) {
        if ($value instanceof DbExpr) {
            return [];
        }
        $value = static::preprocessColumnValue($column, $value);
        // null value?
        if ($value === null) {
            return $column->isValueCanBeNull()
                ? []
                : [static::getErrorMessage($errorMessages, $column::VALUE_CANNOT_BE_NULL)];
        }
        // data type validation
        $errors = static::isValueFitsDataType($value, $column->getType(), $errorMessages);
        if (!empty($errors)) {
            return $errors;
        }
        return [];
    }

    /**
     * Preprocess value using $column->getValuePreprocessor(). This will perform basic processing like
     * converting empty string to null, trimming and lowercasing if any required
     * @param Column $column
     * @param mixed $value
     * @param bool $isDbValue
     * @return mixed
     */
    static public function preprocessColumnValue(Column $column, $value, $isDbValue = false) {
        return call_user_func($column->getValuePreprocessor(), $value, $isDbValue, $column);
    }

    /**
     * Test if $value fits data type ($type)
     * @param mixed $value
     * @param string $type - one of Column::TYPE_*
     * @param array $errorMessages
     * @return array
     */
    static public function isValueFitsDataType($value, $type, array $errorMessages = []) {
        switch ($type) {
            case Column::TYPE_BOOL:
                if (!ValidateValue::isBoolean($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_BOOLEAN)];
                }
                break;
            case Column::TYPE_INT:
                if (!ValidateValue::isInteger($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_INTEGER)];
                }
                break;
            case Column::TYPE_FLOAT:
                if (!ValidateValue::isFloat($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_FLOAT)];
                }
                break;
            case Column::TYPE_ENUM:
                if (!is_string($value) && !is_numeric($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_STRING_OR_NUMERIC)];
                }
                break;
            case Column::TYPE_DATE:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_DATE)];
                }
                break;
            case Column::TYPE_TIME:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_TIME)];
                }
                break;
            case Column::TYPE_TIMESTAMP:
            case Column::TYPE_UNIX_TIMESTAMP:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_TIMESTAMP)];
                }
                break;
            case Column::TYPE_TIMESTAMP_WITH_TZ:
                if (!ValidateValue::isDateTimeWithTz($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_TIMESTAMP_WITH_TZ)];
                }
                break;
            case Column::TYPE_TIMEZONE_OFFSET:
                if (!ValidateValue::isTimezoneOffset($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_TIMEZONE_OFFSET)];
                }
                break;
            case Column::TYPE_IPV4_ADDRESS:
                if (!ValidateValue::isIpAddress($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_IPV4_ADDRESS)];
                }
                break;
            case Column::TYPE_JSON:
            case Column::TYPE_JSONB:
                if (!ValidateValue::isJson($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_JSON)];
                }
                break;
            case Column::TYPE_FILE:
                if (!ValidateValue::isUploadedFile($value, true)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_FILE)];
                }
                break;
            case Column::TYPE_IMAGE:
                if (!ValidateValue::isUploadedImage($value, true)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_IMAGE)];
                }
                break;
            case Column::TYPE_EMAIL:
                if (!ValidateValue::isEmail($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_EMAIL)];
                }
                break;
            case Column::TYPE_PASSWORD:
            case Column::TYPE_TEXT:
            case Column::TYPE_STRING:
                if (!is_string($value)) {
                    return [static::getErrorMessage($errorMessages, Column::VALUE_MUST_BE_STRING)];
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
     *
     * @param string|int|float|array $value
     * @param Column $column
     * @param array $errorMessages
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    static public function isValueWithinTheAllowedValuesOfTheColumn(
        Column $column,
        $value,
        array $errorMessages = []
    ) {
        $allowedValues = $column->getAllowedValues();
        $isEnum = $column->isEnum();
        if (count($allowedValues) === 0) {
            if ($isEnum) {
                throw new \UnexpectedValueException(
                    "Enum column [{$column->getName()}] is required to have a list of allowed values"
                );
            } else {
                return [];
            }
        }
        $value = static::preprocessColumnValue($column, $value);
        // column is nullable and value is null or should be converted to null
        if ($value === null && $column->isValueCanBeNull()) {
            return [];
        }
        // can value be used?
        if (!is_scalar($value) && !is_array($value)) {
            throw new \InvalidArgumentException(
                '$value argument must be a string, integer, float or array to be able to validate if it is within allowed values'
            );
        }
        // validate
        if (is_array($value)) {
            // compare if $value array is contained inside $allowedValues array
            if (count(array_diff($value, $allowedValues)) > 0) {
                return [static::getErrorMessage($errorMessages, Column::ONE_OF_VALUES_IS_NOT_ALLOWED)];
            }
        } else if (!in_array($value, $allowedValues, true)) {
            return [str_replace(':value', $value, static::getErrorMessage($errorMessages, Column::VALUE_IS_NOT_ALLOWED))];
        }
        return [];
    }

    /**
     * @param array $errorMessages
     * @param string $key
     * @return string
     */
    static public function getErrorMessage(array $errorMessages, $key) {
        return array_key_exists($key, $errorMessages) ? $errorMessages[$key] : $key;
    }

    /**
     * Normalize $value according to expected data type ($type)
     * @param mixed $value
     * @param string $type - one of Column::TYPE_*
     * @return null|string|UploadedFile
     */
    static public function normalizeValue($value, $type) {
        if ($value === null) {
            return null;
        }
        switch ($type) {
            case Column::TYPE_BOOL:
                return NormalizeValue::normalizeBoolean($value);
            case Column::TYPE_INT:
            case Column::TYPE_UNIX_TIMESTAMP:
                return NormalizeValue::normalizeInteger($value);
            case Column::TYPE_FLOAT:
                return NormalizeValue::normalizeFloat($value);
            case Column::TYPE_DATE:
                return NormalizeValue::normalizeDate($value);
            case Column::TYPE_TIME:
            case Column::TYPE_TIMEZONE_OFFSET:
                return NormalizeValue::normalizeTime($value);
            case Column::TYPE_TIMESTAMP:
                return NormalizeValue::normalizeDateTime($value);
            case Column::TYPE_TIMESTAMP_WITH_TZ:
                return NormalizeValue::normalizeDateTimeWithTz($value);
            case Column::TYPE_JSON:
            case Column::TYPE_JSONB:
                return NormalizeValue::normalizeJson($value);
            case Column::TYPE_FILE:
            case Column::TYPE_IMAGE:
                return static::normalizeFile($value);
            case Column::TYPE_STRING:
            case Column::TYPE_IPV4_ADDRESS:
            case Column::TYPE_EMAIL:
            case Column::TYPE_PASSWORD:
            case Column::TYPE_TEXT:
                return (string)$value;
            default:
                return $value;
        }
    }

    static public function normalizeFile($value) {
        if ($value instanceof UploadedFile) {
            return $value;
        } else {
            return new UploadedFile(
                $value['tmp_name'],
                $value['name'],
                $value['type'],
                $value['size'],
                $value['error'],
                !is_uploaded_file($value['tmp_name'])
            );
        }
    }

    static public function getValueFormatterAndFormatsByType($type) {
        $formatter = null;
        $formats = [];
        switch ($type) {
            case Column::TYPE_UNIX_TIMESTAMP:
            case Column::TYPE_TIMESTAMP:
            case Column::TYPE_TIMESTAMP_WITH_TZ:
                $formats = ['date', 'time', 'unix_ts'];
                $formatter = function (RecordValue $valueContainer, $format) {
                    return static::formatTimestamp($valueContainer, $format);
                };
                break;
            case Column::TYPE_DATE:
            case Column::TYPE_TIME:
                $formats = ['unix_ts'];
                $formatter = function (RecordValue $valueContainer, $format) {
                    return static::formatDateOrTime($valueContainer, $format);
                };
                break;
            case Column::TYPE_JSON:
            case Column::TYPE_JSONB:
                $formats = ['array', 'object'];
                $formatter = function (RecordValue $valueContainer, $format) {
                    return static::formatJson($valueContainer, $format);
                };
                break;
        }
        return [$formatter, $formats];
    }

    static protected function getSimpleValueFormContainer(RecordValue $valueContainer) {
        $value = $valueContainer->getValueOrDefault();
        if ($value instanceof DbExpr) {
            throw new \UnexpectedValueException('It is impossible to change format of the DbExpr');
        }
        return $value;
    }

    static public function formatTimestamp(RecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('format:' . $format, function (RecordValue $valueContainer) use ($format) {
            $value = static::getSimpleValueFormContainer($valueContainer);
            switch ($format) {
                case 'date':
                    return date(NormalizeValue::DATE_FORMAT, strtotime($value));
                case 'time':
                    return date(NormalizeValue::TIME_FORMAT, strtotime($value));
                case 'unix_ts':
                    return strtotime($value);
                default:
                    throw new \InvalidArgumentException("Requested value format '$format' is not implemented"); 
            }
        }, true);
    }

    static public function formatDateOrTime(RecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('format:' . $format, function (RecordValue $valueContainer) use ($format) {
            if ($format === 'unix_ts') {
                return strtotime(static::getSimpleValueFormContainer($valueContainer));
            } else {
                throw new \InvalidArgumentException("Requested value format '$format' is not implemented");
            }
        }, true);
    }

    static public function formatJson(RecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('format:' . $format, function (RecordValue $valueContainer) use ($format) {
            $value = static::getSimpleValueFormContainer($valueContainer);
            switch ($format) {
                case 'array':
                    return json_decode($value, true);
                case 'object':
                    return json_decode($value);
                default:
                    throw new \InvalidArgumentException("Requested value format '$format' is not implemented");
            }
        }, true);
    }

}