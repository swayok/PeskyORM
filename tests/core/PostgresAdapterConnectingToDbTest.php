<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;

class PostgresAdapterConnectingToDbTest extends \PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
    }

    public static function tearDownAfterClass() {
        self::$dbConnectionConfig = null;
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserName() {
        $config = PostgresConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserName2() {
        $config = PostgresConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage database "totally_not_existing_db" does not exist
     */
    public function testConnectionWithInvalidDbName() {
        $config = PostgresConfig::fromArray([
            'name' => 'totally_not_existing_db',
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => self::$dbConnectionConfig->getUserPassword()
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserPassword() {
        $config = PostgresConfig::fromArray([
            'name' => self::$dbConnectionConfig->getDbName(),
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * Note: very slow
     */
    /*public function testConnectionWithInvalidDbPort() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('could not connect to server');
        $config = PostgresConfig::fromArray([
            'name' => self::$dbConnectionConfig->getDbName(),
            'user' => self::$dbConnectionConfig->getUserName(),
            'password' => self::$dbConnectionConfig->getUserPassword(),
            'port' => '9999'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }*/

    public function testValidConnection() {
        $adapter = new Postgres(self::$dbConnectionConfig);
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        $this->assertEquals($stmnt->columnCount(), 1);
    }
}
