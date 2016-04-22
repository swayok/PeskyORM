<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;

class PostgresAdapterConnectingToDbTest extends \PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
    }

    public static function tearDownAfterClass() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        self::$dbConnectionConfig = null;
    }

    static private function getValidAdapter() {
        return new Postgres(self::$dbConnectionConfig);
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
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid db entity name
     */
    public function testQuotingOfInvalidDbEntity() {
        $adapter = static::getValidAdapter();
        $adapter->quoteName('";DROP table1;');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Value in $fieldType argument must be a constant like
     */
    public function testQuotingOfInvalidDbValueType() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('test', 'abrakadabra');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. String [abrakadabra] received
     */
    public function testQuotingOfInvalidIntDbValue() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('abrakadabra', PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Object fo class [\PeskyORM\Adapter\Postgres] received
     */
    public function testQuotingOfInvalidIntDbValue2() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue($adapter, PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Array received
     */
    public function testQuotingOfInvalidIntDbValue3() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(['key' => 'val'], PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Resource received
     */
    public function testQuotingOfInvalidIntDbValue4() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(curl_init('http://test.url'), PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Boolean [true] received
     */
    public function testQuotingOfInvalidIntDbValue5() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(true, PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Boolean [false] received
     */
    public function testQuotingOfInvalidIntDbValue6() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(false, PDO::PARAM_INT);
    }

    public function testQuoting() {
        $adapter = static::getValidAdapter();
        $this->assertEquals('"table1"', $adapter->quoteName('table1'));
        $this->assertEquals("''';DROP table1;'", $adapter->quoteValue('\';DROP table1;'));
        $this->assertEquals('TRUE', $adapter->quoteValue(true));
        $this->assertEquals('TRUE', $adapter->quoteValue(1, PDO::PARAM_BOOL));
        $this->assertEquals('TRUE', $adapter->quoteValue('1', PDO::PARAM_BOOL));
        $this->assertEquals('FALSE', $adapter->quoteValue(false));
        $this->assertEquals('FALSE', $adapter->quoteValue(0, PDO::PARAM_BOOL));
        $this->assertEquals('FALSE', $adapter->quoteValue('0', PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null));
        $this->assertEquals('NULL', $adapter->quoteValue('abrakadabra', PDO::PARAM_NULL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_INT));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_STR));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_LOB));
        $this->assertEquals("'123'", $adapter->quoteValue(123));
        $this->assertEquals("'123'", $adapter->quoteValue(123, PDO::PARAM_INT));
        $this->assertEquals(
            'DELETE FROM "table1" WHERE "col1" = \'value1\'',
            $adapter->replaceDbExprQuotes(DbExpr::create('DELETE FROM `table1` WHERE `col1` = ``value1``'))
        );
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage invalid input syntax for type json
     */
    public function testInvalidValueInQuery() {
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->exec(
            DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
        $adapter->rollBack();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage relation "abrakadabra" does not exist
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
            "INSERT INTO \"settings\" (\"key\", \"value\") VALUES('test_key', '\"test_value\"')",
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

    public function testTransactionTypes() {
        $adapter = static::getValidAdapter();

        $adapter->begin(true);
        $this->assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_DEFAULT . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $this->assertTrue($adapter->inTransaction());
        $adapter->rollBack();
        $this->assertEquals('ROLLBACK', $adapter->getLastQuery());
        $this->assertFalse($adapter->inTransaction());

        $adapter->begin(true, Postgres::TRANSACTION_TYPE_DEFAULT);
        $this->assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_DEFAULT . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->commit();
        $this->assertEquals('COMMIT', $adapter->getLastQuery());

        $adapter->begin(false, Postgres::TRANSACTION_TYPE_READ_COMMITTED);
        $this->assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_READ_COMMITTED,
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();

        $adapter->begin(true, Postgres::TRANSACTION_TYPE_REPEATABLE_READ);
        $this->assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_REPEATABLE_READ . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();

        $adapter->begin(true, Postgres::TRANSACTION_TYPE_SERIALIZABLE);
        $this->assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_SERIALIZABLE . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unknown transaction type 'abrakadabra' for PostgreSQL
     */
    public function testInvalidTransactionType() {
        $adapter = static::getValidAdapter();
        $adapter->begin(true, 'abrakadabra');
    }
}
