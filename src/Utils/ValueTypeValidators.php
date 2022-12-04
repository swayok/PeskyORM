<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;

abstract class ValueTypeValidators
{
    // '123.00' string can be converted to integer
    public const INTEGER_REGEXP = '%^-?\d+(\.0+)?$%';
    public const FLOAT_REGEXP = '%^-?\d+(\.\d+)?$%';
    public const TIMEZONE_REGEXP = '%^([-+])?([0-1]\d|2[0-3]):[0-5]\d(:[0-6]\d)?$%';
    public const IP_ADDRESS_REGEXP = '%^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$%';
    // http://www.regular-expressions.info/email.html
    public const EMAIL_REGEXP = "%^[a-z0-9!#\$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#\$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$%i";
    // It checks values like: '[...]', '{...}', '"..."' 'true', 'false', 'null'
    // Note: numeric values should be checked by is_numeric()
    public const JSON_REGEXP = "%^(\[.*]|\{.*}|\".*\"|true|false|null)$%is";

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

    public static function isFloat(mixed $value): bool
    {
        if (is_float($value) || is_int($value)) {
            return true;
        }
        if (is_string($value)) {
            return (bool)preg_match(self::FLOAT_REGEXP, $value);
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

    public static function isTimezoneOffset(mixed $value): bool
    {
        if ($value instanceof DateTimeZone) {
            return true;
        }
        if (static::isInteger($value)) {
            return (int)$value > -86400 && (int)$value < 86400;
        }
        if (is_string($value)) {
            return (bool)preg_match(static::TIMEZONE_REGEXP, $value);
        }
        return false;
    }

    public static function isTimestamp(mixed $value): bool
    {
        if ($value instanceof CarbonInterface) {
            return $value->isValid();
        }
        if ($value instanceof DateTimeInterface) {
            return true;
        }
        if (static::isInteger($value)) {
            return (int)$value > 0;
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

    public static function isJson(mixed $value): bool
    {
        if (
            $value === null
            || is_array($value)
            || is_bool($value)
            || is_numeric($value)
        ) {
            return true;
        }
        if (is_string($value)) {
            return (bool)preg_match(self::JSON_REGEXP, $value);
        }
        return false;
    }
}