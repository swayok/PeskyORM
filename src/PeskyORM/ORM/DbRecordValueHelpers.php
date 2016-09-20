<?php

namespace PeskyORM\ORM;

use Swayok\Utils\NormalizeValue;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class DbRecordValueHelpers {

    /**
     * @param mixed $value
     * @param DbTableColumn $column
     * @param array $errorMessages
     * @return array
     * @throws \UnexpectedValueException
     */
    static public function isValidDbColumnValue($value, DbTableColumn $column, array $errorMessages = []) {
        // null value validation
        if ($value === null) {
            if (!$column->isValueCanBeNull()) {
                return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_CANNOT_BE_NULL)];
            }
            return [];
        }
        // required value validation
        if ($value === null && $column->isValueRequired()) {
            return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_IS_REQUIRED)];
        }
        // data type validation
        $errors = static::validateIfValueFitsDataType($value, $column->getType(), $errorMessages);
        if (!empty($errors)) {
            return $errors;
        }
        // test if value is present in $column->getAllowedValues()
        $isEnum = $column->getType() === DbTableColumn::TYPE_ENUM;
        $allowedValues = $column->getAllowedValues();
        if ($isEnum) {
            if (empty($allowedValues)) {
                throw new \UnexpectedValueException('Enum column is required to have a list of allowed values');
            }
            if (!$allowedValues || !in_array($value, $allowedValues, true)) {
                return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_IS_NOT_ALLOWED)];
            }
        } else if (!empty($allowedValues) && (!empty($value) || $column->isValueCanBeNull())) {
            if (is_string($value) || is_numeric($value)) {
                if (!in_array($value, $allowedValues, true)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_IS_NOT_ALLOWED)];
                }
            } else if (is_array($value)) {
                if (count(array_diff($value, $allowedValues)) > 0) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::ONE_OF_VALUES_IS_NOT_ALLOWED)];
                }
            } else {
                throw new \UnexpectedValueException(
                    'Value type must be a string, integer, float or array to be able to validate if it is within allowed values'
                );
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     * @param string $type
     * @param array $errorMessages
     * @return array
     */
    static public function validateIfValueFitsDataType($value, $type, array $errorMessages = []) {
        switch ($type) {
            case DbTableColumn::TYPE_BOOL:
                if (!ValidateValue::isBoolean($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_BOOLEAN)];
                }
                break;
            case DbTableColumn::TYPE_INT:
                if (!ValidateValue::isInteger($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_INTEGER)];
                }
                break;
            case DbTableColumn::TYPE_FLOAT:
                if (!ValidateValue::isFloat($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_FLOAT)];
                }
                break;
            case DbTableColumn::TYPE_DATE:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_DATE)];
                }
                break;
            case DbTableColumn::TYPE_TIME:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIME)];
                }
                break;
            case DbTableColumn::TYPE_TIMESTAMP:
            case DbTableColumn::TYPE_UNIX_TIMESTAMP:
                if (!ValidateValue::isDateTime($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIMESTAMP)];
                }
                break;
            case DbTableColumn::TYPE_TIMESTAMP_WITH_TZ:
                if (!ValidateValue::isDateTimeWithTz($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIMESTAMP_WITH_TZ)];
                }
                break;
            case DbTableColumn::TYPE_TIMEZONE_OFFSET:
                if (!ValidateValue::isTimezoneOffset($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIMEZONE_OFFSET)];
                }
                break;
            case DbTableColumn::TYPE_IPV4_ADDRESS:
                if (!ValidateValue::isIpAddress($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_IPV4_ADDRESS)];
                }
                break;
            case DbTableColumn::TYPE_JSON:
            case DbTableColumn::TYPE_JSONB:
                if (!ValidateValue::isJson($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_JSON)];
                }
                break;
            case DbTableColumn::TYPE_FILE:
                if (!ValidateValue::isUploadedFile($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_FILE)];
                }
                break;
            case DbTableColumn::TYPE_IMAGE:
                if (!ValidateValue::isUploadedImage($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_IMAGE)];
                }
                break;
            case DbTableColumn::TYPE_EMAIL:
                if (!ValidateValue::isEmail($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_EMAIL)];
                }
                break;
            case DbTableColumn::TYPE_ENUM:
            case DbTableColumn::TYPE_PASSWORD:
            case DbTableColumn::TYPE_TEXT:
            case DbTableColumn::TYPE_STRING:
                if (!is_string($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_STRING)];
                }
                break;
        }
        return [];
    }

    static public function getErrorMessage(array $errorMessages, $key) {
        return array_key_exists($key, $errorMessages) ? $errorMessages[$key] : $key;
    }

    static public function normalizeValue($value, $type) {
        if ($value === null) {
            return null;
        }
        switch ($type) {
            case DbTableColumn::TYPE_BOOL:
                return NormalizeValue::normalizeBoolean($value);
            case DbTableColumn::TYPE_INT:
            case DbTableColumn::TYPE_UNIX_TIMESTAMP:
                return NormalizeValue::normalizeInteger($value);
            case DbTableColumn::TYPE_FLOAT:
                return NormalizeValue::normalizeFloat($value);
            case DbTableColumn::TYPE_DATE:
                return NormalizeValue::normalizeDate($value);
            case DbTableColumn::TYPE_TIME:
                return NormalizeValue::normalizeTime($value);
            case DbTableColumn::TYPE_TIMESTAMP:
                return NormalizeValue::normalizeDateTime($value);
            case DbTableColumn::TYPE_TIMEZONE_OFFSET:
                return NormalizeValue::normalizeTimezoneOffset($value);
            case DbTableColumn::TYPE_JSON:
            case DbTableColumn::TYPE_JSONB:
                return NormalizeValue::normalizeJson($value);
            case DbTableColumn::TYPE_FILE:
            case DbTableColumn::TYPE_IMAGE:
                return static::normalizeFile($value);
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
                $value['error']
            );
        }
    }

    static public function getValueFormatterAndFormatsByType($type) {
        $formatter = null;
        $formats = [];
        switch ($type) {
            case DbTableColumn::TYPE_UNIX_TIMESTAMP:
            case DbTableColumn::TYPE_TIMESTAMP:
            case DbTableColumn::TYPE_TIMESTAMP_WITH_TZ:
                $formats = ['date', 'time', 'unix_ts'];
                $formatter = function (DbRecordValue $valueContainer, $format) {
                    static::formatTimestamp($valueContainer, $format);
                };
                break;
            case DbTableColumn::TYPE_DATE:
            case DbTableColumn::TYPE_TIME:
                $formats = ['unix_ts'];
                $formatter = function (DbRecordValue $valueContainer, $format) {
                    static::formatDateOrTime($valueContainer, $format);
                };
                break;
            case DbTableColumn::TYPE_JSON:
            case DbTableColumn::TYPE_JSONB:
                $formats = ['array', 'object'];
                $formatter = function (DbRecordValue $valueContainer, $format) {
                    static::formatJson($valueContainer, $format);
                };
                break;
        }
        return [$formatter, $formats];
    }

    static public function formatTimestamp(DbRecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('as_' . $format, function (DbRecordValue $valueContainer) use ($format) {
            switch ($format) {
                case 'date':
                    return date(NormalizeValue::DATE_FORMAT, strtotime($valueContainer->getValue()));
                case 'time':
                    return date(NormalizeValue::TIME_FORMAT, strtotime($valueContainer->getValue()));
                case 'unix_ts':
                    return strtotime($valueContainer->getValue());
                default:
                    throw new \InvalidArgumentException("Requested value format '$format' is not implemented"); 
            }
        }, true);
    }

    static public function formatDateOrTime(DbRecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('as_' . $format, function (DbRecordValue $valueContainer) use ($format) {
            if ($format === 'unix_ts') {
                return strtotime($valueContainer->getValue());
            } else {
                throw new \InvalidArgumentException("Requested value format '$format' is not implemented");
            }
        }, true);
    }

    static public function formatJson(DbRecordValue $valueContainer, $format) {
        if (!is_string($format)) {
            throw new \InvalidArgumentException('$format argument must be a string');
        }
        return $valueContainer->getCustomInfo('as_' . $format, function (DbRecordValue $valueContainer) use ($format) {
            switch ($format) {
                case 'array':
                    return json_decode($valueContainer->getValue(), true);
                case 'object':
                    return json_decode($valueContainer->getValue());
                default:
                    throw new \InvalidArgumentException("Requested value format '$format' is not implemented");
            }
        }, true);
    }

}