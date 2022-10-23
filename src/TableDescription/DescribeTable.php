<?php

namespace PeskyORM\TableDescription;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescribers\TableDescriberInterface;

abstract class DescribeTable
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
        if (!isset(static::$describers[$dbAdapter::class])) {
            throw new \InvalidArgumentException('There are no table describer for ' . $dbAdapter::class . ' adapter');
        }
        /** @var TableDescriberInterface $describerClass */
        $describerClass = static::$describers[$dbAdapter::class];
        return new $describerClass($dbAdapter);
    }

    public static function getTableDescription(
        DbAdapterInterface $dbAdapter,
        string $tableName,
        ?string $schemaName = null
    ): TableDescription {
        return static::getDescriber($dbAdapter)->getTableDescription($tableName, $schemaName);
    }
}