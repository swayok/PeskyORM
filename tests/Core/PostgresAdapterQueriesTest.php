<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\DbExpr;
use PeskyORM\Exception\DbException;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Utils\PdoUtils;

class PostgresAdapterQueriesTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    private static function getValidAdapter(): Postgres
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public function testInvalidValueInQuery(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("invalid input syntax for type json");
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->exec(
            \PeskyORM\DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
        $adapter->rollBack();
    }
    
    public function testInvalidTableInQuery(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("relation \"abrakadabra\" does not exist");
        $adapter = static::getValidAdapter();
        $adapter->exec(
            DbExpr::create('INSERT INTO `abrakadabra` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
    }
    
    public function testQueriesAndTransactions(): void
    {
        $adapter = static::getValidAdapter();
        $insertQuery = \PeskyORM\DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES(``test_key``, ``"test_value"``)');
        $selectQuery = \PeskyORM\DbExpr::create('SELECT * FROM `settings` WHERE `key` = ``test_key``');
        $adapter->begin();
        $rowsAffected = $adapter->exec($insertQuery);
        static::assertEquals(
            "INSERT INTO \"settings\" (\"key\", \"value\") VALUES('test_key', '\"test_value\"')",
            $adapter->getLastQuery()
        );
        static::assertEquals(1, $rowsAffected);
        $stmnt = $adapter->query($selectQuery);
        static::assertEquals(1, $stmnt->rowCount());
        $record = PdoUtils::getDataFromStatement($stmnt, PdoUtils::FETCH_FIRST);
        static::assertArraySubset([
            'key' => 'test_key',
            'value' => '"test_value"',
        ], $record);
        // test rollback
        $adapter->rollBack();
        $stmnt = $adapter->query($selectQuery);
        static::assertEquals(0, $stmnt->rowCount());
        // test commit
        $adapter->begin();
        $adapter->exec($insertQuery);
        $adapter->commit();
        $stmnt = $adapter->query($selectQuery);
        static::assertEquals(1, $stmnt->rowCount());
        static::assertArraySubset([
            'key' => 'test_key',
            'value' => '"test_value"',
        ], $record);
    }
    
    public function testTransactionsNestingPrevention(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage("Already in transaction");
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->begin();
    }
    
    public function testTransactionCommitWithoutBegin(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage("Attempt to commit not started transaction");
        $adapter = static::getValidAdapter();
        $adapter->commit();
        $adapter->commit();
    }
    
    public function testTransactionRollbackWithoutBegin(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage("Attempt to rollback not started transaction");
        $adapter = static::getValidAdapter();
        $adapter->rollBack();
        $adapter->rollBack();
    }
    
    public function testTransactionTypes(): void
    {
        $adapter = static::getValidAdapter();
        $adapter->rememberTransactionQueries = true;
        $adapter->begin(true);
        static::assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_DEFAULT . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        static::assertTrue($adapter->inTransaction());
        $adapter->rollBack();
        static::assertEquals('ROLLBACK', $adapter->getLastQuery());
        static::assertFalse($adapter->inTransaction());
        
        $adapter->begin(true, Postgres::TRANSACTION_TYPE_DEFAULT);
        static::assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_DEFAULT . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->commit();
        static::assertEquals('COMMIT', $adapter->getLastQuery());
        
        $adapter->begin(false, Postgres::TRANSACTION_TYPE_READ_COMMITTED);
        static::assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_READ_COMMITTED,
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();
        
        $adapter->begin(true, Postgres::TRANSACTION_TYPE_REPEATABLE_READ);
        static::assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_REPEATABLE_READ . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();
        
        $adapter->begin(true, Postgres::TRANSACTION_TYPE_SERIALIZABLE);
        static::assertEquals(
            'BEGIN ISOLATION LEVEL ' . Postgres::TRANSACTION_TYPE_SERIALIZABLE . ' READ ONLY',
            trim($adapter->getLastQuery())
        );
        $adapter->rollBack();
    }
    
    public function testInvalidTransactionType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown transaction type 'abrakadabra' for PostgreSQL");
        $adapter = static::getValidAdapter();
        if ($adapter->inTransaction()) {
            $adapter->rollBack();
        }
        $adapter->begin(true, 'abrakadabra');
    }
    
    public function testPreparedSelectQuery(): void
    {
        $adapter = static::getValidAdapter();
        $statement = $adapter->prepare(DbExpr::create('SELECT * FROM `admins` WHERE `id`=? AND `is_active`=?'));
        static::assertEquals('SELECT * FROM "admins" WHERE "id"=? AND "is_active"=?', $statement->queryString);
    }
    
}
