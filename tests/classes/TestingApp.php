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

    static public function getDefautConnection() {
        if (!static::$dbConnection) {
            $data = include __DIR__ . '/../configs/global.php';
            static::$dbConnection = DbConnectionsManager::createConnection(
                'default',
                DbConnectionsManager::ADAPTER_POSTGRES,
                PostgresConfig::fromArray($data['pgsql'])
            );
        }
        return static::$dbConnection;
    }

    static public function fillAdminsTable() {
        $data = include __DIR__ . '/../configs/base_db_contents.php';
        static::getDefautConnection()->insertMany('admins', array_keys($data['admins'][0]), $data['admins']);
    }

    static public function fillSettingsTable() {
        $data = include __DIR__ . '/../configs/base_db_contents.php';
        static::getDefautConnection()->insertMany('admins', array_keys($data['settings'][0]), $data['settings']);
    }

    static public function clearTables() {
        static::getDefautConnection()->exec('TRUNCATE TABLE settings');
        static::getDefautConnection()->exec('TRUNCATE TABLE admins');
    }
}