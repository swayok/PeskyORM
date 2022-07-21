<?php

namespace Tests\PeskyORMTest;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;

class TestingApp {

    /**
     * @var Postgres
     */
    static public $pgsqlConnection;
    /**
     * @var Mysql
     */
    static public $mysqlConnection;
    static protected $dataForDb;
    static protected $dataForDbMinimal;

    static public function getMysqlConnection() {
        if (!static::$mysqlConnection) {
            static::$mysqlConnection = DbConnectionsManager::createConnection(
                'mysql',
                DbConnectionsManager::ADAPTER_MYSQL,
                MysqlConfig::fromArray(static::getGlobalConfigs()['mysql'])
            );
            static::getMysqlConnection()->exec(file_get_contents(__DIR__ . '/../../configs/db_schema_mysql.sql'));
            date_default_timezone_set('UTC');
        }
        return static::$mysqlConnection;
    }

    static public function getPgsqlConnection() {
        if (!static::$pgsqlConnection) {
            static::$pgsqlConnection = DbConnectionsManager::createConnection(
                'default',
                DbConnectionsManager::ADAPTER_POSTGRES,
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
            );
            DbConnectionsManager::addAlternativeNameForConnection('default', 'writable');
            static::getPgsqlConnection()->exec(file_get_contents(__DIR__ . '/../../configs/db_schema_pgsql.sql'));
            static::$pgsqlConnection->query('SET LOCAL TIME ZONE "UTC"');
            date_default_timezone_set('UTC');
        }
        static::$pgsqlConnection->rememberTransactionQueries = true;
        return static::$pgsqlConnection;
    }

    static protected function getGlobalConfigs() {
        return include __DIR__ . '/../configs/global.php';
    }

    static public function getRecordsForDb($table, $limit = 0) {
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

    static public function fillAdminsTable($limit = 0): array {
        static::$pgsqlConnection->exec('TRUNCATE TABLE admins');
        $data = static::getRecordsForDb('admins', $limit);
        static::$pgsqlConnection->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }

    static public function fillSettingsTable($limit = 0): array {
        static::$pgsqlConnection->exec('TRUNCATE TABLE settings');
        $data = static::getRecordsForDb('settings', $limit);
        static::$pgsqlConnection->insertMany('settings', array_keys($data[0]), $data);
        return $data;
    }

    static public function clearTables(DbAdapterInterface $adapter) {
        if ($adapter->inTransaction()) {
            $adapter->rollBack();
        }
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }

    static public function cleanInstancesOfDbTablesAndStructures() {
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
    }
}