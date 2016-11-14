<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class PostgresAdapterUpdateDataTest extends \PHPUnit_Framework_TestCase {

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
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }

    public function getTestDataForAdminsTableInsert() {
        return [
            [
                'id' => 1,
                'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
                'password' => password_hash('KIS37QEG4HT', PASSWORD_DEFAULT),
                'parent_id' => null,
                'created_at' => '2015-05-14 02:12:05+00',
                'updated_at' => '2015-06-10 19:30:24+00',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => 1,
                'name' => 'Lionel Freeman',
                'email' => 'diam.at.pretium@idmollisnec.co.uk',
                'timezone' => 'Europe/Moscow'
            ],
            [
                'id' => 2,
                'login' => 'ADCE237A-9E48-BECD-1F01-1CACA964CF0F',
                'password' => password_hash('NKJ63NMV6NY', PASSWORD_DEFAULT),
                'parent_id' => 1,
                'created_at' => '2015-05-14 06:54:01+00',
                'updated_at' => '2015-05-19 23:48:17+00',
                'remember_token' => '0A2E7DA9-6072-34E2-38E8-2675C73F3419',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => false,
                'name' => 'Jasper Waller',
                'email' => 'elit@eratvelpede.org',
                'timezone' => 'Europe/Moscow'
            ]
        ];
    }

    public function convertTestDataForAdminsTableAssert($data) {
        foreach ($data as &$item) {
            $item['id'] = "{$item['id']}";
            $item['is_superadmin'] = (bool)$item['is_superadmin'];
            $item['is_active'] = (bool)$item['is_active'];
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
    }

    public function testUpdate() {
        static::cleanTables();
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')]
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $update1 = ['value' => json_encode('test_value1.1')];
        $adapter->update('settings', $update1, DbExpr::create("`key` = ``{$testData1[0]['key']}``"));
        $this->assertEquals(
            $adapter->quoteDbExpr(DbExpr::create(
                "UPDATE `settings` SET `value`=``\"test_value1.1\"`` WHERE (`key` = ``{$testData1[0]['key']}``)",
                false
            )),
            $adapter->getLastQuery()
        );
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT * FROM `settings` WHERE (`key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``)) ORDER BY `key`",
                false
            )),
            Utils::FETCH_ALL
        );
        $this->assertArraySubset(array_replace($testData1[0], $update1), $data[0]);
        $this->assertArraySubset($testData1[1], $data[1]);

        $testData2 = $this->getTestDataForAdminsTableInsert();
        $adapter->insertMany('admins', array_keys($testData2[0]), $testData2);
        $update2 = [
            'parent_id' => 1,
            'is_superadmin' => 0,
            'is_active' => 0
        ];
        $adapter->update(
            'admins',
            $update2,
            DbExpr::create("`id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``)"),
            [
                'parent_id' => PDO::PARAM_INT,
                'is_superadmin' => PDO::PARAM_BOOL,
                'is_active' => PDO::PARAM_BOOL
            ]
        );
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT * FROM `admins` WHERE `id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``) ORDER BY `id`"
            )),
            Utils::FETCH_ALL
        );
        $testData2[0] = array_replace($testData2[0], $update2);
        $testData2[1] = array_replace($testData2[1], $update2);
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData2);
        $this->assertEquals($dataForAssert[0], $data[0]);
        $this->assertEquals($dataForAssert[0]['is_active'], $data[0]['is_active']);
        $this->assertEquals($dataForAssert[0]['parent_id'], $data[0]['parent_id']);
        $this->assertEquals($dataForAssert[1], $data[1]);
        $this->assertEquals($dataForAssert[1]['is_active'], $data[1]['is_active']);
        $this->assertEquals($dataForAssert[1]['parent_id'], $data[1]['parent_id']);
    }

}
