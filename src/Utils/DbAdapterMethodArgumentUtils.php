<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;

abstract class DbAdapterMethodArgumentUtils
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function guardTableNameArg(DbAdapterInterface $adapter, string $table): void
    {
        ArgumentValidators::assertValidDbEntityName('$table', $table, true, $adapter);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardConditionsArg(DbExpr|array $conditions): void
    {
        ArgumentValidators::assertNotEmpty(
            '$conditions',
            $conditions,
            " Use DbExpr('true') or DbExpr('1 = 1')"
            . ' if you want to perform action all records in table.'
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardPkNameArg(DbAdapterInterface $adapter, string $pkName): void
    {
        ArgumentValidators::assertValidDbEntityName('$pkName', $pkName, false, $adapter);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardDataArg(string $argName, array $data): void
    {
        ArgumentValidators::assertNotEmpty($argName, $data);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardColumnsListArg(
        array $columns,
        bool $allowDbExpr = true,
        bool $canBeEmpty = false
    ): void {
        if (!$canBeEmpty) {
            ArgumentValidators::assertNotEmpty('$columns', $columns);
        }
        foreach ($columns as $index => $column) {
            ArgumentValidators::assertArrayKeyValueIsNotEmpty("\$columns[{$index}]", $column);
            if ($allowDbExpr) {
                ArgumentValidators::assertArrayKeyValueIsStringOrDbExpr("\$columns[{$index}]", $column);
            } else {
                ArgumentValidators::assertArrayKeyValueIsString("\$columns[{$index}]", $column);
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function guardReturningArg(bool|array $returning): void
    {
        if (is_array($returning)) {
            foreach ($returning as $index => $column) {
                ArgumentValidators::assertArrayKeyValueIsNotEmptyString("\$returning[{$index}]", $column, true);
            }
        }
    }
}