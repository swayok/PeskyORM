<?php

namespace PeskyORM\ORM;

abstract class DbRecordValueHelpers {

    /**
     * @param $value
     * @param DbTableColumn $column
     * @param $errorMessages
     * @return array
     */
    static public function isValidDbColumnValue($value, DbTableColumn $column, $errorMessages) {
        // null value validation
        if ($value === null) {
            if (!$column->isValueCanBeNull()) {
                return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_CANNOT_BE_NULL)];
            }
            return [];
        }
        // data type validation
        switch ($column->getType()) {
            case DbTableColumn::TYPE_BOOL:
                if (!static::isBoolean($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_BOOLEAN)];
                }
                break;
            case DbTableColumn::TYPE_INT:
                if (!static::isInteger($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_INTEGER)];
                }
                break;
            case DbTableColumn::TYPE_FLOAT:
                if (!static::isFloat($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_FLOAT)];
                }
                break;
            case DbTableColumn::TYPE_DATE:
                if (!static::isDate($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_DATE)];
                }
                break;
            case DbTableColumn::TYPE_TIME:
                if (!static::isTime($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIME)];
                }
                break;
            case DbTableColumn::TYPE_TIMESTAMP:
                if (!static::isTimestamp($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIMEZONE)];
                }
                break;
            case DbTableColumn::TYPE_TIMEZONE_OFFSET:
                if (!static::isTimezoneOffset($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_TIMEZONE_OFFSET)];
                }
                break;
            case DbTableColumn::TYPE_IPV4_ADDRESS:
                if (!static::isIpv4Address($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_IPV4_ADDRESS)];
                }
                break;
            case DbTableColumn::TYPE_JSON:
            case DbTableColumn::TYPE_JSONB:
                if (!static::isJson($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_JSON)];
                }
                break;
            case DbTableColumn::TYPE_FILE:
                if (!static::isFile($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_FILE)];
                }
                break;
            case DbTableColumn::TYPE_IMAGE:
                if (!static::isImage($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_IMAGE)];
                }
                break;
            case DbTableColumn::TYPE_EMAIL:
                if (!static::isEmail($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_EMAIL)];
                }
                break;
            case DbTableColumn::TYPE_ENUM:
            case DbTableColumn::TYPE_PASSWORD:
            case DbTableColumn::TYPE_TEXT:
            case DbTableColumn::TYPE_STRING:
                if (!is_string($value)) {
                    return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_MUST_BE_IMAGE)];
                }
                break;

        }
        // test if value is present in $column->getAllowedValues()
        $isEnum = $column->getType() === DbTableColumn::TYPE_ENUM;
        if (is_string($value) || $isEnum) {
            $allowedValues = $column->getAllowedValues();
            if (($isEnum || !empty($allowedValues)) && !in_array($value, $allowedValues, true)) {
                return [static::getErrorMessage($errorMessages, DbTableColumn::VALUE_IS_NOT_ALLOWED)];
            }
        }

        return [];
    }

    static protected function getErrorMessage(array $errorMessages, $key) {
        return array_key_exists($key, $errorMessages) ? $errorMessages[$key] : $key;
    }

    static public function normalizeValue($value, $type) {
        switch ($type) {
            case DbTableColumn::TYPE_BOOL:
                return static::normalizeBool($value);
        }
    }

    public static function getValueFormatterAndFormatsByType($type) {
        return [null, []];
    }
}