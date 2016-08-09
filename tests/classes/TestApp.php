<?php

namespace PeskyORMTest;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbConnectionsManager;

class TestApp {

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
}