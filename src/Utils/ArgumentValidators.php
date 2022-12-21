<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;

abstract class ArgumentValidators
{
    public static function assertNotEmpty(
        string $argName,
        mixed $value,
        string $messageSuffix = ''
    ): void {
        if (empty($value)) {
            throw new \InvalidArgumentException(
                "{$argName} argument value cannot be empty.{$messageSuffix}"
            );
        }
    }

    public static function assertNotEmptyString(
        string $argName,
        mixed $value,
        bool $trim
    ): void {
        if (!is_string($value) || ($trim ? trim($value) : $value) === '') {
            throw new \InvalidArgumentException(
                "{$argName} argument value must be a not-empty string."
                . (!is_string($value) ? ' ' . static::getValueInfoForException($value) : '')
            );
        }
    }

    public static function assertNullOrNotEmptyString(
        string $argName,
        mixed $value
    ): void {
        if ($value === null) {
            return;
        }
        static::assertNotEmptyString($argName, $value, true);
    }

    public static function assertArrayHasKey(
        string $argName,
        array $array,
        string|int|float|bool $key
    ): void {
        if (!array_key_exists($key, $array)) {
            throw new \InvalidArgumentException(
                "Array {$argName} has no key {$key}."
            );
        }
    }

    public static function assertArrayKeyValueIsNotEmpty(
        string $arrayKeyPath,
        mixed $value
    ): void {
        if (empty($value)) {
            throw new \InvalidArgumentException(
                "$arrayKeyPath: value cannot be empty."
            );
        }
    }

    public static function assertArrayKeyValueIsNotEmptyString(
        string $arrayKeyPath,
        mixed $value,
        bool $trim
    ): void {
        if (!is_string($value) || ($trim ? trim($value) : $value) === '') {
            throw new \InvalidArgumentException(
                "{$arrayKeyPath}: value must be a not-empty string."
                . (!is_string($value) ? ' ' . static::getValueInfoForException($value) : '')
            );
        }
    }

    public static function assertArrayKeyValueIsArray(
        string $arrayKeyPath,
        mixed $value
    ): void {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                "$arrayKeyPath: value must be an array. "
                . static::getValueInfoForException($value)
            );
        }
    }

    public static function assertArrayKeyValueIsStringOrArray(
        string $arrayKeyPath,
        mixed $value
    ): void {
        if (!is_string($value) && !is_array($value)) {
            throw new \InvalidArgumentException(
                "$arrayKeyPath: value must be a string or array. "
                . static::getValueInfoForException($value)
            );
        }
    }

    public static function assertArrayKeyValueIsString(
        string $arrayKeyPath,
        mixed $value
    ): void {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                "$arrayKeyPath: value must be a string. "
                . static::getValueInfoForException($value)
            );
        }
    }

    public static function assertArrayKeyValueIsStringOrDbExpr(
        string $arrayKeyPath,
        mixed $value
    ): void {
        if (!is_string($value) && !($value instanceof DbExpr)) {
            throw new \InvalidArgumentException(
                "$arrayKeyPath: value must be a string or instance of " . DbExpr::class
                . '. ' . static::getValueInfoForException($value)
            );
        }
    }

    public static function assertPositiveInteger(
        string $argName,
        int|string $value,
        bool $allowZero
    ): void {
        $minValue = $allowZero ? 0 : 1;
        if (!is_numeric($value) || (int)$value < $minValue) {
            throw new \InvalidArgumentException(
                "{$argName} argument value ({$value}) must be a positive integer"
                . ($allowZero ? ' or 0' : '')
            );
        }
    }

    public static function assertPascalCase(string $argName, string $value): void
    {
        if (!StringUtils::isPascalCase($value)) {
            throw new \InvalidArgumentException(
                "$argName argument value ({$value}) has invalid format."
                . ' Expected naming pattern: ' . StringUtils::PASCAL_CASE_VALIDATION_REGEXP . '.'
                . ' Example: PascalCase1.'
            );
        }
    }

    public static function assertSnakeCase(string $argName, string $value): void
    {
        if (!StringUtils::isSnakeCase($value)) {
            throw new \InvalidArgumentException(
                "{$argName} argument value ({$value}) has invalid format."
                . ' Expected naming pattern: ' . StringUtils::SNAKE_CASE_VALIDATION_REGEXP . '.'
                . ' Example: snake_case1.'
            );
        }
    }

    public static function assertInArray(
        string $argName,
        string $value,
        array $allowedValues
    ): void {
        if (!in_array($value, $allowedValues, true)) {
            throw new \InvalidArgumentException(
                "{$argName} argument value ({$value}) must be one of: "
                . implode(', ', $allowedValues)
            );
        }
    }

    /**
     * @param bool $mayContainAlias - true: $argName value can be like 'name AS Alias'
     */
    public static function assertValidDbEntityName(
        string $argName,
        string $value,
        bool $mayContainAlias = false,
        ?DbAdapterInterface $adapter = null
    ): void {
        static::assertNotEmptyString($argName, $value, true);

        if ($mayContainAlias) {
            $parts = preg_split('%\s*AS\s*%i', $value, 2);
            if (!static::isValidDbEntityName($parts[0], $adapter)) {
                throw new \InvalidArgumentException(
                    "{$argName}[name] argument value ({$value}) must be a string that matches"
                    . ' DB entity naming rules (usually alphanumeric with underscores).'
                );
            }
            if (
                isset($parts[1])
                && !static::isValidDbEntityName($parts[0], $adapter)
            ) {
                throw new \InvalidArgumentException(
                    "{$argName}[alias] argument value ({$value}) must be a string that matches"
                    . ' DB entity naming rules (usually alphanumeric with underscores).'
                );
            }
        } elseif (!static::isValidDbEntityName($value, $adapter)) {
            throw new \InvalidArgumentException(
                "{$argName} argument value ({$value}) must be a string that matches"
                . ' DB entity naming rules (usually alphanumeric with underscores).'
            );
        }
    }

    public static function assertClassImplementsInterface(
        string $argName,
        string $class,
        string $interfaceClass
    ): void {
        if (!is_subclass_of($class, $interfaceClass)) {
            throw new \InvalidArgumentException(
                "{$argName} argument value must be a class that implements "
                . $interfaceClass
            );
        }
    }

    private static function isValidDbEntityName(string $value, ?DbAdapterInterface $adapter): bool
    {
        return $adapter
            ? $adapter->isValidDbEntityName($value)
            : PdoUtils::isValidDbEntityName($value);
    }

    public static function getValueInfoForException(mixed $value): string
    {
        if (is_object($value)) {
            return 'Instance of ' . get_class($value) . ' class received.';
        }
        $type = gettype($value);
        $message = "Value of $type type received";
        if (!is_resource($value) && !in_array($type, ['NULL', 'unknown type', 'boolean'], true)) {
            $message .= ': ';
            if (is_array($value)) {
                $message .= json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $message .= $value;
            }
        }
        return $message . '.';
    }


}