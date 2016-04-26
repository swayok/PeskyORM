<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class PostgresAdapterDeleteTest extends \PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
        static::cleanTables();
    }

    static public function tearDownAfterClass() {
        static::cleanTables();
        static::$dbConnectionConfig = null;
    }

    static protected function cleanTables() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }

    static protected function getValidAdapter() {
        $adapter = new Postgres(static::$dbConnectionConfig);
        $adapter->writeTransactionQueriesToLastQuery = false;
        return $adapter;
    }

    public function testDelete() {
        static::cleanTables();
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')]
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $rowsDeleted = $adapter->delete('settings', DbExpr::create("`key` = ``{$testData1[0]['key']}``"));
        $this->assertEquals(
            $adapter->replaceDbExprQuotes(DbExpr::create(
                "DELETE FROM `settings` WHERE `key` = ``{$testData1[0]['key']}``"
            )),
            $adapter->getLastQuery()
        );
        $this->assertEquals(1, $rowsDeleted);
        $count = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT COUNT(*) FROM `settings` WHERE `key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``) GROUP BY `key`"
            )),
            Utils::FETCH_VALUE
        );
        $this->assertEquals(1, $count);
    }

    public function testDeleteReturning() {
        static::cleanTables();
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
