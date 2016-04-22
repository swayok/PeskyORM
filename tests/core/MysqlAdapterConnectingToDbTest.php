<?php

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\DbExpr;

class MysqlAdapterConnectingToDbTest extends \PHPUnit_Framework_TestCase {

    /** @var MysqlConfig */
    static protected $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = MysqlConfig::fromArray($data['mysql']);
    }

    public static function tearDownAfterClass() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        self::$dbConnectionConfig = null;
    }

    static private function getValidAdapter() {
        return new Mysql(self::$dbConnectionConfig);
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
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        $this->assertEquals(1, $stmnt->rowCount());
    }

    public function testDisconnect() {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $adapter->disconnect();
        $reflector = new ReflectionClass($adapter);
        $prop = $reflector->getProperty('pdo');
        $prop->setAccessible(true);
        $this->assertEquals(null, $prop->getValue($adapter));
        $reflector->getProperty('pdo')->setAccessible(false);
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage Column 'key' cannot be null
     */
    public function testInvalidValueInQuery() {
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->exec(
            DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES (null, ``test_value``)')
        );
        $adapter->rollBack();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessageRegExp  %Table '.*?\.abrakadabra' doesn't exist%i
     */
    public function testInvalidTableInQuery() {
        $adapter = static::getValidAdapter();
        $adapter->exec(
            DbExpr::create('INSERT INTO `abrakadabra` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
    }

    public function testQueriesAndTransactions() {
        $adapter = static::getValidAdapter();
        $insertQuery = DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES(``test_key``, ``"test_value"``)');
        $selectQuery = DbExpr::create('SELECT * FROM `settings` WHERE `key` = ``test_key``');
        $adapter->begin();
        $rowsAffected = $adapter->exec($insertQuery);
        $this->assertEquals(
            "INSERT INTO `settings` (`key`, `value`) VALUES('test_key', '\\\"test_value\\\"')",
            $adapter->getLastQuery()
        );
        $this->assertEquals(1, $rowsAffected);
        $stmnt = $adapter->query($selectQuery);
        $this->assertEquals(1, $stmnt->rowCount());
        $record = \PeskyORM\Core\Utils::getDataFromStatement($stmnt, \PeskyORM\Core\Utils::FETCH_FIRST);
        $this->assertArraySubset([
            'key' => 'test_key',
            'value' => '"test_value"',
        ], $record);
        // test rollback
        $adapter->rollBack();
        $stmnt = $adapter->query($selectQuery);
        $this->assertEquals(0, $stmnt->rowCount());
        // test commit
        $adapter->begin();
        $adapter->exec($insertQuery);
        $adapter->commit();
        $stmnt = $adapter->query($selectQuery);
        $this->assertEquals(1, $stmnt->rowCount());
        $this->assertArraySubset([
            'key' => 'test_key',
            'value' => '"test_value"',
        ], $record);
    }

    /**
     * @expectedException \PeskyORM\Core\DbException
     * @expectedExceptionMessage Already in transaction
     */
    public function testTransactionsNestingPrevention() {
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->begin();
    }

    /**
     * @expectedException \PeskyORM\Core\DbException
     * @expectedExceptionMessage Attempt to commit not started transaction
     */
    public function testTransactionCommitWithoutBegin() {
        $adapter = static::getValidAdapter();
        $adapter->commit();
    }

    /**
     * @expectedException \PeskyORM\Core\DbException
     * @expectedExceptionMessage Attempt to rollback not started transaction
     */
    public function testTransactionRollbackWithoutBegin() {
        $adapter = static::getValidAdapter();
        $adapter->rollBack();
    }
}
