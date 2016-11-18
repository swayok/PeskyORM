<?php

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORMTest\TestingApp;

class PostgresAdapterDeleteTest extends \PHPUnit_Framework_TestCase {

    static public function setUpBeforeClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static public function tearDownAfterClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    /**
     * @return \PeskyORM\Adapter\Postgres
     */
    static protected function getValidAdapter() {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }

    protected function setUp() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    public function testDelete() {
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')]
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $rowsDeleted = $adapter->delete('settings', DbExpr::create("`key` = ``{$testData1[0]['key']}``"));
        $this->assertEquals(
            $adapter->quoteDbExpr(DbExpr::create(
                "DELETE FROM `settings` WHERE (`key` = ``{$testData1[0]['key']}``)",
                false
            )),
            $adapter->getLastQuery()
        );
        $this->assertEquals(1, $rowsDeleted);
        $count = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT COUNT(*) FROM `settings` WHERE `key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``) GROUP BY `key`",
                false
            )),
            Utils::FETCH_VALUE
        );
        $this->assertEquals(1, $count);
    }

    public function testDeleteReturning() {
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')]
        ];
        $insertedData = $adapter->insertMany('settings', ['key', 'value'], $testData1, [], true);
        $deletedRecords = $adapter->delete(
            'settings',
            DbExpr::create("`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)"),
            true
        );
        $this->assertCount(2, $deletedRecords);
        $this->assertEquals($insertedData, $deletedRecords);

        $insertedData = $adapter->insertMany('settings', ['key', 'value'], $testData1, [], ['id', 'value']);
        $deletedRecords = $adapter->delete(
            'settings',
            DbExpr::create("`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)"),
            ['id', 'value']
        );
        $this->assertCount(2, $deletedRecords);
        $this->assertEquals($insertedData, $deletedRecords);
    }

}
