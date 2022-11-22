<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use InvalidArgumentException;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Select\Select;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\Data\TestDataForAdminsTable;
use PeskyORM\Tests\PeskyORMTest\Data\TestDataForSettingsTable;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class PostgresAdapterSelectDataTest extends BaseTestCase
{
    use TestDataForAdminsTable;
    use TestDataForSettingsTable;
    
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

    public function testInvalidColumnsListInSelectMany1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of');
        static::getValidAdapter()->select('admins', [$this]);
    }

    public function testInvalidColumnsListInSelectMany2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of');
        static::getValidAdapter()->selectOne('admins', [$this]);
    }

    public function testInvalidColumnsListInSelectMany3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getValidAdapter()->select('admins', ['']);
    }

    public function testInvalidColumnsListInSelectMany4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getValidAdapter()->select('admins', [0]);
    }

    public function testInvalidColumnsListInSelectMany5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getValidAdapter()->select('admins', [null]);
    }

    public function testInvalidColumnsListInSelectMany6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of');
        static::getValidAdapter()->select('admins', [true]);
    }

    public function testInvalidColumnsListInSelectMany7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getValidAdapter()->select('admins', [false]);
    }

    public function testInvalidColumnsListInSelectAssoc1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$keysColumn argument value cannot be empty');
        static::getValidAdapter()->selectAssoc('admins', '', '');
    }

    public function testInvalidColumnsListInSelectAssoc2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$valuesColumn argument value cannot be empty');
        static::getValidAdapter()->selectAssoc('admins', 'qq', '');
    }
    
    public function testSelects(): void
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData = $this->getTestDataForAdminsTableInsert();
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData);
        $adapter->insertMany('admins', array_keys($testData[0]), $testData);
        
        $data = $adapter->select('admins', [], DbExpr::create('ORDER BY `id`', false));
        static::assertEquals($dataForAssert[0], $data[0]);
        static::assertEquals($dataForAssert[1], $data[1]);

        $data = $adapter->select('admins', [], ['ORDER' => ['id' => 'asc']]);
        static::assertEquals($dataForAssert[0], $data[0]);
        static::assertEquals($dataForAssert[1], $data[1]);
        
        $data = $adapter->select(
            'admins',
            ['id', 'parent_id'],
            \PeskyORM\DbExpr::create(
                "WHERE `id` IN (``{$testData[0]['id']}``)"
            )
        );
        static::assertCount(1, $data);
        static::assertCount(2, $data[0]);
        static::assertArrayHasKey('id', $data[0]);
        static::assertArrayHasKey('parent_id', $data[0]);
        static::assertArraySubset($data[0], $dataForAssert[0]);

        $data = $adapter->select(
            'admins',
            ['id', 'parent_id'],
            ['id' => [$testData[0]['id']]]
        );
        static::assertCount(1, $data);
        static::assertCount(2, $data[0]);
        static::assertArrayHasKey('id', $data[0]);
        static::assertArrayHasKey('parent_id', $data[0]);
        static::assertArraySubset($data[0], $dataForAssert[0]);
        
        $data = $adapter->selectOne(
            'admins',
            [],
            \PeskyORM\DbExpr::create(
                "WHERE `id` IN (``{$testData[0]['id']}``)"
            )
        );
        static::assertEquals($dataForAssert[0], $data);

        $data = $adapter->selectOne(
            'admins',
            [],
            ['id' => [$testData[0]['id']]]
        );
        static::assertEquals($dataForAssert[0], $data);
        
        $data = $adapter->selectColumn('admins', 'email', \PeskyORM\DbExpr::create('ORDER BY `id`'));
        static::assertCount(2, $data);
        static::assertEquals([$dataForAssert[0]['email'], $dataForAssert[1]['email']], $data);

        $data = $adapter->selectColumn('admins', 'email', ['ORDER' => ['id' => 'asc']]);
        static::assertCount(2, $data);
        static::assertEquals([$dataForAssert[0]['email'], $dataForAssert[1]['email']], $data);
        
        $data = $adapter->selectAssoc('admins', 'id', 'email', \PeskyORM\DbExpr::create('ORDER BY `id`'));
        static::assertCount(2, $data);
        static::assertEquals(
            [
                $dataForAssert[0]['id'] => $dataForAssert[0]['email'],
                $dataForAssert[1]['id'] => $dataForAssert[1]['email'],
            ],
            $data
        );

        $data = $adapter->selectAssoc('admins', 'id', 'email', ['ORDER' => ['id' => 'asc']]);
        static::assertCount(2, $data);
        static::assertEquals(
            [
                $dataForAssert[0]['id'] => $dataForAssert[0]['email'],
                $dataForAssert[1]['id'] => $dataForAssert[1]['email'],
            ],
            $data
        );
        
        $data = $adapter->selectValue('admins', \PeskyORM\DbExpr::create('COUNT(`*`)'));
        static::assertEquals(2, $data);
    }
    
    public function testJsonSelects(): void
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData = $this->getTestDataForSettingsTableInsert();
        $adapter->insertMany('settings', array_keys($testData[0]), $testData);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value`->``test4``->>``sub1`` = ``val1``")
        );
        static::assertNotEmpty($data);
        static::assertEquals(3, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value`#>>``{test4,sub1}`` = ``val1``")
        );
        static::assertNotEmpty($data);
        static::assertEquals(3, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value`#>>``{test4,sub1}`` IS NOT NULL")
        );
        static::assertNotEmpty($data);
        static::assertEquals(3, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value` ?? ``test4``")
        );
        static::assertNotEmpty($data);
        static::assertEquals(3, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value` ?? ``test1``")
        );
        static::assertNotEmpty($data);
        static::assertEquals(2, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value` ??| array[``test1``, ``test2``]")
        );
        static::assertNotEmpty($data);
        static::assertEquals(2, $data['id']);
    
        $data = $adapter->selectOne(
            'settings',
            ['id'],
            \PeskyORM\DbExpr::create("WHERE `value` ??& array[``test1``, ``test2``]")
        );
        static::assertNotEmpty($data);
        static::assertEquals(2, $data['id']);
    }
    
    public function testInvalidAnalyzeColumnName1(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('ERROR:  column tbl_Admins_0.test test does not exist');
        // it is actually valid for PostgreSQL but not valid for MySQL
        static::getValidAdapter()->selectColumn('admins', 'test test');
    }
    
    public function testInvalidAnalyzeColumnName2(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('ERROR:  column tbl_Admins_0.test%test does not exist');
        // it is actually valid for PostgreSQL but not valid for MySQL
        static::getValidAdapter()->selectColumn('admins', 'test%test');
    }

    public function testInvalidAnalyzeColumnName3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$column argument value cannot be empty");
        static::getValidAdapter()->selectColumn('admins', '');
    }
    
    public function testInvalidWith1(): void
    {
        $select = new \PeskyORM\Select\Select('admins', static::getValidAdapter());
        $withSelect = new Select('admins', static::getValidAdapter());
        // it is actually valid for PostgreSQL but not valid for MySQL
        $select->with($withSelect, 'asdas as das das');
        static::assertTrue(true);
    }
}
