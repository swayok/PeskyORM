<?php

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;

class MysqlAdapterConnectingToDbTest extends \PHPUnit_Framework_TestCase {

    /** @var MysqlConfig */
    static $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = MysqlConfig::fromArray($data['mysql']);
    }

    public static function tearDownAfterClass() {
        self::$dbConnectionConfig = null;
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage Access denied for user
     */
    public function testConnectionWithInvalidUserName() {
        $config = MysqlConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage Access denied for user
     */
    public function testConnectionWithInvalidUserName2() {
        $config = MysqlConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage Unknown database
     */
    public function testConnectionWithInvalidDbName() {
        $config = MysqlConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => self::$dbConnectionConfig->getUserPassword()
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage Access denied for user
     */
    public function testConnectionWithInvalidUserPassword() {
        $config = MysqlConfig::fromArray([
            'name' => self::$dbConnectionConfig->getDbName(),
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }

    /**
     * Note: very slow
     */
    /*public function testConnectionWithInvalidDbPort() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('SQLSTATE[HY000]');
        $config = MysqlConfig::fromArray([
            'name' => self::$dbConnectionConfig->getDbName(),
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => self::$dbConnectionConfig->getUserPassword(),
            'port' => '9999'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }*/

    public function testValidConnection() {
        $adapter = new Mysql(self::$dbConnectionConfig);
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        $this->assertEquals($stmnt->columnCount(), 1);
    }
}
