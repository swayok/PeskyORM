<?php

declare(strict_types=1);

namespace PeskyORM\Core\Utils;

use PeskyORM\Core\DbExpr;

abstract class ArgumentValidators
{
    public static function assertNotEmpty(string $argName, mixed $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("{$argName} argument value cannot be empty");
        }
    }

    public static function assertNotEmptyString(string $argName, mixed $value, bool $trim): void
    {
        if (!is_string($value) || ($trim ? trim($value) : $value) === '') {
            throw new \InvalidArgumentException("{$argName} argument value must be a not-empty string");
        }
    }

    public static function assertNullOrNotEmptyString(string $argName, ?string $value): void
    {
        if ($value !== null && empty($value)) {
            throw new \InvalidArgumentException("{$argName} argument value must be a not-empty string or null");
        }
    }

    public static function assertArrayKeyValueIsNotEmpty(string $arrayKeyPath, mixed $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("$arrayKeyPath: value cannot be empty");
        }
    }

    public static function assertArrayKeyValueIsArray(string $arrayKeyPath, mixed $value): void
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("$arrayKeyPath: value must be an array");
        }
    }

    public static function assertArrayKeyValueIsStringOrArray(string $arrayKeyPath, mixed $value): void
    {
        if (!is_string($value) && !is_array($value)) {
            throw new \InvalidArgumentException("$arrayKeyPath: value must be a string or array");
        }
    }

    public static function assertArrayKeyValueIsStringOrDbExpr(string $arrayKeyPath, mixed $value): void
    {
        if (!is_string($value) && !($value instanceof DbExpr)) {
            throw new \InvalidArgumentException("$arrayKeyPath: value must be a string or instance of " . DbExpr::class);
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

    public static function assertPascalCase(string $argName, string $value): void
    {
        if (!StringUtils::isPascalCase($value)) {
            throw new \InvalidArgumentException(
                "$argName argument contains invalid value: '$value'."
                . ' Expected naming pattern: ' . StringUtils::PASCAL_CASE_VALIDATION_REGEXP . '.'
                . ' Example: PascalCase1.'
            );
        }
    }

    public static function assertSnakeCase(string $argName, string $value): void
    {
        if (!StringUtils::isSnakeCase($value)) {
            throw new \InvalidArgumentException(
                "$argName argument contains invalid value: '$value'."
                . ' Expected naming pattern: ' . StringUtils::SNAKE_CASE_VALIDATION_REGEXP . '.'
                . ' Example: snake_case1.'
            );
        }
    }
}