<?php

require_once __DIR__ . '/PostgresAdapterHelpersTest.php';

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;

class MysqlAdapterHelpersTest extends PostgresAdapterHelpersTest {

    /** @var MysqlConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = MysqlConfig::fromArray($data['pgsql']);
    }

    static public function tearDownAfterClass() {
        static::$dbConnectionConfig = null;
    }

    static protected function getValidAdapter() {
        return new Mysql(static::$dbConnectionConfig);
    }
}