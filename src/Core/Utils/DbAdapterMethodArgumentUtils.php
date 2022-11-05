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
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a non-numeric string');
        }

        if (!$adapter->isValidDbEntityName($table)) {
            throw new \InvalidArgumentException(
                '$table must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardConditionsArg(DbExpr|string $conditions): void
    {
        if (empty($conditions)) {
            throw new \InvalidArgumentException(
                '$conditions argument is not allowed to be empty. Use "true" or "1 = 1" if you want to update all.'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardPkNameArg(DbAdapterInterface $adapter, string $pkName): void
    {
        if (empty($pkName)) {
            throw new \InvalidArgumentException('$pkName argument cannot be empty');
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
            throw new \InvalidArgumentException('$data argument cannot be empty');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardColumnsArg(array $columns, bool $allowDbExpr = true): void
    {
        if (empty($columns)) {
            throw new \InvalidArgumentException('$columns argument cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) && (!$allowDbExpr || !($column instanceof DbExpr))) {
                throw new \InvalidArgumentException(
                    '$columns argument must contain only strings' . ($allowDbExpr ? ' and DbExpr objects' : '')
                );
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardReturningArg(bool|array $returning): void
    {
        if (is_array($returning)) {
            foreach ($returning as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException(
                        '$returning argument must contain only strings'
                    );
                }
            }
        }
    }
}