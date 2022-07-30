<?php

declare(strict_types=1);

namespace Tests\Core;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Select;
use PHPUnit\Framework\TestCase;
use Tests\PeskyORMTest\Data\TestDataForAdminsTable;
use Tests\PeskyORMTest\TestingApp;

class PostgresAdapterSelectDataTest extends TestCase
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
    
    public function testSelects()
    {
        $adapter = static::getValidAdapter();
        TestingApp::clearTables($adapter);
        $testData = $this->getTestDataForAdminsTableInsert();
        $dataForAssert = $this->convertTestDataForAdminsTableAssert($testData);
        $adapter->insertMany('admins', array_keys($testData[0]), $testData);
        
        $data = $adapter->select('admins', [], DbExpr::create('ORDER BY `id`', false));
        $this->assertEquals($dataForAssert[0], $data[0]);
        $this->assertEquals($dataForAssert[1], $data[1]);
        
        $data = $adapter->select(
            'admins',
            ['id', 'parent_id'],
            DbExpr::create(
                "WHERE `id` IN (``{$testData[0]['id']}``)"
            )
        );
        $this->assertCount(1, $data);
        $this->assertCount(2, $data[0]);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('parent_id', $data[0]);
        $this->assertArraySubset($data[0], $dataForAssert[0]);
        
        $data = $adapter->selectOne(
            'admins',
            [],
            DbExpr::create(
                "WHERE `id` IN (``{$testData[0]['id']}``)"
            )
        );
        $this->assertEquals($dataForAssert[0], $data);
        
        $data = $adapter->selectColumn('admins', 'email', DbExpr::create('ORDER BY `id`'));
        $this->assertCount(2, $data);
        $this->assertEquals([$dataForAssert[0]['email'], $dataForAssert[1]['email']], $data);
        
        $data = $adapter->selectAssoc('admins', 'id', 'email', DbExpr::create('ORDER BY `id`'));
        $this->assertCount(2, $data);
        $this->assertEquals(
            [
                $dataForAssert[0]['id'] => $dataForAssert[0]['email'],
                $dataForAssert[1]['id'] => $dataForAssert[1]['email'],
            ],
            $data
        );
        
        $data = $adapter->selectValue('admins', DbExpr::create('COUNT(`*`)'));
        $this->assertEquals(2, $data);
    }
    
    public function testInvalidAnalyzeColumnName1()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('ERROR:  column "test test" does not exist');
        // it is actually valid for PostgreSQL but not valid for MySQL
        static::getValidAdapter()->selectColumn('admins', 'test test');
    }
    
    public function testInvalidAnalyzeColumnName2()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('ERROR:  column "test%test" does not exist');
        // it is actually valid for PostgreSQL but not valid for MySQL
        static::getValidAdapter()->selectColumn('admins', 'test%test');
    }
    
    public function testInvalidWith1()
    {
        $select = new Select('admins', static::getValidAdapter());
        $withSelect = new Select('admins', static::getValidAdapter());
        // it is actually valid for PostgreSQL but not valid for MySQL
        $select->with($withSelect, 'asdas as das das');
        static::assertTrue(true);
    }
}
