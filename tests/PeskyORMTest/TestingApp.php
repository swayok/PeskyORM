<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest;

use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;
use PeskyORM\Tests\PeskyORMTest\Adapter\MysqlTesting;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;

class TestingApp
{
    
    public static ?PostgresTesting $pgsqlConnection = null;
    public static ?MysqlTesting $mysqlConnection = null;
    protected static ?array $dataForDb = null;
    protected static ?array $dataForDbMinimal = null;
    
    public static function getMysqlConnection(): MysqlTesting
    {
        if (!static::$mysqlConnection) {
            DbConnectionsManager::addAdapter(DbConnectionsManager::ADAPTER_MYSQL, MysqlTesting::class);
            /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
            static::$mysqlConnection = DbConnectionsManager::createConnection(
                'mysql',
                DbConnectionsManager::ADAPTER_MYSQL,
                MysqlConfig::fromArray(static::getGlobalConfigs()['mysql'])
            );
            static::$mysqlConnection->exec(file_get_contents(__DIR__ . '/../configs/db_schema_mysql.sql'));
            date_default_timezone_set('UTC');
        }
        return static::$mysqlConnection;
    }
    
    public static function getPgsqlConnection(): PostgresTesting
    {
        if (!static::$pgsqlConnection) {
            DbConnectionsManager::addAdapter(DbConnectionsManager::ADAPTER_POSTGRES, PostgresTesting::class);
            /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
            static::$pgsqlConnection = DbConnectionsManager::createConnection(
                'default',
                DbConnectionsManager::ADAPTER_POSTGRES,
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
            );
            DbConnectionsManager::addAlternativeNameForConnection('default', 'writable');
            static::$pgsqlConnection->exec(file_get_contents(__DIR__ . '/../configs/db_schema_pgsql.sql'));
            static::$pgsqlConnection->query('SET LOCAL TIME ZONE "UTC"');
            date_default_timezone_set('UTC');
        }
        static::$pgsqlConnection->rememberTransactionQueries = true;
        return static::$pgsqlConnection;
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
        } else {
            return $records[$table];
        }
    }
    
    public static function fillAdminsTable(int $limit = 0): array
    {
        static::$pgsqlConnection->exec('TRUNCATE TABLE admins');
        $data = static::getRecordsForDb('admins', $limit);
        static::$pgsqlConnection->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }
    
    public static function fillSettingsTable(int $limit = 0): array
    {
        static::$pgsqlConnection->exec('TRUNCATE TABLE settings');
        $data = static::getRecordsForDb('settings', $limit);
        static::$pgsqlConnection->insertMany('settings', array_keys($data[0]), $data);
        return $data;
    }
    
    public static function clearTables(DbAdapterInterface $adapter): void
    {
        if ($adapter->inTransaction()) {
            $adapter->rollBack();
        }
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }
    
    public static function cleanInstancesOfDbTablesAndRecordsAndStructures(): void
    {
        $class = new \ReflectionClass(Table::class);
        $method = $class->getMethod('resetInstances');
        $method->setAccessible(true);
        $method->invoke(null);
        $method->setAccessible(false);
        
        $class = new \ReflectionClass(TableStructure::class);
        $method = $class->getMethod('resetInstances');
        $method->setAccessible(true);
        $method->invoke(null);
        $method->setAccessible(false);
    
        $class = new \ReflectionClass(Record::class);
        $method = $class->getMethod('resetColumnsCache');
        $method->setAccessible(true);
        $method->invoke(null);
        $method->setAccessible(false);
    }
}