<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;

class PostgresAdapterQueriesTest extends \PHPUnit_Framework_TestCase {

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
