<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class PostgresAdapterDataModificationQueriesTest extends \PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
    }

    public static function tearDownAfterClass() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
        self::$dbConnectionConfig = null;
    }

    static private function getValidAdapter() {
        return new Postgres(self::$dbConnectionConfig);
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

    public function testBuildValuesList() {

    }

    public function testBuildColumnsList() {

    }

    public function testInserts() {
        $adapter = static::getValidAdapter();
        $testData1 = ['key' => 'test_key1', 'value' => json_encode('test_value1')];
        $adapter->insert('settings', $testData1);
        $data = Utils::getDataFromStatement(
            $adapter->query(DbExpr::create("SELECT * FROM `settings` WHERE `key` = ``{$testData1['key']}``")),
            Utils::FETCH_FIRST
        );
        $this->assertArraySubset($testData1, $data);

        $testData2 = [
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
        ];
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
        $this->assertArraySubset($testData2, $data);
        $this->assertTrue($data['is_active']);
        $this->assertNull($data['parent_id']);
    }



}
