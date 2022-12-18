<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class TableTest extends BaseTestCase
{

    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(TestingAdminsTable::getInstance()->getConnection());
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        TestingApp::clearTables(TestingAdminsTable::getInstance()->getConnection());
    }

    public static function fillAdminsTable(int $limit = 0): array
    {
        TestingAdminsTable::getInstance()
            ->getConnection(true)
            ->exec('TRUNCATE TABLE admins');
        $data = TestingApp::getRecordsForDb('admins', $limit);
        // avoid using TestingAdminsTable::insertMany()
        // to avoid autoupdatable columns usage *updated_at for example
        TestingAdminsTable::getInstance()
            ->getConnection()
            ->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }

    public function testInsert(): void
    {
        $data = TestingApp::getRecordsForDb('admins', 2);
        $insertedData = TestingAdminsTable::insert($data[0], true, false);
        $expectedData = TestingAdmin::fromArray($data[0], true)
            ->getValuesForInsertQuery(array_keys($insertedData));
        $expectedData['created_at'] .= '+00';
        $expectedData['updated_at'] .= '+00';
        static::assertEquals($expectedData, $insertedData);

        $insertedData = TestingAdminsTable::insert($data[1], true, true);
        $expectedData = TestingAdmin::fromArray($data[1], true)
            ->getValuesForInsertQuery(array_keys($insertedData));
        $expectedData['created_at'] .= '+00';
        $expectedData['updated_at'] .= '+00';
        static::assertEquals($expectedData, $insertedData);

        $newAdmin = [
            'login' => '2AE351AF',
            'password' => '$2y$10$9KOltdqg053WgkQoQT6cU.JkI92qwdRuD1h4E99.zCy1OicQDd.da',
            'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
            'ip' => '192.168.0.1',
            'name' => 'Lionel Freeman',
            'email' => 'qqq@qqq.co.uk',
            'big_data' => 'biiiiiiig data'
        ];
        $insertedData = TestingAdminsTable::insert($newAdmin, true, true);
        $expectedData = array_merge($newAdmin, [
            'id' => $insertedData['id'],
            'created_at' => $insertedData['created_at'],
            'updated_at' => $insertedData['updated_at'],
            'parent_id' => null,
            'is_superadmin' => false,
            'language' => 'en',
            'role' => '',
            'is_active' => true,
            'timezone' => 'UTC',
            'not_changeable_column' => 'not changable'
        ]);
        static::assertEquals($expectedData, $insertedData);

        $newAdmin['login'] = '4FE263E6FF81';
        $newAdmin['email'] = 'www@qqq.co.uk';
        $newAdmin['password'] = 'test';
        $insertedData = TestingAdminsTable::insert($newAdmin, true, false);
        // password must be hashed
        static::assertNotEquals($newAdmin['password'], $insertedData['password']);
        $expectedData = array_merge($newAdmin, [
            'id' => $insertedData['id'],
            'created_at' => $insertedData['created_at'],
            'updated_at' => $insertedData['updated_at'],
            'password' => $insertedData['password'],
            'parent_id' => null,
            'is_superadmin' => false,
            'language' => 'en',
            'role' => '',
            'is_active' => true,
            'timezone' => 'UTC',
            'not_changeable_column' => 'not changable'
        ]);
        static::assertEquals($expectedData, $insertedData);
    }

    public function testInvalidInsertMany1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$valuesAssoc array does not contain key [id]');
        $columns = array_keys((new TestingAdminsTableStructure())->getRealColumns());
        TestingAdminsTable::insertMany($columns, [['is_active' => true]], true);
    }

    public function testInvalidInsertMany2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$rows[is_active]: value must be an array.');
        $columns = array_keys((new TestingAdminsTableStructure())->getRealColumns());
        TestingAdminsTable::insertMany($columns, ['is_active' => true], true);
    }

    public function testInvalidInsertMany3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$rows[0]: value must be an array.');
        $columns = array_keys((new TestingAdminsTableStructure())->getRealColumns());
        TestingAdminsTable::insertMany($columns, [null], true);
    }

    public function testInsertMany1(): void
    {
        $data = TestingApp::getRecordsForDb('admins', 4);
        $columns = array_keys((new TestingAdminsTableStructure())->getRealColumns());
        $insertedRows = TestingAdminsTable::insertMany($columns, [$data[0], $data[1]], true, false);
        $expectedData = [
            TestingAdmin::fromArray($data[0], true)
                ->getValuesForInsertQuery($columns),
            TestingAdmin::fromArray($data[1], true)
                ->getValuesForInsertQuery($columns),
        ];
        $expectedData[0]['created_at'] .= '+00';
        $expectedData[0]['updated_at'] .= '+00';
        $expectedData[1]['created_at'] .= '+00';
        $expectedData[1]['updated_at'] .= '+00';
        static::assertEquals($expectedData[0], $insertedRows[0]);
        static::assertEquals($expectedData[1], $insertedRows[1]);

        $columns = array_keys((new TestingAdminsTableStructure())->getRealColumns());
        $insertedRows = TestingAdminsTable::insertMany($columns, [$data[2], $data[3]], true, true);
        $expectedData = [
            TestingAdmin::fromArray($data[2], true)
                ->getValuesForInsertQuery($columns),
            TestingAdmin::fromArray($data[3], true)
                ->getValuesForInsertQuery($columns),
        ];
        $expectedData[0]['created_at'] .= '+00';
        $expectedData[0]['updated_at'] .= '+00';
        $expectedData[1]['created_at'] .= '+00';
        $expectedData[1]['updated_at'] .= '+00';
        static::assertEquals($expectedData[0], $insertedRows[0]);
        static::assertEquals($expectedData[1], $insertedRows[1]);
    }

    public function testInsertMany2(): void
    {
        $newAdmins = [
            [
                'login' => '2AE351AF',
                'password' => '$2y$10$9KOltdqg053WgkQoQT6cU.JkI92qwdRuD1h4E99.zCy1OicQDd.da',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'ip' => '192.168.0.1',
                'name' => 'Lionel Freeman',
                'email' => 'qqq@qqq.co.uk',
                'big_data' => 'biiiiiiig data'
            ],
            [
                'login' => '4FE263E6FF81',
                'password' => '$2y$10$9KOltdqg053WgkQoQT6cU.JkI92qwdRuD1h4E99.zCy1OicQDd.da',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'ip' => '192.168.0.1',
                'name' => 'Lionel Freeman',
                'email' => 'www@qqq.co.uk',
                'big_data' => 'biiiiiiig data'
            ]
        ];
        $columns = array_keys($newAdmins[0]);
        $insertedRows = TestingAdminsTable::insertMany($columns, $newAdmins, true, true);
        $expectedData = [];
        foreach ($insertedRows as $index => $insertedRow) {
            $expectedData[] = array_merge($newAdmins[$index], [
                'id' => $insertedRow['id'],
                'created_at' => $insertedRow['created_at'],
                'updated_at' => $insertedRow['updated_at'],
                'password' => $insertedRow['password'],
                'parent_id' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'role' => '',
                'is_active' => true,
                'timezone' => 'UTC',
                'not_changeable_column' => 'not changable'
            ]);
        }
        static::assertEquals($expectedData[0], $insertedRows[0]);
        static::assertEquals($expectedData[1], $insertedRows[1]);
    }

    public function testInsertMany3(): void
    {
        $newAdmins = [
            [
                'login' => '2AE351AF',
                'password' => 'F7A124FE',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'ip' => '192.168.0.1',
                'name' => 'Lionel Freeman',
                'email' => 'qqq@qqq.co.uk',
                'big_data' => 'biiiiiiig data'
            ],
            [
                'login' => '4FE263E6FF81',
                'password' => '63E6FF',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'ip' => '192.168.0.1',
                'name' => 'Lionel Freeman',
                'email' => 'www@qqq.co.uk',
                'big_data' => 'biiiiiiig data'
            ]
        ];
        $columns = array_diff(
            array_keys((new TestingAdminsTableStructure())->getRealColumns()),
            ['id']
        );
        $insertedRows = TestingAdminsTable::insertMany($columns, $newAdmins, true, false);
        $expectedData = [];
        foreach ($insertedRows as $index => $insertedRow) {
            $expectedData[] = array_merge($newAdmins[$index], [
                'id' => $insertedRow['id'],
                'created_at' => $insertedRow['created_at'],
                'updated_at' => $insertedRow['updated_at'],
                'password' => $insertedRow['password'],
                'parent_id' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'role' => 'guest',
                'is_active' => true,
                'timezone' => 'UTC',
                'not_changeable_column' => 'not changable'
            ]);
        }
        static::assertEquals($expectedData[0], $insertedRows[0]);
        static::assertEquals($expectedData[1], $insertedRows[1]);
    }

    public function testUpdate(): void
    {
        $admins = static::fillAdminsTable(5);
        $adminsCount = count($admins);

        $updatedRows = TestingAdminsTable::update(['is_active' => true], ['is_active' => false], true);
        static::assertCount(0, $updatedRows);

        $updatedRows = TestingAdminsTable::update(['is_active' => false], ['is_active' => true], true);
        static::assertCount($adminsCount, $updatedRows);
        foreach ($updatedRows as $row) {
            static::assertArrayHasKey('id', $row);
            static::assertFalse($row['is_active']);
        }

        $updatedRows = TestingAdminsTable::update(['is_active' => true], ['id' => $admins[1]['id']], true);
        static::assertCount(1, $updatedRows);
        static::assertEquals($admins[1]['id'], $updatedRows[0]['id']);
        static::assertTrue($updatedRows[0]['is_active']);

        $assoc = TestingAdminsTable::selectAssoc('id', 'is_active');
        static::assertCount($adminsCount, $assoc);
        foreach ($assoc as $id => $isActive) {
            if ($id === $admins[1]['id']) {
                static::assertTrue($isActive);
            } else {
                static::assertFalse($isActive);
            }
        }
    }

    public function testDelete(): void
    {
        $admins = static::fillAdminsTable(5);
        $adminsCount = count($admins);

        $deletedAdmins = TestingAdminsTable::delete(['id' => $admins[1]['id']], true);
        static::assertCount(1, $deletedAdmins);
        static::assertEquals($adminsCount - 1, TestingAdminsTable::count());
        static::assertFalse(TestingAdminsTable::hasMatchingRecord(['id' => $admins[1]['id']]));
        $adminsCount--;

        $updatesCount = TestingAdminsTable::update(['is_active' => false], ['id' => $admins[2]['id']]);
        static::assertEquals(1, $updatesCount);
        $deletedCount = TestingAdminsTable::delete(['is_active' => false]);
        static::assertEquals(1, $deletedCount);
        static::assertEquals($adminsCount - 1, TestingAdminsTable::count());
        static::assertFalse(TestingAdminsTable::hasMatchingRecord(['id' => $admins[2]['id']]));
        $adminsCount--;

        $deletedCount = TestingAdminsTable::delete([DbExpr::create('``1``')]);
        static::assertEquals($adminsCount, $deletedCount);
        static::assertEquals(0, TestingAdminsTable::count());
    }
}