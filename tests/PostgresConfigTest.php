<?php

class PostgresConfigTest extends PHPUnit_Framework_TestCase {

    static protected $globalConfigs;

    static public function setUpBeforeClass() {
        self::$globalConfigs = include __DIR__ . '/configs/global.php';
    }

    public static function tearDownAfterClass() {
        self::$globalConfigs = null;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument
     */
    public function testInvalidConfigs() {
        new \PeskyORM\Config\Connection\PostgresConfig(null, null, null, null, null);
        //$config = \PeskyORM\Config\Connection\PostgresConfig::fromArray($this->globalConfigs['pgsql']['valie']);
    }

}
