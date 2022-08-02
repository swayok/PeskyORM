<?php

declare(strict_types=1);

namespace Tests\Core;

use PDO;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\Data\TestDataForAdminsTable;
use Tests\PeskyORMTest\TestingApp;

class PostgresAdapterInsertDataTest extends BaseTestCase
{
    use TestDataForAdminsTable;
    
    static public function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    static public function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    static protected function getValidAdapter()
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public function testInsertOne()
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData1 = ['key' => 'test_key1', 'value' => json_encode('test_value1')];
        $adapter->insert('settings', $testData1);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create("SELECT * FROM `settings` WHERE `key` = ``{$testData1['key']}``")),
            Utils::FETCH_FIRST
        );
        static::assertArraySubset($testData1, $data);
        
        $testData2 = $this->getTestDataForAdminsTableInsert()[0];
        $adapter->insert('admins', $testData2, [
            'id' => PDO::PARAM_INT,
            'parent_id' => PDO::PARAM_INT,
            'is_superadmin' => PDO::PARAM_BOOL,
            'is_active' => PDO::PARAM_BOOL,
        ]);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create("SELECT * FROM `admins` WHERE `id` = ``{$testData2['id']}``")),
            Utils::FETCH_FIRST
        );
        $dataForAssert = $this->convertTestDataForAdminsTableAssert([$testData2])[0];
        static::assertEquals($dataForAssert, $data);
        static::assertEquals($dataForAssert['is_active'], $data['is_active']);
        static::assertNull($data['parent_id']);
        
        // insert already within transaction
        $adapter->begin();
        $adapter->insert('settings', ['key' => 'in_transaction', 'value' => json_encode('yes')]);
        $adapter->commit();
        
        // test returning
        /** @var array $return */
        $return = $adapter->insert(
            'settings',
            ['key' => 'test_key_returning1', 'value' => json_encode('test_value1')],
            [],
            ['id', 'key']
        );
        static::assertArrayHasKey('id', $return);
        static::assertArrayHasKey('key', $return);
        static::assertArrayNotHasKey('value', $return);
        static::assertEquals('test_key_returning1', $return['key']);
        
        $return = $adapter->insert(
            'settings',
            ['key' => 'test_key_returning2', 'value' => json_encode('test_value1')],
            [],
            true
        );
        static::assertArrayHasKey('id', $return);
        static::assertArrayHasKey('key', $return);
        static::assertArrayHasKey('value', $return);
        static::assertGreaterThanOrEqual(1, $return['id']);
        static::assertEquals('test_key_returning2', $return['key']);
        static::assertEquals(json_encode('test_value1'), $return['value']);
    }
    
    public function testInvalidColumnsForInsertMany()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings");
        $adapter = static::getValidAdapter();
        $adapter->insertMany('settings', [null], [['key' => 'value']]);
    }
    
    public function testInvalidColumnsForInsertMany2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings");
        $adapter = static::getValidAdapter();
        $adapter->insertMany('settings', [DbExpr::create('test')], [['key' => 'value']]);
    }
    
    public function testInvalidColumnsForInsertMany3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings");
        $adapter = static::getValidAdapter();
        $adapter->insertMany('settings', [['subarray']], [['key' => 'value']]);
    }
    
    public function testInsertMany()
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')],
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'INSERT INTO `settings` (`key`, `value`) VALUES '
                    . "(``{$testData1[0]['key']}``, ``{$testData1[0]['value']}``),"
                    . "(``{$testData1[1]['key']}``, ``{$testData1[1]['value']}``)",
                    false
                )
            ),
            $adapter->getLastQuery()
        );
        $data = Utils::getDataFromStatement(
            $adapter->query(
                DbExpr::create(
                    "SELECT * FROM `settings` WHERE (`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)) ORDER BY `key`",
                    false
                )
            ),
            Utils::FETCH_ALL
        );
        static::assertArraySubset($testData1[0], $data[0]);
        static::assertArraySubset($testData1[1], $data[1]);
        $data = $adapter->query(
            DbExpr::create(
                "SELECT * FROM `settings` WHERE (`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)) ORDER BY `key`",
                false
            ),
            Utils::FETCH_ALL
        );
        static::assertArraySubset($testData1[0], $data[0]);
        static::assertArraySubset($testData1[1], $data[1]);
        
        $testData2 = $this->getTestDataForAdminsTableInsert();
        $adapter->insertMany('admins', array_keys($testData2[0]), $testData2, [
            'id' => PDO::PARAM_INT,
            'parent_id' => PDO::PARAM_INT,
            'is_superadmin' => PDO::PARAM_BOOL,
            'is_active' => PDO::PARAM_BOOL,
        ]);
        $data = Utils::getDataFromStatement(
            $adapter->query(
                DbExpr::create(
                    "SELECT * FROM `admins` WHERE `id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``) ORDER BY `id`"
                )
            ),
            Utils::FETCH_ALL
        );
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData2);
        static::assertEquals($dataForAssert[0], $data[0]);
        static::assertEquals($dataForAssert[1], $data[1]);
        static::assertEquals($dataForAssert[0]['is_active'], $data[0]['is_active']);
        static::assertEquals($dataForAssert[1]['is_active'], $data[1]['is_active']);
        static::assertNull($data[0]['parent_id']);
        static::assertEquals($dataForAssert[1]['parent_id'], $data[1]['parent_id']);
    }
    
    public function testInsertManyReturning()
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        /** @var array $return */
        $testData3 = [
            ['key' => 'test_key_returning2', 'value' => json_encode('test_value1')],
            ['key' => 'test_key_returning3', 'value' => json_encode('test_value1')],
        ];
        $return = $adapter->insertMany('settings', ['key', 'value'], $testData3, [], true);
        static::assertCount(2, $return);
        static::assertArrayHasKey('id', $return[0]);
        static::assertArraySubset($testData3[0], $return[0]);
        static::assertGreaterThanOrEqual(1, $return[0]['id']);
        static::assertArrayHasKey('id', $return[1]);
        static::assertArraySubset($testData3[1], $return[1]);
        static::assertGreaterThanOrEqual(1, $return[1]['id']);
        
        $testData4 = [
            ['key' => 'test_key_returning4', 'value' => json_encode('test_value1')],
            ['key' => 'test_key_returning5', 'value' => json_encode('test_value1')],
        ];
        $return = $adapter->insertMany('settings', ['key', 'value'], $testData4, [], ['id']);
        static::assertCount(2, $return);
        static::assertArrayHasKey('id', $return[0]);
        static::assertArrayNotHasKey('key', $return[0]);
        static::assertArrayNotHasKey('value', $return[0]);
        static::assertGreaterThanOrEqual(1, $return[0]['id']);
        static::assertArrayHasKey('id', $return[1]);
        static::assertArrayNotHasKey('key', $return[1]);
        static::assertArrayNotHasKey('value', $return[1]);
        static::assertGreaterThanOrEqual(1, $return[1]['id']);
    }
    
}
