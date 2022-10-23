<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\TableDescription\DescribeTable;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class PostgresAdapterHelpersTest extends BaseTestCase
{
    
    /**
     * @return PostgresTesting
     */
    protected static function getValidAdapter(): DbAdapterInterface
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public function testInvalidTable(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardTableNameArg(null);
    }
    
    public function testInvalidTable2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$table argument cannot be empty');
        static::getValidAdapter()->guardTableNameArg('');
    }
    
    public function testInvalidTable3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardTableNameArg(false);
    }
    
    public function testInvalidTable4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardTableNameArg(true);
    }
    
    public function testInvalidTable5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardTableNameArg($this);
    }
    
    public function testInvalidTable6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardTableNameArg(123);
    }
    
    public function testInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$data argument cannot be empty');
        static::getValidAdapter()->guardDataArg([]);
    }
    
    public function testInvalidData2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($data) must be of type array, string given');
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardDataArg('test');
    }
    
    public function testInvalidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns argument cannot be empty');
        static::getValidAdapter()->guardColumnsArg([]);
    }
    
    public function testInvalidColumns2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columns) must be of type array, string given');
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardColumnsArg('test');
    }
    
    public function testInvalidColumns3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns argument must contain only strings and DbExpr objects');
        static::getValidAdapter()->guardColumnsArg([$this]);
    }
    
    public function testInvalidColumns4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns argument must contain only strings');
        static::getValidAdapter()->guardColumnsArg([DbExpr::create('test')], false);
    }
    
    public function testInvalidConditions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditions argument is not allowed to be empty');
        static::getValidAdapter()->guardConditionsArg('');
    }
    
    public function testInvalidConditions2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardConditionsArg([]);
    }
    
    public function testInvalidConditions3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type PeskyORM\Core\DbExpr|string');
        static::getValidAdapter()->guardConditionsArg(new \Exception('test'));
    }
    
    public function testInvalidConditions4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardConditionsArg(false);
    }
    
    public function testInvalidConditions5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardConditionsArg(true);
    }
    
    public function testInvalidConditions6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardConditionsArg(123);
    }
    
    public function testInvalidReturning(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardReturningArg('*');
    }
    
    public function testInvalidReturning2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardReturningArg(new \Exception('test'));
    }
    
    public function testInvalidReturning3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardReturningArg(123);
    }
    
    public function testInvalidPkName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$pkName argument cannot be empty");
        static::getValidAdapter()->guardPkNameArg('');
    }
    
    public function testInvalidPkName2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($pkName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getValidAdapter()->guardPkNameArg(true);
    }
    
    public function testInvalidPkName3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$pkName) must be of type string, array given");
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardPkNameArg([]);
    }
    
    public function testInvalidPkName4(): void
    {
        // it is valid for PostgreSQL
        static::getValidAdapter()->guardPkNameArg('teasd as das d 90as9()');
        static::assertTrue(true);
    }
    
    public function testInvalidPkName5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$pkName) must be of type string");
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        static::getValidAdapter()->guardPkNameArg(DbExpr::create('test'));
    }
    
    public function testValidArgs(): void
    {
        static::getValidAdapter()->guardTableNameArg('table');
        static::getValidAdapter()->guardDataArg(['key' => 'value']);
        static::getValidAdapter()->guardColumnsArg(['key1', DbExpr::create('key2')]);
        static::getValidAdapter()->guardConditionsArg('test');
        static::getValidAdapter()->guardConditionsArg(DbExpr::create('test'));
        static::getValidAdapter()->guardReturningArg(true);
        static::getValidAdapter()->guardReturningArg(false);
        static::getValidAdapter()->guardReturningArg(['key1', 'key2']);
        static::assertTrue(true);
    }
    
    public function testInvalidConvertConditionOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition operator [>=] does not support list of values");
        $adapter = self::getValidAdapter();
        $adapter->convertConditionOperator('>=', [1, 2, 3]);
    }
    
    public function testConvertConditionOperatorForNullValue(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->convertConditionOperator('=', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('IS', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('>=', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('OPER', null);
        static::assertEquals('IS', $operator);
        
        $operator = $adapter->convertConditionOperator('IS', '1');
        static::assertEquals('=', $operator);
        
        $operator = $adapter->convertConditionOperator('!=', null);
        static::assertEquals('IS NOT', $operator);
        $operator = $adapter->convertConditionOperator('NOT', null);
        static::assertEquals('IS NOT', $operator);
        $operator = $adapter->convertConditionOperator('IS NOT', null);
        static::assertEquals('IS NOT', $operator);
        
        $operator = $adapter->convertConditionOperator('NOT', '1');
        static::assertEquals('!=', $operator);
        $operator = $adapter->convertConditionOperator('IS NOT', '1');
        static::assertEquals('!=', $operator);
    }
    
    public function testConvertConditionOperatorForArrayValue(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->convertConditionOperator('=', [1, 2, 3]);
        static::assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('!=', [1, 2, 3]);
        static::assertEquals('NOT IN', $operator);
        
        $operator = $adapter->convertConditionOperator('IN', 1);
        static::assertEquals('=', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', 1);
        static::assertEquals('!=', $operator);
        
        $operator = $adapter->convertConditionOperator('IN', [1, 2, 3]);
        static::assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', [1, 2, 3]);
        static::assertEquals('NOT IN', $operator);
        
        $operator = $adapter->convertConditionOperator('IN', DbExpr::create('SELECT'));
        static::assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', DbExpr::create('SELECT'));
        static::assertEquals('NOT IN', $operator);
    }
    
    public function testConvertConditionOperator(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->convertConditionOperator('BETWEEN', [1, 2, 3]);
        static::assertEquals('BETWEEN', $operator);
        $operator = $adapter->convertConditionOperator('NOT BETWEEN', 'oo');
        static::assertEquals('NOT BETWEEN', $operator);
        
        $operator = $adapter->convertConditionOperator('>', 1);
        static::assertEquals('>', $operator);
        $operator = $adapter->convertConditionOperator('<', 'a');
        static::assertEquals('<', $operator);
        $operator = $adapter->convertConditionOperator('CUSTOM', 'qwe');
        static::assertEquals('CUSTOM', $operator);
        $operator = $adapter->convertConditionOperator('LIKE', 'weqwe');
        static::assertEquals('LIKE', $operator);
        $operator = $adapter->convertConditionOperator('NOT LIKE', 'ewqewqe');
        static::assertEquals('NOT LIKE', $operator);
    }
    
    public function testConvertConditionOperatorForStringComparison(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->convertConditionOperator('SIMILAR TO', 'qwe');
        static::assertEquals('SIMILAR TO', $operator);
        $operator = $adapter->convertConditionOperator('NOT SIMILAR TO', 'qwe');
        static::assertEquals('NOT SIMILAR TO', $operator);
        
        $operator = $adapter->convertConditionOperator('REGEXP', 'wqe');
        static::assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEXP', 'ewqe');
        static::assertEquals('!~*', $operator);
        
        $operator = $adapter->convertConditionOperator('REGEX', 'weq');
        static::assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEX', 'qwe');
        static::assertEquals('!~*', $operator);
        
        $operator = $adapter->convertConditionOperator('~', 'ewq');
        static::assertEquals('~', $operator);
        $operator = $adapter->convertConditionOperator('!~', 'ewqe');
        static::assertEquals('!~', $operator);
        
        $operator = $adapter->convertConditionOperator('~*', 'ewqe');
        static::assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('!~*', 'ewqe');
        static::assertEquals('!~*', $operator);
    }
    
    public function testInvalidArgsInMakeSelectQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$table argument cannot be empty and must be a non-numeric string");
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('');
    }
    
    public function testInvalidArgsInMakeSelectQuery2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings and DbExpr objects");
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [$this]);
    }
    
    public function testInvalidArgsInMakeSelectQuery3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #3 (\$conditionsAndOptions) must be of type ?PeskyORM\Core\DbExpr");
        $adapter = self::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->makeSelectQuery('table', [], 'string');
    }
    
    public function testMakeSelectQuery(): void
    {
        $adapter = self::getValidAdapter();
        
        $query = $adapter->makeSelectQuery('test_table', ['col1', DbExpr::create('`col2` as `col22`')]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `col1`, ' . '(`col2` as `col22`) FROM `test_table`', //< concatenation needed to avoid annoying PHPStorm inspections
                    false
                )
            ),
            $query
        );
        
        $query = $adapter->makeSelectQuery('test_table', [], DbExpr::create('WHERE `col1` > ``0``', false));
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `*`' . ' FROM `test_table` WHERE `col1` > ``0``', //< concatenation needed to avoid annoying PHPStorm inspections
                    false
                )
            ),
            $query
        );
    }
    
    public function testQuoteJsonSelectorExpression(): void
    {
        static::assertEquals(
            '"table"."col_name"->\'key1\'->>\'key 2\'#>\'key 3\'#>>\'key 4\'',
            static::getValidAdapter()->quoteJsonSelectorExpression([
                'table.col_name',
                '->',
                'key1',
                '->>',
                '"key 2"',
                '#>',
                '`key 3`',
                '#>>',
                "'key 4'",
            ])
        );
        static::assertEquals(
            '"table"."col_name"->2',
            static::getValidAdapter()->quoteJsonSelectorExpression([
                'table.col_name',
                '->',
                '2',
            ])
        );
    }
    
    public function testIsValidDbEntityNameAndJsonSelector1(): void
    {
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test->test'));
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('_test #> `test`'));
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('t2est ->>"test"'));
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test->> \'test\''));
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test->test->>test#>test#>>test'));
        
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector(''));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test->'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test->""->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test->\'->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test->```->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('0test test->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('$test test->test'));
        
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('_test._test2'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('t2est.*'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('*'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test->test'));
        
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName('0test'));
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName('$test'));
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName(''));
    }
    
    public function testIsValidDbEntityNameAndJsonSelector2(): void
    {
        // for PostgreSQL these are ok while for other RDBMS it might be not
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test test->test'));
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test#test->test'));
    
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test test'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test$test'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test->test', false));
    }
    
    public function testInvalidAssembleConditionValue11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(null, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue12(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(1, 'NOT BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue13(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(false, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue14(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(true, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue15(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue([], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue16(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue([true], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue17(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue([1, 2, 3], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue18(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean");
        static::getValidAdapter()
            ->assembleConditionValue([true, false], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue19(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean");
        static::getValidAdapter()
            ->assembleConditionValue([null, 1], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue110(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean");
        static::getValidAdapter()
            ->assembleConditionValue([1, null], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue21(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty array is not allowed as condition value");
        static::getValidAdapter()
            ->assembleConditionValue([], '=');
    }
    
    public function testAssembleConditionValue(): void
    {
        $adapter = static::getValidAdapter();
        $dbexpr = DbExpr::create('``test`` = `expr`');
        static::assertEquals(
            $adapter->quoteDbExpr($dbexpr),
            $adapter->assembleConditionValue($dbexpr, 'doesnt matter')
        );
        static::assertEquals(
            $adapter->quoteDbExpr($dbexpr),
            $adapter->assembleConditionValue($dbexpr, 'doesnt matter', true)
        );
        static::assertEquals(
            $adapter->quoteValue(10) . ' AND ' . $adapter->quoteValue(20),
            $adapter->assembleConditionValue([10, 20], 'BETWEEN')
        );
        static::assertEquals(
            'str1 AND str2',
            $adapter->assembleConditionValue(['str1', 'str2'], 'BETWEEN', true)
        );
        static::assertEquals(
            'str1',
            $adapter->assembleConditionValue('str1', '=', true)
        );
        static::assertEquals(
            $adapter->quoteValue(DbExpr::create('11')) . ' AND ' . $adapter->quoteValue(DbExpr::create('21')),
            $adapter->assembleConditionValue([DbExpr::create('11'), DbExpr::create('21')], 'NOT BETWEEN')
        );
        static::assertEquals(
            '(' . $adapter->quoteValue(11) . ', ' . $adapter->quoteValue(12) . ', ' . $adapter->quoteValue(DbExpr::create('13')) . ')',
            $adapter->assembleConditionValue([11, 12, DbExpr::create('13')], '=')
        );
        static::assertEquals(
            $adapter->quoteValue('string'),
            $adapter->assembleConditionValue('string', '=')
        );
    }
    
    public function testInvalidConditionValueWithQuotedFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value with \$valueAlreadyQuoted === true must be a string. array received");
        static::getValidAdapter()
            ->assembleConditionValue(['key' => 'value'], '@>', true);
    }
    
    public function testAssembleConditionValueAdapterSpecific(): void
    {
        $adapter = static::getValidAdapter();
        static::assertEquals(
            $adapter->quoteValue('string') . '::jsonb',
            $adapter->assembleConditionValue('string', '@>')
        );
        static::assertEquals(
            'string::jsonb',
            $adapter->assembleConditionValue('string', '@>', true)
        );
        static::assertEquals(
            $adapter->quoteValue(json_encode(['key' => 'value'])) . '::jsonb',
            $adapter->assembleConditionValue(['key' => 'value'], '@>')
        );
        static::assertEquals(
            json_encode(['key' => 'value']) . '::jsonb',
            $adapter->assembleConditionValue(json_encode(['key' => 'value']), '@>', true)
        );
        static::assertEquals(
            $adapter->quoteValue(json_encode([1, 2, 3])) . '::jsonb',
            $adapter->assembleConditionValue([1, 2, 3], '<@')
        );
        static::assertEquals(
            json_encode([1, 2, 3]) . '::jsonb',
            $adapter->assembleConditionValue(json_encode([1, 2, 3]), '<@', true)
        );
    }
    
    public function testAssembleCondition(): void
    {
        $adapter = static::getValidAdapter();
        $column = $adapter->quoteDbEntityName('colname');
        static::assertEquals(
            $column . ' = ' . $adapter->assembleConditionValue('test', '='),
            $adapter->assembleCondition($column, '=', 'test')
        );
        static::assertEquals(
            $column . ' = ' . $adapter->assembleConditionValue('test', '=', true),
            $adapter->assembleCondition($column, '=', 'test', true)
        );
        static::assertEquals(
            $column . ' IN ' . $adapter->assembleConditionValue([1, 2], 'IN'),
            $adapter->assembleCondition($column, 'IN', [1, 2])
        );
        static::assertEquals(
            $column . ' IN ' . $adapter->assembleConditionValue([1, 2], 'IN', true),
            $adapter->assembleCondition($column, 'IN', [1, 2], true)
        );
        static::assertEquals(
            $column . ' BETWEEN ' . $adapter->assembleConditionValue([1, 2], 'BETWEEN'),
            $adapter->assembleCondition($column, 'BETWEEN', [1, 2])
        );
        static::assertEquals(
            $column . ' BETWEEN ' . $adapter->assembleConditionValue([1, 2], 'BETWEEN', true),
            $adapter->assembleCondition($column, 'BETWEEN', [1, 2], true)
        );
    }
    
    public function testAssembleConditionAdapterSpecific(): void
    {
        $adapter = static::getValidAdapter();
        $column = $adapter->quoteDbEntityName('colname');
        static::assertEquals(
            $column . ' @> ' . $adapter->assembleConditionValue('test', '@>'),
            $adapter->assembleCondition($column, '@>', 'test')
        );
        static::assertEquals(
            $column . ' @> ' . $adapter->assembleConditionValue('test', '@>', true),
            $adapter->assembleCondition($column, '@>', 'test', true)
        );
        static::assertEquals(
            $column . ' <@ ' . $adapter->assembleConditionValue('test', '<@'),
            $adapter->assembleCondition($column, '<@', 'test')
        );
        static::assertEquals(
            $column . ' <@ ' . $adapter->assembleConditionValue('test', '<@', true),
            $adapter->assembleCondition($column, '<@', 'test', true)
        );
        static::assertEquals(
            "{$column} ?? " . $adapter->assembleConditionValue('test', '?'),
            $adapter->assembleCondition($column, '?', 'test')
        );
        static::assertEquals(
            "{$column} ?? " . $adapter->assembleConditionValue('test', '?', true),
            $adapter->assembleCondition($column, '?', 'test', true)
        );
        static::assertEquals(
            "{$column} ??| array[" . $adapter->quoteValue('test') . ']',
            $adapter->assembleCondition($column, '?|', 'test')
        );
        static::assertEquals(
            "{$column} ??| array[test]",
            $adapter->assembleCondition($column, '?|', 'test', true)
        );
        static::assertEquals(
            "{$column} ??| array[" . $adapter->quoteValue('test1') . ', ' . $adapter->quoteValue('test2') . ']',
            $adapter->assembleCondition($column, '?|', ['test1', 'test2'])
        );
        static::assertEquals(
            "{$column} ??| array[test1, test2]",
            $adapter->assembleCondition($column, '?|', ['test1', 'test2'], true)
        );
        static::assertEquals(
            "{$column} ??& array[" . $adapter->quoteValue('test') . ']',
            $adapter->assembleCondition($column, '?&', 'test')
        );
        static::assertEquals(
            "{$column} ??& array[test]",
            $adapter->assembleCondition($column, '?&', 'test', true)
        );
        static::assertEquals(
            "{$column} ??& array[" . $adapter->quoteValue('test1') . ', ' . $adapter->quoteValue('test2') . ']',
            $adapter->assembleCondition($column, '?&', ['test1', 'test2'])
        );
        static::assertEquals(
            "{$column} ??& array[test1, test2]",
            $adapter->assembleCondition($column, '?&', ['test1', 'test2'], true)
        );
    }
}