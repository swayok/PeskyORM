<?php

namespace PeskyORMTest;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\DbTable;
use PeskyORM\ORM\DbTableStructure;

class TestingApp {

    /**
     * @var Postgres
     */
    static public $dbConnection;
    static protected $dataForDb;
    static protected $dataForDbMinimal;

    static public function init() {
        if (!static::$dbConnection) {
            static::$dbConnection = DbConnectionsManager::createConnection(
                'default',
                DbConnectionsManager::ADAPTER_POSTGRES,
                PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
            );
        }
    }

    static protected function getGlobalConfigs() {
        return include __DIR__ . '/../../configs/global.php';
    }

    static public function getRecordsForDb($table, $limit = 0) {
        if ($limit === 0 || $limit <= 10) {
            if (!static::$dataForDbMinimal) {
                static::$dataForDbMinimal = include __DIR__ . '/../../configs/minimal_db_contents.php';
            }
            $records = static::$dataForDbMinimal;
        } else {
            if (!static::$dataForDb) {
                static::$dataForDb = include __DIR__ . '/../../configs/base_db_contents.php';
            }
            $records = static::$dataForDb;
        }
        if ($limit > 0) {
            return array_slice($records[$table], 0, $limit);
        } else {
            return $records[$table];
        }
    }

    static public function fillAdminsTable($limit = 0) {
        $data = static::getRecordsForDb('admins', $limit);
        static::$dbConnection->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }

    static public function fillSettingsTable($limit = 0) {
        $data = static::getRecordsForDb('settings', $limit);
        static::$dbConnection->insertMany('settings', array_keys($data[0]), $data);
        return $data;
    }

    static public function clearTables() {
        static::$dbConnection->exec('TRUNCATE TABLE settings');
        static::$dbConnection->exec('TRUNCATE TABLE admins');
    }

    static public function cleanInstancesOfDbTablesAndStructures() {
        $class = new \ReflectionClass(DbTable::class);
        $method = $class->getMethod('resetInstances');
        $method->setAccessible(true);
        $method->invoke(null);
        $method->setAccessible(false);

        $class = new \ReflectionClass(DbTableStructure::class);
        $method = $class->getMethod('resetInstances');
        $method->setAccessible(true);
        $method->invoke(null);
        $method->setAccessible(false);
    }
}