<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class PostgresAdapterInsertsTest extends \PHPUnit_Framework_TestCase {

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
        }
        return $data;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsert() {
        $adapter = static::getValidAdapter();
        $adapter->insert(null, []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsert2() {
        $adapter = static::getValidAdapter();
        $adapter->insert('', []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsert3() {
        $adapter = static::getValidAdapter();
        $adapter->insert(false, []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsert4() {
        $adapter = static::getValidAdapter();
        $adapter->insert($adapter, []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument cannot be empty
     */
    public function testInvalidDataInInsert() {
        $adapter = static::getValidAdapter();
        $adapter->insert('test', []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsert() {
        $adapter = static::getValidAdapter();
        $adapter->insert('test', ['key' => 'value'], [], '*');
    }
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsert2() {
        $adapter = static::getValidAdapter();
        $adapter->insert('test', ['key' => 'value'], [], $adapter);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsert3() {
        $adapter = static::getValidAdapter();
        $adapter->insert('test', ['key' => 'value'], [], 123);
    }

    public function testInsertOne() {
        static::cleanTables();
        $adapter = static::getValidAdapter();
        $testData1 = ['key' => 'test_key1', 'value' => json_encode('test_value1')];
        $adapter->insert('settings', $testData1);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create("SELECT * FROM `settings` WHERE `key` = ``{$testData1['key']}``")),
            Utils::FETCH_FIRST
        );
        $this->assertArraySubset($testData1, $data);

        $testData2 = $this->getTestDataForAdminsTableInsert()[0];
        $adapter->insert('admins', $testData2, [
            'id' => PDO::PARAM_INT,
            'parent_id' => PDO::PARAM_INT,
            'is_superadmin' => PDO::PARAM_BOOL,
            'is_active' => PDO::PARAM_BOOL
        ]);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create("SELECT * FROM `admins` WHERE `id` = ``{$testData2['id']}``")),
            Utils::FETCH_FIRST
        );
        $dataForAssert = $this->convertTestDataForAdminsTableAssert([$testData2])[0];
        $this->assertEquals($dataForAssert, $data);
        $this->assertEquals($dataForAssert['is_active'], $data['is_active']);
        $this->assertNull($data['parent_id']);

        // todo: test returning
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsertMany() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany(null, [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsertMany2() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('', [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsertMany3() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany(false, [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a string
     */
    public function testInvalidTableInInsertMany4() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany($adapter, [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument cannot be empty
     */
    public function testInvalidColumnsInInsertMany() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument cannot be empty
     */
    public function testInvalidDataInInsertMany() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['col1'], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $valuesAssoc array does not contain key [col1]
     */
    public function testInvalidDataInInsertMany2() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['col1'], [[]]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $valuesAssoc array does not contain key [col2]
     */
    public function testInvalidDataInInsertMany3() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['col1', 'col2'], [['col1' => '1']]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument must contain only arrays
     */
    public function testInvalidDataInInsertMany4() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['col1', 'col2'], ['col1' => '1']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsertMany() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['key'], ['key' => 'value'], [], '*');
    }
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsertMany2() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['key'], ['key' => 'value'], [], $adapter);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturningInInsertMany3() {
        $adapter = static::getValidAdapter();
        $adapter->insertMany('test', ['key'], ['key' => 'value'], [], 123);
    }

    public function testInsertMany() {
        static::cleanTables();
        $adapter = static::getValidAdapter();
        $testData1 = [
            ['key' => 'test_key1', 'value' => json_encode('test_value1')],
            ['key' => 'test_key2', 'value' => json_encode('test_value2')]
        ];
        $adapter->insertMany('settings', ['key', 'value'], $testData1);
        $this->assertEquals(
            $adapter->replaceDbExprQuotes(DbExpr::create(
                'INSERT INTO `settings` (`key`,`value`) VALUES '
                    . "(``{$testData1[0]['key']}``,``{$testData1[0]['value']}``),"
                    . "(``{$testData1[1]['key']}``,``{$testData1[1]['value']}``)"
            )),
            $adapter->getLastQuery()
        );
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT * FROM `settings` WHERE `key` IN (``{$testData1[0]['key']}``,``{$testData1[1]['key']}``) ORDER BY `key`"
            )),
            Utils::FETCH_ALL
        );
        $this->assertArraySubset($testData1[0], $data[0]);
        $this->assertArraySubset($testData1[1], $data[1]);

        $testData2 = $this->getTestDataForAdminsTableInsert();
        $adapter->insertMany('admins', array_keys($testData2[0]), $testData2, [
            'id' => PDO::PARAM_INT,
            'parent_id' => PDO::PARAM_INT,
            'is_superadmin' => PDO::PARAM_BOOL,
            'is_active' => PDO::PARAM_BOOL
        ]);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create(
                "SELECT * FROM `admins` WHERE `id` IN (``{$testData2[0]['id']}``,``{$testData2[1]['id']}``) ORDER BY `id`"
            )),
            Utils::FETCH_ALL
        );
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData2);
        $this->assertEquals($dataForAssert[0], $data[0]);
        $this->assertEquals($dataForAssert[1], $data[1]);
        $this->assertEquals($dataForAssert[0]['is_active'], $data[0]['is_active']);
        $this->assertEquals($dataForAssert[1]['is_active'], $data[1]['is_active']);
        $this->assertNull($data[0]['parent_id']);
        $this->assertEquals($dataForAssert[1]['parent_id'], $data[1]['parent_id']);

        // todo: test returning
    }

}
