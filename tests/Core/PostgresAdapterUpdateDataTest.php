<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PDO;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\Data\TestDataForAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Utils\PdoUtils;

class PostgresAdapterUpdateDataTest extends BaseTestCase
{
    use TestDataForAdminsTable;
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    protected static function getValidAdapter(): DbAdapterInterface
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public function testUpdate(): void
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')],
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $update1 = ['value' => json_encode('test_value1.1')];
        $adapter->update('settings', $update1, DbExpr::create("`key` = ``{$testData1[0]['key']}``"));
        static::assertEquals(
            $adapter->quoteDbExpr(
                \PeskyORM\DbExpr::create(
                    "UPDATE `settings` SET `value`=``\"test_value1.1\"`` WHERE (`key` = ``{$testData1[0]['key']}``)",
                    false
                )
            ),
            $adapter->getLastQuery()
        );
        $data = PdoUtils::getDataFromStatement(
            $adapter->query(
                \PeskyORM\DbExpr::create(
                    "SELECT * FROM `settings` WHERE (`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)) ORDER BY `key`",
                    false
                )
            ),
            PdoUtils::FETCH_ALL
        );
        static::assertArraySubset(array_replace($testData1[0], $update1), $data[0]);
        static::assertArraySubset($testData1[1], $data[1]);
        
        $testData2 = $this->getTestDataForAdminsTableInsert();
        $adapter->insertMany('admins', array_keys($testData2[0]), $testData2);
        $update2 = [
            'parent_id' => 1,
            'is_superadmin' => 0,
            'is_active' => 0,
        ];
        $adapter->update(
            'admins',
            $update2,
            DbExpr::create("`id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``)"),
            [
                'parent_id' => PDO::PARAM_INT,
                'is_superadmin' => PDO::PARAM_BOOL,
                'is_active' => PDO::PARAM_BOOL,
            ]
        );
        $data = PdoUtils::getDataFromStatement(
            $adapter->query(
                \PeskyORM\DbExpr::create(
                    "SELECT * FROM `admins` WHERE `id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``) ORDER BY `id`"
                )
            ),
            PdoUtils::FETCH_ALL
        );
        $testData2[0] = array_replace($testData2[0], $update2);
        $testData2[1] = array_replace($testData2[1], $update2);
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData2);
        static::assertEquals($dataForAssert[0], $data[0]);
        static::assertEquals($dataForAssert[0]['is_active'], $data[0]['is_active']);
        static::assertEquals($dataForAssert[0]['parent_id'], $data[0]['parent_id']);
        static::assertEquals($dataForAssert[1], $data[1]);
        static::assertEquals($dataForAssert[1]['is_active'], $data[1]['is_active']);
        static::assertEquals($dataForAssert[1]['parent_id'], $data[1]['parent_id']);
    }
    
}
