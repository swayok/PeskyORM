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
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnection = DbConnectionsManager::createConnection(
            'default',
            DbConnectionsManager::ADAPTER_POSTGRES,
            PostgresConfig::fromArray($data['pgsql'])
        );
    }

    static public function fillAdminsTable() {
        $data = include __DIR__ . '/../configs/base_db_contents.php';
        static::$dbConnection->insertMany('admins', array_keys($data['admins'][0]), $data['admins']);
    }

    static public function fillSettingsTable() {
        $data = include __DIR__ . '/../configs/base_db_contents.php';
        static::$dbConnection->insertMany('admins', array_keys($data['settings'][0]), $data['settings']);
    }

    static public function clearTables() {
        static::$dbConnection->exec('TRUNCATE TABLE settings');
        static::$dbConnection->exec('TRUNCATE TABLE admins');
    }
}