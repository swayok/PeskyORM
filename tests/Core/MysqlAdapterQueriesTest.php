<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PDOException;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\Exception\DbException;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class MysqlAdapterQueriesTest extends BaseTestCase
{
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    private static function getValidAdapter(): Mysql
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testInvalidValueInQuery(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Column 'key' cannot be null");
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->exec(
            DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES (NULL, ``test_value``)')
        );
        $adapter->rollBack();
    }
    
    public function testInvalidTableInQuery(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("%Table '.*?\.abrakadabra' doesn't exist%i");
        $adapter = static::getValidAdapter();
        $adapter->exec(
            DbExpr::create('INSERT INTO `abrakadabra` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
    }
    
    public function testQueriesAndTransactions(): void
    {
        $adapter = static::getValidAdapter();
        $insertQuery = DbExpr::create('INSERT INTO `settings` (`key`, `value`) VALUES(``test_key``, ``"test_value"``)');
        $selectQuery = DbExpr::create('SELECT * FROM `settings` WHERE `key` = ``test_key``');
        $adapter->begin();
        $rowsAffected = $adapter->exec($insertQuery);
        static::assertEquals(
            "INSERT INTO `settings` (`key`, `value`) VALUES('test_key', '\\\"test_value\\\"')",
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
    
    public function testPreparedSelectQuery(): void
    {
        $adapter = static::getValidAdapter();
        $statement = $adapter->prepare(DbExpr::create('SELECT * FROM `admins` WHERE `id`=? AND `is_active`=?'));
        static::assertEquals('SELECT * FROM `admins` WHERE `id`=? AND `is_active`=?', $statement->queryString);
    }
}
