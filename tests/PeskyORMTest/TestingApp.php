<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Tests\PeskyORMTest\Adapter\MysqlTesting;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;
use PeskyORM\Utils\ServiceContainer;

class TestingApp
{
    protected static bool $connectionsConfigured = false;
    public static ?PostgresTesting $pgsqlConnection = null;
    public static bool $pgsqlConnectionInitiated = false;
    public static ?MysqlTesting $mysqlConnection = null;
    public static bool $mysqlConnectionInitiated = false;
    protected static ?array $dataForDb = null;
    protected static ?array $dataForDbMinimal = null;

    public static function configureConnections(bool $force = false): void
    {
        if (!static::$connectionsConfigured || $force) {
            DbConnectionsFacade::registerAdapter(
                ServiceContainer::MYSQL,
                MysqlTesting::class,
            );
            DbConnectionsFacade::registerConnectionConfigClass(
                ServiceContainer::MYSQL,
                MysqlConfig::class
            );
            DbConnectionsFacade::registerConnection(
                'mysql',
                ServiceContainer::MYSQL,
                MysqlConfig::fromArray(static::getGlobalConfigs()['mysql'])
            );

            DbConnectionsFacade::registerAdapter(
                ServiceContainer::POSTGRES,
                PostgresTesting::class,
            );
            DbConnectionsFacade::registerConnectionConfigClass(
                ServiceContainer::POSTGRES,
                PostgresConfig::class
            );
            DbConnectionsFacade::registerConnection(
                'default',
                ServiceContainer::POSTGRES,
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
            );
            DbConnectionsFacade::registerAliasForConnection('default', 'writable');

            date_default_timezone_set('UTC');
            static::$connectionsConfigured = true;
        }
    }

    public static function getMysqlConnection(bool $reuseExisting = true): MysqlTesting
    {
        if (!$reuseExisting) {
            static::$mysqlConnection?->disconnect();
            static::$mysqlConnection = null;
        }
        if (!static::$mysqlConnection) {
            static::$mysqlConnection = new MysqlTesting(
                MysqlConfig::fromArray(static::getGlobalConfigs()['mysql']),
                ServiceContainer::MYSQL
            );
            if (!static::$mysqlConnectionInitiated) {
                date_default_timezone_set('UTC');
                static::$mysqlConnection->exec(file_get_contents(__DIR__ . '/../configs/db_schema_mysql.sql'));
                static::$mysqlConnectionInitiated = true;
            }
        }
        if (
            static::$mysqlConnection->isConnected()
            && get_class(static::$mysqlConnection->getConnection()) !== \PDO::class
        ) {
            throw new \UnexpectedValueException('$mysqlConnection is not pure \PDO connection');
        }
        return static::$mysqlConnection;
    }

    public static function getPgsqlConnection(bool $reuseExisting = true): PostgresTesting
    {
        if (!$reuseExisting) {
            static::$pgsqlConnection?->disconnect();
            static::$pgsqlConnection = null;
        }
        if (!static::$pgsqlConnection) {
            static::$pgsqlConnection = new PostgresTesting(
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql']),
                ServiceContainer::POSTGRES
            );
            static::$pgsqlConnection->setTimezone('UTC');
            if (!static::$pgsqlConnectionInitiated) {
                date_default_timezone_set('UTC');
                static::$pgsqlConnection->exec(file_get_contents(__DIR__ . '/../configs/db_schema_pgsql.sql'));
                // for ORM tests
                static::$pgsqlConnectionInitiated = true;
            }
        }
        return static::$pgsqlConnection;
    }

    public static function resetConnections(): void
    {
        $connection = static::getPgsqlConnection(false);
        if (get_class($connection->getConnection()) !== \PDO::class) {
            throw new \UnexpectedValueException('$pgsqlConnection is not pure \PDO connection');
        }
        $connection = static::getMysqlConnection(false);
        if (get_class($connection->getConnection()) !== \PDO::class) {
            throw new \UnexpectedValueException('$mysqlConnection is not pure \PDO connection');
        }
    }

    protected static function getGlobalConfigs(): array
    {
        return include __DIR__ . '/../configs/global.php';
    }

    public static function getRecordsForDb(string $table, int $limit = 0): array
    {
        if ($limit <= 10) {
            if (!static::$dataForDbMinimal) {
                static::$dataForDbMinimal = include __DIR__ . '/../configs/minimal_db_contents.php';
            }
            $records = static::$dataForDbMinimal;
        } else {
            if (!static::$dataForDb) {
                static::$dataForDb = include __DIR__ . '/../configs/base_db_contents.php';
            }
            $records = static::$dataForDb;
        }
        if ($limit > 0) {
            return array_slice($records[$table], 0, $limit);
        }

        return $records[$table];
    }

    public static function clearTables(DbAdapterInterface $adapter): void
    {
        if ($adapter->inTransaction()) {
            $adapter->rollBack();
        }
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }

    public static function resetServiceContainer(): void
    {
        ServiceContainer::replaceContainer(null);
        static::configureConnections(true);
    }

    public static function fillAdminsTable(
        DbAdapterInterface $connection,
        int $limit = 0,
        ?array $records = null,
    ): array {
        static::clearTables($connection);
        if (!$records) {
            $records = static::getRecordsForDb('admins', $limit);
        } else if ($limit > 0) {
            $records = array_slice($records, 0, $limit);
        }
        $connection->insertMany(
            'admins',
            array_keys($records[0]),
            $records
        );
        return $records;
    }
}