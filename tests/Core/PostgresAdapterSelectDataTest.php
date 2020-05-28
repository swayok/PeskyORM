<?php

use PeskyORM\Core\DbExpr;
use PeskyORMTest\TestingApp;

class PostgresAdapterSelectDataTest extends \PHPUnit_Framework_TestCase {

    static public function setUpBeforeClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static public function tearDownAfterClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static protected function getValidAdapter() {
        $adapter = TestingApp::getPgsqlConnection();
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

    public function testSelects() {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData = $this->getTestDataForAdminsTableInsert();
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData);
        $adapter->insertMany('admins', array_keys($testData[0]), $testData);

        $data = $adapter->select('admins', [], DbExpr::create('ORDER BY `id`', false));
        $this->assertEquals($dataForAssert[0], $data[0]);
        $this->assertEquals($dataForAssert[1], $data[1]);

        $data = $adapter->select('admins', ['id', 'parent_id'], DbExpr::create(
            "WHERE `id` IN (``{$testData[0]['id']}``)"
        ));
        $this->assertCount(1, $data);
        $this->assertCount(2, $data[0]);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('parent_id', $data[0]);
        $this->assertArraySubset($data[0], $dataForAssert[0]);

        $data = $adapter->selectOne('admins', [], DbExpr::create(
            "WHERE `id` IN (``{$testData[0]['id']}``)"
        ));
        $this->assertEquals($dataForAssert[0], $data);

        $data = $adapter->selectColumn('admins', 'email', DbExpr::create('ORDER BY `id`'));
        $this->assertCount(2, $data);
        $this->assertEquals([$dataForAssert[0]['email'], $dataForAssert[1]['email']], $data);

        $data = $adapter->selectAssoc('admins', 'id', 'email', DbExpr::create('ORDER BY `id`'));
        $this->assertCount(2, $data);
        $this->assertEquals(
            [
                $dataForAssert[0]['id'] => $dataForAssert[0]['email'],
                $dataForAssert[1]['id'] => $dataForAssert[1]['email']
            ],
            $data
        );

        $data = $adapter->selectValue('admins', DbExpr::create('COUNT(`*`)'));
        $this->assertEquals(2, $data);
    }

}
