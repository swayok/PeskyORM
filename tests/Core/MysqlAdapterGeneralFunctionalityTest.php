<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PDO;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Core\DbExpr;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use ReflectionClass;

class MysqlAdapterGeneralFunctionalityTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    private static function getValidAdapter(): Mysql
    {
        return TestingApp::getMysqlConnection();
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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name: [colname->->]");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname->->');
    }
    
    public function testQuotingOfInvalidDbEntity7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name: [colname-> ->]");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname-> ->');
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
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. String [abrakadabra] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('abrakadabra', PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Object fo class [\PeskyORM\Adapter\Mysql] received");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteValue($adapter, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Array received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(['key' => 'val'], PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string.");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(curl_init('http://test.url'), PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Boolean [true] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(true, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Boolean [false] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(false, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidDbExpr(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$expression) must be of type PeskyORM\Core\DbExpr");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteDbExpr('test');
    }
    
    public function testQuoting(): void
    {
        $adapter = static::getValidAdapter();
        // names
        static::assertEquals('`table1`', $adapter->quoteDbEntityName('table1'));
        static::assertEquals('*', $adapter->quoteDbEntityName('*'));
        static::assertEquals('`table`.`colname`', $adapter->quoteDbEntityName('table.colname'));
        static::assertEquals(
            '`table`.`colname`->\'$.jsonkey\'',
            $adapter->quoteDbEntityName('table.colname->jsonkey')
        );
        static::assertEquals(
            'JSON_EXTRACT(`table`.`colname`, \'$.jsonkey\')',
            $adapter->quoteDbEntityName('table.colname #> jsonkey')
        );
        static::assertEquals(
            '`table`.`colname`->>\'$[0][1]\'',
            $adapter->quoteDbEntityName('table.colname ->> [0][1]')
        );
        static::assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(`table`.`colname`, \'$.json key\'))',
            $adapter->quoteDbEntityName('table.colname #>> \'json key\'')
        );
        static::assertEquals(
            '`table`.`colname`->\'$.json key\'',
            $adapter->quoteDbEntityName('table.colname -> "json key"')
        );
        static::assertEquals(
            '`table`.`colname`->\'$.json key\'->>\'$.json key 2\'',
            $adapter->quoteDbEntityName('table.colname -> "json key" ->> json key 2')
        );
        static::assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(`table`.`colname`->\'$.json key\', \'$.json key 2\'))',
            $adapter->quoteDbEntityName('table.colname -> "json key" #>> json key 2')
        );
        static::assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(`table`.`colname`, \'$.json key\'), \'$.json key 2\'))',
            $adapter->quoteDbEntityName('table.colname #> "json key" #>> json key 2')
        );
        // values
        static::assertEquals("'\\';DROP table1;'", $adapter->quoteValue('\';DROP table1;'));
        static::assertEquals('1', $adapter->quoteValue(true));
        static::assertEquals('1', $adapter->quoteValue(1, PDO::PARAM_BOOL));
        static::assertEquals('1', $adapter->quoteValue('1', PDO::PARAM_BOOL));
        static::assertEquals('0', $adapter->quoteValue(false));
        static::assertEquals('0', $adapter->quoteValue(0, PDO::PARAM_BOOL));
        static::assertEquals('0', $adapter->quoteValue('0', PDO::PARAM_BOOL));
        static::assertEquals('NULL', $adapter->quoteValue(null));
        static::assertEquals('NULL', $adapter->quoteValue('abrakadabra', PDO::PARAM_NULL));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_INT));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_BOOL));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_STR));
        static::assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_LOB));
        static::assertEquals("'123'", $adapter->quoteValue(123));
        static::assertEquals("'123'", $adapter->quoteValue(123, PDO::PARAM_INT));
        /** @noinspection SqlWithoutWhere */
        static::assertEquals(
            'DELETE FROM `table1` WHERE `col1` = \'value1\'',
            $adapter->quoteDbExpr(DbExpr::create('DELETE FROM `table1` WHERE `col1` = ``value1``'))
        );
    }
    
    public function testBuildColumnsList(): void
    {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildColumnsList');
        $method->setAccessible(true);
        $colsList = $method->invoke($adapter, ['column1', 'alias.column2']);
        static::assertEquals('(`column1`, `alias`.`column2`)', $colsList);
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
        static::assertEquals("('val1', '1', NULL, 1, 0, '', '1.22')", $valsList);
        $valsList = $method->invoke($adapter, $columns, $data, ['col2' => PDO::PARAM_BOOL]);
        static::assertEquals("('val1', 1, NULL, 1, 0, '', '1.22')", $valsList);
    }
}
