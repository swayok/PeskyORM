<?php

declare(strict_types=1);

namespace PeskyORM\Core\Utils;

abstract class ArgumentValidators
{
    public static function assertNullOrNotEmptyString(string $argName, ?string $value): void
    {
        if ($value !== null && empty($value)) {
            throw new \InvalidArgumentException("{$argName} argument value must be a not-empty string or null");
        }
    }

    public static function assertNotEmpty(string $argName, mixed $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("{$argName} argument value cannot be empty");
        }
    }

    public static function assertArrayKeyValueIsArray(string $arrayKeyPath, mixed $value): void
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("$arrayKeyPath: value must be an array");
        }
    }

    public static function assertPositiveInteger(string $argName, int $value, bool $allowZero): void
    {
        $minValue = $allowZero ? 0 : 1;
        if ($value < $minValue) {
            throw new \InvalidArgumentException(
                "$argName argument value must be a positive integer" . ($allowZero ? ' or 0' : '')
            );
        }
    }
}