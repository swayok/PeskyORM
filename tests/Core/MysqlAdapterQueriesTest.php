<?php

declare(strict_types=1);

namespace Tests\Core;

use PDOException;
use PeskyORM\Core\DbExpr;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingApp;

class MysqlAdapterQueriesTest extends BaseTestCase
{
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    static private function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testInvalidValueInQuery()
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
    
    public function testInvalidTableInQuery()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("%Table '.*?\.abrakadabra' doesn't exist%i");
        $adapter = static::getValidAdapter();
        $adapter->exec(
            DbExpr::create('INSERT INTO `abrakadabra` (`key`, `value`) VALUES (``test_key``, ``test_value``)')
        );
    }
    
    public function testQueriesAndTransactions()
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
        $record = \PeskyORM\Core\Utils::getDataFromStatement($stmnt, \PeskyORM\Core\Utils::FETCH_FIRST);
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
    
    public function testTransactionsNestingPrevention()
    {
        $this->expectException(\PeskyORM\Exception\DbException::class);
        $this->expectExceptionMessage("Already in transaction");
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->begin();
    }
    
    public function testTransactionCommitWithoutBegin()
    {
        $this->expectException(\PeskyORM\Exception\DbException::class);
        $this->expectExceptionMessage("Attempt to commit not started transaction");
        $adapter = static::getValidAdapter();
        $adapter->commit();
        $adapter->commit();
    }
    
    public function testTransactionRollbackWithoutBegin()
    {
        $this->expectException(\PeskyORM\Exception\DbException::class);
        $this->expectExceptionMessage("Attempt to rollback not started transaction");
        $adapter = static::getValidAdapter();
        $adapter->rollBack();
        $adapter->rollBack();
    }
    
    public function testPreparedSelectQuery()
    {
        $adapter = static::getValidAdapter();
        $statement = $adapter->prepare(DbExpr::create('SELECT * FROM `admins` WHERE `id`=? AND `is_active`=?'));
        static::assertEquals('SELECT * FROM `admins` WHERE `id`=? AND `is_active`=?', $statement->queryString);
    }
}
