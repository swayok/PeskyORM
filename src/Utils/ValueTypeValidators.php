<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

abstract class ValueTypeValidators
{
    // '123.00' string can be converted to integer
    public const INTEGER_REGEXP = '%^-?\d+(\.0+)?$%';

    public static function isInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (
            is_numeric($value)
            && preg_match(self::INTEGER_REGEXP, (string)$value)
        ) {
            return true;
        }

        return false;
    }
}