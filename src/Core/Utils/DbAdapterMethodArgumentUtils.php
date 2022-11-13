<?php

declare(strict_types=1);

namespace PeskyORM\Core\Utils;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;

abstract class DbAdapterMethodArgumentUtils
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function guardTableNameArg(DbAdapterInterface $adapter, string $table): void
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a non-numeric string.');
        }

        if (!$adapter->isValidDbEntityName($table)) {
            throw new \InvalidArgumentException(
                '$table name must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardConditionsArg(DbExpr|array $conditions): void
    {
        if (empty($conditions)) {
            throw new \InvalidArgumentException(
                '$conditions argument is not allowed to be empty.'
                . " Use DbExpr('true') or DbExpr('1 = 1')"
                . ' if you want to perform action all records in table.'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardPkNameArg(DbAdapterInterface $adapter, string $pkName): void
    {
        if (empty($pkName)) {
            throw new \InvalidArgumentException('$pkName argument cannot be empty.');
        }

        if (!$adapter->isValidDbEntityName($pkName)) {
            throw new \InvalidArgumentException(
                '$pkName must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardDataArg(array $data): void
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('$data argument cannot be empty.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardColumnsListArg(
        array $columns,
        bool $allowDbExpr = true,
        bool $canBeEmpty = false
    ): void {
        if (empty($columns)) {
            if ($canBeEmpty) {
                return;
            }
            throw new \InvalidArgumentException('$columns argument cannot be empty.');
        }
        $expectation = '. String' . ($allowDbExpr ? ' or instance of ' . DbExpr::class : '') . ' expected.';
        foreach ($columns as $index => $column) {
            if (empty($column)) {
                throw new \InvalidArgumentException(
                    "\$columns[{$index}]: value cannot be empty."
                );
            }
            if (is_string($column) || ($allowDbExpr && $column instanceof DbExpr)) {
                continue;
            }
            if (is_object($column)) {
                throw new \InvalidArgumentException(
                    "\$columns[{$index}]: value cannot be instance of " . get_class($column) . $expectation
                );
            }
            throw new \InvalidArgumentException(
                "\$columns[{$index}]: value cannot be of type " . gettype($column) . $expectation
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardReturningArg(bool|array $returning): void
    {
        if (is_array($returning)) {
            $expectation = '. String expected.';
            foreach ($returning as $index => $column) {
                if (empty($column)) {
                    throw new \InvalidArgumentException(
                        "\$returning[{$index}]: value cannot be empty."
                    );
                }
                if (is_string($column)) {
                    continue;
                }
                if (is_object($column)) {
                    throw new \InvalidArgumentException(
                        "\$returning[{$index}]: value cannot be instance of " . get_class($column) . $expectation
                    );
                }
                throw new \InvalidArgumentException(
                    "\$returning[{$index}]: value cannot be of type " . gettype($column) . $expectation
                );
            }
        }
    }
}