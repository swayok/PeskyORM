<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PDO;
use PeskyORM\Adapter\Postgres;
use PeskyORM\DbExpr;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use ReflectionClass;

class PostgresAdapterGeneralFunctionalityTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    private static function getValidAdapter(): Postgres
    {
        return TestingApp::getPgsqlConnection();
    }
    
    public function testQuotingOfInvalidDbEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('";DROP table1;');
    }
    
    public function testQuotingOfInvalidDbEntity2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$name) must be of type string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $adapter->quoteDbEntityName(['arrr']);
    }
    
    public function testQuotingOfInvalidDbEntity3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$name) must be of type string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $adapter->quoteDbEntityName($adapter);
    }
    
    public function testQuotingOfInvalidDbEntity4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$name) must be of type string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpStrictTypeCheckingInspection */
        $adapter->quoteDbEntityName(true);
    }
    
    public function testQuotingOfInvalidDbEntity5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$name) must be of type string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpStrictTypeCheckingInspection */
        $adapter->quoteDbEntityName(false);
    }
    
    public function testQuotingOfInvalidDbEntity6(): void
    {
        // OK for PostgreSQL
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname->->');
        static::assertTrue(true);
    }
    
    public function testQuotingOfInvalidDbEntity7(): void
    {
        // OK for PostgreSQL
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname-> ->');
        static::assertTrue(true);
    }
    
    public function testQuotingOfInvalidDbEntity8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('');
    }
    
    public function testQuotingOfInvalidDbValueType(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$valueDataType) must be of type ?int");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpStrictTypeCheckingInspection */
        $adapter->quoteValue('test', 'abrakadabra');
    }
    
    public function testQuotingOfInvalidIntDbValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$value expected to be integer or numeric string. String [abrakadabra] received');
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('abrakadabra', PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($value) must be of type');
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteValue($adapter, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$value expected to be integer or numeric string. Array received');
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(['key' => 'val'], PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($value) must be of type');
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(curl_init('http://test.url'), PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$value expected to be integer or numeric string. Boolean [true] received');
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(true, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$value expected to be integer or numeric string. Boolean [false] received');
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(false, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidDbExpr(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($expression) must be of type PeskyORM\DbExpr');
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteDbExpr('test');
    }
    
    public function testQuoting(): void
    {
        $adapter = static::getValidAdapter();
        // names
        static::assertEquals('"table1"', $adapter->quoteDbEntityName('table1'));
        static::assertEquals('*', $adapter->quoteDbEntityName('*'));
        static::assertEquals('"table"."colname"', $adapter->quoteDbEntityName('table.colname'));
        static::assertEquals('"table"."colname"->\'jsonkey\'', $adapter->quoteDbEntityName('table.colname->jsonkey'));
        static::assertEquals('"table"."colname"#>\'jsonkey\'', $adapter->quoteDbEntityName('table.colname #> jsonkey'));
        static::assertEquals('"table"."colname"->>\'json key\'', $adapter->quoteDbEntityName('table.colname ->> json key'));
        static::assertEquals('"table"."colname"#>>\'json key\'', $adapter->quoteDbEntityName('table.colname #>> \'json key\''));
        static::assertEquals('"table"."colname"->\'json key\'', $adapter->quoteDbEntityName('table.colname -> "json key"'));
        static::assertEquals(
            '"table"."colname"->\'json key\'->>\'json key 2\'',
            $adapter->quoteDbEntityName('table.colname -> "json key" ->> json key 2')
        );
        // values
        static::assertEquals("''';DROP table1;'", $adapter->quoteValue('\';DROP table1;'));
        static::assertEquals('TRUE', $adapter->quoteValue(true));
        static::assertEquals('TRUE', $adapter->quoteValue(1, PDO::PARAM_BOOL));
        static::assertEquals('TRUE', $adapter->quoteValue('1', PDO::PARAM_BOOL));
        static::assertEquals('FALSE', $adapter->quoteValue(false));
        static::assertEquals('FALSE', $adapter->quoteValue(0, PDO::PARAM_BOOL));
        static::assertEquals('FALSE', $adapter->quoteValue('0', PDO::PARAM_BOOL));
        static::assertEquals('NULL', $adapter->quoteValue(null));
        static::assertEquals('NULL', $adapter->quoteValue('abrakadabra', PDO::PARAM_NULL));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_INT));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_BOOL));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_STR));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_LOB));
        static::assertEquals("'123'", $adapter->quoteValue(123));
        static::assertEquals("'123'", $adapter->quoteValue(123, PDO::PARAM_INT));
        static::assertEquals(
            'DELETE FROM "table1" WHERE "col1" = \'value1\'',
            $adapter->quoteDbExpr(DbExpr::create('DELETE FROM `table1` WHERE `col1` = ``value1``'))
        );
    }
    
    public function testBuildColumnsList(): void
    {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildColumnsList');
        $method->setAccessible(true);
        $colsList = $method->invoke($adapter, ['column1', 'alias.column2']);
        static::assertEquals('("column1", "alias"."column2")', $colsList);
    }
    
    public function testInvalidColumnsInBuildValuesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument cannot be empty");
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $method->invoke($adapter, [], []);
    }
    
    public function testInvalidDataInBuildValuesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$valuesAssoc array does not contain key [col2]");
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $method->invoke($adapter, ['col1', 'col2'], ['col1' => '1']);
    }
    
    public function testBuildValuesList(): void
    {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $data = [
            'col1' => 'val1',
            'col2' => 1,
            'col3' => null,
            'col4' => true,
            'col5' => false,
            'col6' => '',
            'col7' => 1.22,
        ];
        $columns = array_keys($data);
        $valsList = $method->invoke($adapter, $columns, $data);
        static::assertEquals("('val1', '1', NULL, TRUE, FALSE, '', '1.22')", $valsList);
        $valsList = $method->invoke($adapter, $columns, $data, ['col2' => PDO::PARAM_BOOL]);
        static::assertEquals("('val1', TRUE, NULL, TRUE, FALSE, '', '1.22')", $valsList);
    }
    
    
}
