<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use DateTimeZone;

abstract class ValueTypeValidators
{
    // '123.00' string can be converted to integer
    public const INTEGER_REGEXP = '%^-?\d+(\.0+)?$%';

    // Just checks format to be: '+dd:dd' or '-dd:dd'
    public const TIMEZONE_FORMAT_REGEXP = '%^([-+])(\d\d):(\d\d)$%';

    public const IP_ADDRESS_REGEXP = '%^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$%';

    // http://www.regular-expressions.info/email.html
    public const EMAIL_REGEXP = "%^[a-z0-9!#\$\%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#\$\%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$%i";

    // Checks values like: '[...]', '{...}', '"..."' 'true', 'false', 'null', 1, 2.2
    // Note: '[...,]' and '{...,}' are invalid (coma cannot be there)
    public const JSON_REGEXP = "%^(\[\s*\]|\[.*[^,]\s*\]|\{\s*\}|\{.*[^,]\s*\}|\".*\"|\d+(\.\d+)?|true|false|null)$%is";

    // Excludes '[...,]' and '{...,}' validators
    public const NOT_JSONABLE_REGEXP = "%^(\[.*\]|\{.*\}|\".*\"|\d+(\.\d+)?|true|false|null)$%is";

    // Checks if value is similar to json array: '[...]' (except '[...,]' because invalid)
    public const JSON_ARRAY_REGEXP = "%^(\[\s*\]|\[.*[^,]\s*\])$%s";

    // Checks if value is similar to json object: '{...}' (except '{...,}' because invalid)
    public const JSON_OBJECT_REGEXP = "%^(\{\s*\}|\{.*[^,]\s*\})$%s";

