<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescribers\TableDescriberInterface;

abstract class TableDescribersRegistry
{
    static protected array $describers = [
        Mysql::class => MysqlTableDescriber::class,
        Postgres::class => PostgresTableDescriber::class,
    ];

    public static function registerDescriber(string $dbAdapterClass, string $describerClass): void
    {
        static::$describers[$dbAdapterClass] = $describerClass;
    }

    public static function getDescriber(DbAdapterInterface $dbAdapter): TableDescriberInterface
    {
        if (isset(static::$describers[$dbAdapter::class])) {
            $describerClass = static::$describers[$dbAdapter::class];
            return new $describerClass($dbAdapter);
        }
        // test if subclass of known adapters
        foreach (static::$describers as $adapterClass => $describerClass) {
            if (is_subclass_of($dbAdapter, $adapterClass)) {
                return new $describerClass($dbAdapter);
            }
        }
        throw new \InvalidArgumentException('There are no table describer for ' . $dbAdapter::class . ' adapter');
    }

    public static function describeTable(
        DbAdapterInterface $dbAdapter,
        string $tableName,
        ?string $schemaName = null
    ): TableDescription {
        return static::getDescriber($dbAdapter)->getTableDescription($tableName, $schemaName);
    }
}