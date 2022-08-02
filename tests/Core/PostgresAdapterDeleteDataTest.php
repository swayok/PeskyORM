<?php

declare(strict_types=1);

namespace Tests\Core;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingApp;

class PostgresAdapterDeleteDataTest extends BaseTestCase
{
    
    static public function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    static public function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    /**
     * @return \PeskyORM\Adapter\Postgres
     */
    static protected function getValidAdapter()
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    protected function setUp(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public function testDelete()
    {
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')],
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $rowsDeleted = $adapter->delete('settings', DbExpr::create("`key` = ``{$testData1[0]['key']}``"));
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    "DELETE FROM `settings` WHERE (`key` = ``{$testData1[0]['key']}``)",
                    false
                )
            ),
            $adapter->getLastQuery()
        );
        static::assertEquals(1, $rowsDeleted);
        $count = Utils::getDataFromStatement(
            $adapter->query(
                DbExpr::create(
                    "SELECT COUNT(*) FROM `settings` WHERE `key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``) GROUP BY `key`",
                    false
                )
            ),
            Utils::FETCH_VALUE
        );
        static::assertEquals(1, $count);
    }
    
    public function testDeleteReturning()
    {
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')],
        ];
        $insertedData = $adapter->insertMany('settings', ['key', 'value'], $testData1, [], true);
        $deletedRecords = $adapter->delete(
            'settings',
            DbExpr::create("`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)"),
            true
        );
        static::assertCount(2, $deletedRecords);
        static::assertEquals($insertedData, $deletedRecords);
        
        $insertedData = $adapter->insertMany('settings', ['key', 'value'], $testData1, [], ['id', 'value']);
        $deletedRecords = $adapter->delete(
            'settings',
            DbExpr::create("`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)"),
            ['id', 'value']
        );
        static::assertCount(2, $deletedRecords);
        static::assertEquals($insertedData, $deletedRecords);
    }
    
}
