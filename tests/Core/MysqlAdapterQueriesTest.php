<?php

use PeskyORM\Core\DbExpr;
use PeskyORMTest\TestingApp;

class MysqlAdapterQueriesTest extends \PHPUnit_Framework_TestCase {

    public static function tearDownAfterClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static private function getValidAdapter() {
        return TestingApp::getMysqlConnection();
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
     * @expectedException \PeskyORM\Exception\DbException
     * @expectedExceptionMessage Already in transaction
     */
    public function testTransactionsNestingPrevention() {
        $adapter = static::getValidAdapter();
        $adapter->begin();
        $adapter->begin();
    }

    /**
     * @expectedException \PeskyORM\Exception\DbException
     * @expectedExceptionMessage Attempt to commit not started transaction
     */
    public function testTransactionCommitWithoutBegin() {
        $adapter = static::getValidAdapter();
        $adapter->commit();
        $adapter->commit();
    }

    /**
     * @expectedException \PeskyORM\Exception\DbException
     * @expectedExceptionMessage Attempt to rollback not started transaction
     */
    public function testTransactionRollbackWithoutBegin() {
        $adapter = static::getValidAdapter();
        $adapter->rollBack();
        $adapter->rollBack();
    }
}
