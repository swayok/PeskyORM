<?php

namespace PeskyORMTest;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbConnectionsManager;

class TestingApp {

    /**
     * @var Postgres
     */
    static protected $dbConnection;

    static public function init() {
        static::$dbConnection = DbConnectionsManager::createConnection(
            'default',
            DbConnectionsManager::ADAPTER_POSTGRES,
            PostgresConfig::fromArray(static::getGlobalConfigs()['pgsql'])
        );
    }

    static protected function getGlobalConfigs() {
        return include __DIR__ . '/../../configs/global.php';
    }

    static protected function getRecordsForDb($table) {
        $data = include __DIR__ . '/../../configs/base_db_contents.php';
        return $data[$table];
    }

    static public function fillAdminsTable() {
        $data = static::getRecordsForDb('admins');
        static::$dbConnection->insertMany('admins', array_keys($data[0]), $data);
    }

    static public function fillSettingsTable() {
        $data = static::getRecordsForDb('settings');
        static::$dbConnection->insertMany('admins', array_keys($data[0]), $data);
    }

    static public function clearTables() {
        static::$dbConnection->exec('TRUNCATE TABLE settings');
        static::$dbConnection->exec('TRUNCATE TABLE admins');
    }
}