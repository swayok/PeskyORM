<?php

namespace PeskyORM\Tests\PeskyORMTest;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;

class TestingApp
{
    
    /**
     * @var Postgres
     */
    public static $pgsqlConnection;
    /**
     * @var Mysql
     */
    public static $mysqlConnection;
    protected static $dataForDb;
    protected static $dataForDbMinimal;
    
    public static function getMysqlConnection()
    {
        if (!static::$mysqlConnection) {
            static::$mysqlConnection = DbConnectionsManager::createConnection(
                'mysql',
                DbConnectionsManager::ADAPTER_MYSQL,
                MysqlConfig::fromArray(static::getGlobalConfigs()['mysql'])
            );
            static::getMysqlConnection()
                ->exec(file_get_contents(__DIR__ . '/../configs/db_schema_mysql.sql'));
            date_default_timezone_set('UTC');
        }
        return static::$mysqlConnection;
    }
    
    public static function getPgsqlConnection()
    {
        if (!static::$pgsqlConnection) {
            static::$pgsqlConnection = DbConnectionsManager::createConnection(
                'default',
                DbConnectionsManager::ADAPTER_POSTGRES,
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
            );
            DbConnectionsManager::addAlternativeNameForConnection('default', 'writable');
            static::getPgsqlConnection()
                ->exec(file_get_contents(__DIR__ . '/../configs/db_schema_pgsql.sql'));
            static::$pgsqlConnection->query('SET LOCAL TIME ZONE "UTC"');
            date_default_timezone_set('UTC');
        }
        static::$pgsqlConnection->rememberTransactionQueries = true;
        return static::$pgsqlConnection;
    }
    
    protected static function getGlobalConfigs()
    {
        return include __DIR__ . '/../configs/global.php';
    }
    
    public static function getRecordsForDb($table, $limit = 0)
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
    
    public static function fillAdminsTable($limit = 0): array
    {
        static::$pgsqlConnection->exec('TRUNCATE TABLE admins');
        $data = static::getRecordsForDb('admins', $limit);
        static::$pgsqlConnection->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }
    
    public static function fillSettingsTable($limit = 0): array
    {
        static::$pgsqlConnection->exec('TRUNCATE TABLE settings');
        $data = static::getRecordsForDb('settings', $limit);
        static::$pgsqlConnection->insertMany('settings', array_keys($data[0]), $data);
        return $data;
    }
    
    public static function clearTables(DbAdapterInterface $adapter)
    {
        if ($adapter->inTransaction()) {
            $adapter->rollBack();
        }
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }
    
    public static function cleanInstancesOfDbTablesAndRecordsAndStructures()
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