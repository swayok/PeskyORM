<?php

require_once __DIR__ . '/PostgresAdapterDeleteDataTest.php';

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;

class MysqlAdapterDeleteTest extends PostgresAdapterDeleteTest {

    /** @var MysqlConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = MysqlConfig::fromArray($data['mysql']);
        static::cleanTables();
    }

    static protected function getValidAdapter() {
        return new Mysql(static::$dbConnectionConfig);
    }

}