    public static function isInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_numeric($value)) {
            return (bool)preg_match(self::INTEGER_REGEXP, (string)$value);
        }
        return false;
    }

    public static function isBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        return in_array($value, ['1', '0', 1, 0], true);
    }

    /**
     * Check if value is valid timezone offset related to UTC.
     * Allowed values are from -12:00 to +14:00 (without any prefixes like UTC, GMT, etc.).
     * \DateTimeZone instances also allowed.
     * @see DateTimeZone
     * @see CarbonTimeZone
     */
    public static function isTimezoneOffset(mixed $value): bool
    {
        $isObject = false;
        if ($value instanceof CarbonTimeZone) {
            $value = $value->toOffsetName();
            $isObject = true;
        } elseif ($value instanceof DateTimeZone) {
            $value = $value->getName();
            $isObject = true;
        }
        if (
            !is_int($value)
            && !is_string($value)
        ) {
            return false;
        }
        if (!$isObject && static::isInteger($value)) {
            $value = (int)$value;
        }
        if (is_string($value)) {
            // validate
            if (!preg_match(static::TIMEZONE_FORMAT_REGEXP, $value, $matches)) {
                return false;
            }
            // convert to integer
            [, $sign, $hours, $minutes] = $matches;
            $value = (int)$hours * 3600 + (int)$minutes * 60;
            if ($sign === '-') {
                $value *= -1;
            }
        }
        // check if value is in minutes and in range from -12:00 to +14:00
        return (
            abs($value) % 60 === 0
            && (int)$value >= -43200
            && (int)$value <= 50400
        );
    }

    /**
     * Check if value is valid timezone name (like: "Continent/City").
     * \DateTimeZone instances allowed.
     * @see DateTimeZone
     * @see CarbonTimeZone
     */
    public static function isTimezoneName(mixed $value): bool
    {
        if ($value instanceof DateTimeZone) {
            $value = $value->getName();
        }
        if (!is_string($value)) {
            return false;
        }
        return in_array($value, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Check if value is valid timezone name or timezone offset related to UTC.
     * \DateTimeZone instances allowed.
     * @see DateTimeZone
     * @see CarbonTimeZone
     * @see self::isTimezoneOffset()
     * @see self::isTimezoneName()
     */
    public static function isTimezone(mixed $value): bool
    {
        return (
            static::isTimezoneOffset($value)
            || static::isTimezoneName($value)
        );
    }

    public static function isTimestamp(mixed $value): bool
    {
        if ($value instanceof CarbonInterface) {
            return $value->isValid();
        }
        if ($value instanceof DateTimeInterface) {
            return true;
        }
        if (is_numeric($value)) {
            return (float)$value > 0.0;
        }
        if (is_string($value)) {
            return strtotime($value) > 0;
        }
        return false;
    }

    public static function isIpV4Address(mixed $value): bool
    {
        return is_string($value) && preg_match(self::IP_ADDRESS_REGEXP, $value);
    }

    public static function isEmail(mixed $value): bool
    {
        return is_string($value) && preg_match(self::EMAIL_REGEXP, $value);
    }

    /**
     * Check if value is json-encoded string.
     * It actually does not decode contents to check if json is valid.
     * Validation is based on simple regexp that checks if string
     * potentially contains json.
     * Valid values are:
     * 'true', 'false', 'null', '1', '123', 'string', '[...]', '{...}'
     *
     * Some verified info about json encode/decode for non-array values:
     * json_encode(null) => 'null' (string)
     * json_encode(true) => 'true' (string)
     * json_encode(false) => 'false' (string)
     * json_encode(1) => '1' (string)
     * json_encode('1') => '"1"' (string)
     * json_encode(2.2) => '2.2' (string)
     * json_encode('2.2') => '"2.2"' (string)
     * json_encode('') => '""' (string)
     * json_encode('str') => '"str"' (string)
     *
     * json_decode('null') => null (null)
     * json_decode('true') => true (bool)
     * json_decode('false') => false (bool)
     * json_decode('1') => 1 (int)
     * json_decode('"1"') => '1' (string)
     * json_decode('2.2') => 2.2 (float)
     * json_decode('"2.2"') => '2.2' (string)
     * json_decode('""') => '' (empty string)
     * json_decode('"str"') => 'str' (string)
     * json_decode('') => null (null) - error
     * json_decode('str') => null (null) - error
     */
    public static function isJsonEncodedString(mixed $value): bool
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }
        return (bool)preg_match(self::JSON_REGEXP, $value);
    }

    /**
     * Check if value can be encoded to json.
     * Also checks if string value is not already encoded to json.
     * Valid values: null, bool, numbers, arrays, strings.
     * Invalid strings:
     * '[...]', '{...}', 'null', 'true', 'false', '1', '2.2', '"str"', '""'
     * You can allow or forbid objects using $allowObjects argument.
     */
    public static function isJsonable(mixed $value, bool $allowObjects): bool
    {
        if (
            $value === null
            || is_array($value)
            || is_bool($value)
            || is_int($value)
            || is_float($value)
        ) {
            return true;
        }
        if (is_object($value)) {
            return $allowObjects;
        }
        if (is_string($value)) {
            return !preg_match(self::NOT_JSONABLE_REGEXP, $value);
        }
        return false;
    }

    /**
     * Check if value is json-encoded array (no decode, only regexp check)
     * or indexed/empty array.
     * Other values are not allowed.
     * Note: it does not check values of PHP array.
     */
    public static function isJsonArray(mixed $value): bool
    {
        if (is_string($value)) {
            return (bool)preg_match(self::JSON_ARRAY_REGEXP, $value);
        }
        if (is_array($value)) {
            return static::isIndexedArray($value);
        }
        return false;
    }

    /**
     * Check if value is json-encoded object (no decode, only regexp check)
     * or associative/empty array.
     * Other values are not allowed.
     * Note: object must be converted to array before calling this function.
     */
    public static function isJsonObject(mixed $value): bool
    {
        if (is_string($value)) {
            return (bool)preg_match(self::JSON_OBJECT_REGEXP, $value);
        }
        if (is_array($value)) {
            return static::isAssociativeArray($value);
        }
        return false;
    }

    /**
     * Check if all keys are integers.
     * Note: it does not check if keys are sequential or not.
     * Indexed arrays: ['a', 'b', 'c'], [0 => 'a', 1 => 'b'],
     * [2 => 'a', 4 => 'b', 0 => 'c'], ['0' => 'a', '2' => 'b', 3 => 'c'].
     * PHP converts keys '1', '2', '30' to integers.
     */
    public static function isIndexedArray(mixed $value): bool
    {
        // null values are not allowed
        if (!is_array($value)) {
            return false;
        }
        if (empty($value)) {
            return true;
        }

        for (reset($value); key($value) !== null; next($value)) {
            if (!is_int(key($value))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if array is associative.
     * Returns true if at least 1 key in array is not integer.
     */
    public static function isAssociativeArray(mixed $value): bool
    {
        // null values are not allowed
        if (!is_array($value)) {
            return false;
        }
        if (empty($value)) {
            return true;
        }

        for (reset($value); key($value) !== null; next($value)) {
            if (!is_int(key($value))) {
                return true;
            }
        }
        return false;
    }
}