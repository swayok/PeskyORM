<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORMTest\TestingApp;

class PostgresAdapterHelpersTest extends PHPUnit_Framework_TestCase {

    static protected function getValidAdapter() {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }

    /**
     * @param string $methodName
     * @param mixed $arg
     * @param mixed $arg2
     * @return ReflectionMethod
     */
    protected function invokePrivateAdapterMethod($methodName, $arg, $arg2 = '__NOT_SET__') {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod($methodName);
        $method->setAccessible(true);
        if ($arg2 === '__NOT_SET__') {
            return $method->invoke($adapter, $arg);
        } else {
            return $method->invoke($adapter, $arg, $arg2);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable2() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', '');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable3() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable4() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable5() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', new Exception('test'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty
     */
    public function testInvalidTable6() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', 123);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument cannot be empty
     */
    public function testInvalidData() {
        $this->invokePrivateAdapterMethod('guardDataArg', []);
    }

    /**
     * @expectedException TypeError
     * @expectedExceptionMessage guardDataArg() must be of the type array
     */
    public function testInvalidData2() {
        $this->invokePrivateAdapterMethod('guardDataArg', 'test');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument cannot be empty
     */
    public function testInvalidColumns() {
        $this->invokePrivateAdapterMethod('guardColumnsArg', []);
    }

    /**
     * @expectedException TypeError
     * @expectedExceptionMessage guardColumnsArg() must be of the type array
     */
    public function testInvalidColumns2() {
        $this->invokePrivateAdapterMethod('guardColumnsArg', 'test');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and DbExpr objects
     */
    public function testInvalidColumns3() {
        $this->invokePrivateAdapterMethod('guardColumnsArg', [$this]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings
     */
    public function testInvalidColumns4() {
        $this->invokePrivateAdapterMethod('guardColumnsArg', [DbExpr::create('test')], false);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument is not allowed to be empty
     */
    public function testInvalidConditions() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', '');
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument must be a string or DbExpr object
     */
    public function testInvalidConditions2() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', []);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument must be a string or DbExpr object
     */
    public function testInvalidConditions3() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', new Exception('test'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument must be a string or DbExpr object
     */
    public function testInvalidConditions4() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument must be a string or DbExpr object
     */
    public function testInvalidConditions5() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditions argument must be a string or DbExpr object
     */
    public function testInvalidConditions6() {
        $this->invokePrivateAdapterMethod('guardConditionsArg', 123);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditionsAndOptions argument must be an instance of DbExpr class
     */
    public function testInvalidConditionsAndOptions() {
        $this->invokePrivateAdapterMethod('guardConditionsAndOptionsArg', 123);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditionsAndOptions argument must be an instance of DbExpr class
     */
    public function testInvalidConditionsAndOptions2() {
        $this->invokePrivateAdapterMethod('guardConditionsAndOptionsArg', $this);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditionsAndOptions argument must be an instance of DbExpr class
     */
    public function testInvalidConditionsAndOptions3() {
        $this->invokePrivateAdapterMethod('guardConditionsAndOptionsArg', 'string');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturning() {
        $this->invokePrivateAdapterMethod('guardReturningArg', '*');
    }
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturning2() {
        $this->invokePrivateAdapterMethod('guardReturningArg', new Exception('test'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $returning argument must be array or boolean
     */
    public function testInvalidReturning3() {
        $this->invokePrivateAdapterMethod('guardReturningArg', 123);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $pkName argument cannot be empty
     */
    public function testInvalidPkName() {
        $this->invokePrivateAdapterMethod('guardPkNameArg', '');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $pkName argument must be a string
     */
    public function testInvalidPkName2() {
        $this->invokePrivateAdapterMethod('guardPkNameArg', true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $pkName argument cannot be empty
     */
    public function testInvalidPkName3() {
        $this->invokePrivateAdapterMethod('guardPkNameArg', []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid db entity name
     */
    public function testInvalidPkName4() {
        $this->invokePrivateAdapterMethod('guardPkNameArg', 'teasd as das d 90as9()');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $pkName argument must be a string
     */
    public function testInvalidPkName5() {
        $this->invokePrivateAdapterMethod('guardPkNameArg', DbExpr::create('test'));
    }

    public function testValidArgs() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', 'table');
        $this->invokePrivateAdapterMethod('guardDataArg', ['key' => 'value']);
        $this->invokePrivateAdapterMethod('guardColumnsArg', ['key1', DbExpr::create('key2')]);
        $this->invokePrivateAdapterMethod('guardConditionsArg', 'test');
        $this->invokePrivateAdapterMethod('guardConditionsArg', DbExpr::create('test'));
        $this->invokePrivateAdapterMethod('guardReturningArg', true);
        $this->invokePrivateAdapterMethod('guardReturningArg', false);
        $this->invokePrivateAdapterMethod('guardReturningArg', ['key1', 'key2']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition operator [>=] does not support list of values
     */
    public function testInvalidConvertConditionOperator() {
        $adapter = $this->getValidAdapter();
        $adapter->convertConditionOperator('>=', [1, 2, 3]);
    }

    public function testConvertConditionOperatorForNullValue() {
        $adapter = $this->getValidAdapter();

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

    public function testConvertConditionOperatorForArrayValue() {
        $adapter = $this->getValidAdapter();

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

    public function testConvertConditionOperator() {
        $adapter = $this->getValidAdapter();

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

    public function testConvertConditionOperatorForStringComparison() {
        $adapter = $this->getValidAdapter();

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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $table argument cannot be empty and must be a non-numeric string
     */
    public function testInvalidArgsInMakeSelectQuery() {
        $adapter = $this->getValidAdapter();
        $adapter->makeSelectQuery('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and DbExpr objects
     */
    public function testInvalidArgsInMakeSelectQuery2() {
        $adapter = $this->getValidAdapter();
        $adapter->makeSelectQuery('table', [$this]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $conditionsAndOptions argument must be an instance of DbExpr class
     */
    public function testInvalidArgsInMakeSelectQuery3() {
        $adapter = $this->getValidAdapter();
        $adapter->makeSelectQuery('table', [], 'string');
    }

    public function testMakeSelectQuery() {
        $adapter = $this->getValidAdapter();

        $query = $adapter->makeSelectQuery('test_table', ['col1', DbExpr::create('`col2` as `col22`')]);
        static::assertEquals(
            $adapter->quoteDbExpr(DbExpr::create(
                'SELECT `col1`,(`col2` as `col22`) FROM `test_table`',
                false
            )),
            $query
        );

        $query = $adapter->makeSelectQuery('test_table', [], DbExpr::create('WHERE `col1` > ``0``', false));
        static::assertEquals(
            $adapter->quoteDbExpr(DbExpr::create(
                'SELECT `*` FROM `test_table` WHERE `col1` > ``0``',
                false
            )),
            $query
        );
    }

    public function testQuoteJsonSelectorExpression() {
        static::assertEquals(
            '"table"."col_name"->\'key1\'->>\'key 2\'#>\'key 3\'#>>\'key 4\'',
            $this->invokePrivateAdapterMethod('quoteJsonSelectorExpression', [
                'table.col_name', '->', 'key1', '->>', '"key 2"', '#>', '`key 3`', '#>>', "'key 4'"
            ])
        );
        static::assertEquals(
            '"table"."col_name"->2',
            $this->invokePrivateAdapterMethod('quoteJsonSelectorExpression', [
                'table.col_name', '->', '2'
            ])
        );
    }

    public function testIsValidDbEntityNameAndJsonSelector() {
        static::assertTrue($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->test'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidJsonSelector', '_test #> `test`'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidJsonSelector', 't2est ->>"test"'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->> \'test\''));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->test->>test#>test#>>test'));

        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', ''));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', '->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->""->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->\'->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test->```->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test test->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', '0test test->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', '$test test->test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidJsonSelector', 'test#test->test'));

        static::assertTrue($this->invokePrivateAdapterMethod('isValidDbEntityName', 'test'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidDbEntityName', '_test._test2'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidDbEntityName', 't2est.*'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidDbEntityName', '*'));
        static::assertTrue($this->invokePrivateAdapterMethod('isValidDbEntityName', 'test->test'));

        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', 'test test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', '0test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', '$test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', 'test$test'));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', ''));
        static::assertFalse($this->invokePrivateAdapterMethod('isValidDbEntityName', 'test->test', false));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue11() {
        static::getValidAdapter()->assembleConditionValue(null, 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue12() {
        static::getValidAdapter()->assembleConditionValue(1, 'NOT BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue13() {
        static::getValidAdapter()->assembleConditionValue(false, 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue14() {
        static::getValidAdapter()->assembleConditionValue(true, 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue15() {
        static::getValidAdapter()->assembleConditionValue([], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue16() {
        static::getValidAdapter()->assembleConditionValue([true], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]
     */
    public function testInvalidAssembleConditionValue17() {
        static::getValidAdapter()->assembleConditionValue([1, 2, 3], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean
     */
    public function testInvalidAssembleConditionValue18() {
        static::getValidAdapter()->assembleConditionValue([true, false], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean
     */
    public function testInvalidAssembleConditionValue19() {
        static::getValidAdapter()->assembleConditionValue([null, 1], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean
     */
    public function testInvalidAssembleConditionValue110() {
        static::getValidAdapter()->assembleConditionValue([1, null], 'BETWEEN');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Empty array is not allowed as condition value
     */
    public function testInvalidAssembleConditionValue21() {
        static::getValidAdapter()->assembleConditionValue([], '=');
    }

    public function testAssembleConditionValue() {
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
            '(' . $adapter->quoteValue(11) . ',' . $adapter->quoteValue(12) . ',' . $adapter->quoteValue(DbExpr::create('13')) . ')',
            $adapter->assembleConditionValue([11, 12, DbExpr::create('13')], '=')
        );
        static::assertEquals(
            $adapter->quoteValue('string'),
            $adapter->assembleConditionValue('string', '=')
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Condition value with $valueAlreadyQuoted === true must be a string. array received
     */
    public function testInvalidConditionValueWithQuotedFlag() {
        static::getValidAdapter()->assembleConditionValue(['key' => 'value'], '@>', true);
    }

    public function testAssembleConditionValueAdapterSpecific() {
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

    public function testAssembleCondition() {
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

    public function testAssembleConditionAdapterSpecific() {
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
            "jsonb_exists({$column}, " . $adapter->assembleConditionValue('test', '?') . ')',
            $adapter->assembleCondition($column, '?', 'test')
        );
        static::assertEquals(
            "jsonb_exists({$column}, " . $adapter->assembleConditionValue('test', '?', true) . ')',
            $adapter->assembleCondition($column, '?', 'test', true)
        );
        static::assertEquals(
            "jsonb_exists_any({$column}, array[" . $adapter->quoteValue('test') . '])',
            $adapter->assembleCondition($column, '?|', 'test')
        );
        static::assertEquals(
            "jsonb_exists_any({$column}, array[test])",
            $adapter->assembleCondition($column, '?|', 'test', true)
        );
        static::assertEquals(
            "jsonb_exists_any({$column}, array[" . $adapter->quoteValue('test1') . ', ' . $adapter->quoteValue('test2') . '])',
            $adapter->assembleCondition($column, '?|', ['test1', 'test2'])
        );
        static::assertEquals(
            "jsonb_exists_any({$column}, array[test1, test2])",
            $adapter->assembleCondition($column, '?|', ['test1', 'test2'], true)
        );
        static::assertEquals(
            "jsonb_exists_all({$column}, array[" . $adapter->quoteValue('test') . '])',
            $adapter->assembleCondition($column, '?&', 'test')
        );
        static::assertEquals(
            "jsonb_exists_all({$column}, array[test])",
            $adapter->assembleCondition($column, '?&', 'test', true)
        );
        static::assertEquals(
            "jsonb_exists_all({$column}, array[" . $adapter->quoteValue('test1') . ', ' . $adapter->quoteValue('test2') . '])',
            $adapter->assembleCondition($column, '?&', ['test1', 'test2'])
        );
        static::assertEquals(
            "jsonb_exists_all({$column}, array[test1, test2])",
            $adapter->assembleCondition($column, '?&', ['test1', 'test2'], true)
        );
    }

    /**
     * @covers Postgres::extractLimitAndPrecisionForColumnDescription()
     * @covers Postgres::cleanDefaultValueForColumnDescription()
     * @covers DbAdapter::describeTable()
     * @covers Postgres::describeTable()
     */
    public function testDescribeTable() {
        // Postgres::extractLimitAndPrecisionForColumnDescription()
        static::assertEquals(
            [null, null],
            $this->invokePrivateAdapterMethod('extractLimitAndPrecisionForColumnDescription', 'integer')
        );
        static::assertEquals(
            [200, null],
            $this->invokePrivateAdapterMethod('extractLimitAndPrecisionForColumnDescription', 'character varying(200)')
        );
        static::assertEquals(
            [8, 2],
            $this->invokePrivateAdapterMethod('extractLimitAndPrecisionForColumnDescription', 'numeric(8,2)')
        );
        // Postgres::cleanDefaultValueForColumnDescription()
        static::assertEquals(
            '',
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "''::character varying")
        );
        static::assertEquals(
            ' ',
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "' '::text")
        );
        static::assertEquals(
            'a',
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'a'::bpchar")
        );
        static::assertEquals(
            '{}',
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'{}'::jsonb")
        );
        static::assertEquals(
            null,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', '')
        );
        static::assertEquals(
            null,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', null)
        );
        static::assertEquals(
            null,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', 'NULL::character varying')
        );
        static::assertEquals(
            true,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', 'true')
        );
        static::assertEquals(
            false,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', 'false')
        );
        static::assertEquals(
            11,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', '11')
        );
        static::assertEquals(
            11.1,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', '11.1')
        );
        static::assertEquals(
            11.1,
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'11.1'")
        );
        static::assertEquals(
            DbExpr::create("'somecode'::text + NOW()::text + INTERVAL '1 day'::text"),
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'somecode'::text + NOW()::text + INTERVAL '1 day'::text")
        );
        static::assertEquals(
            "'",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "''''::text")
        );
        static::assertEquals(
            "test'quote",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'test''quote'::text")
        );
        static::assertEquals(
            "test'",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'test'''::text")
        );
        static::assertEquals(
            "'quote",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'''quote'::text")
        );
        static::assertEquals(
            "'quote'",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'''quote'''::text")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'''quote''test '' asd'''::text")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', "'''quote''test '' asd'''")
        );
        static::assertEquals(
            DbExpr::create('NOW()'),
            $this->invokePrivateAdapterMethod('cleanDefaultValueForColumnDescription', 'NOW()')
        );
        // Postgres::describeTable()
        $adapter = static::getValidAdapter();
        $description = $adapter->describeTable('settings');
        static::assertInstanceOf(\PeskyORM\Core\TableDescription::class, $adapter->describeTable('admins'));
        static::assertEquals('settings', $description->getName());
        static::assertEquals('public', $description->getDbSchema());
        static::assertCount(3, $description->getColumns());

        $idCol = $description->getColumn('id');
        static::assertEquals('id', $idCol->getName());
        static::assertEquals('int4', $idCol->getDbType());
        static::assertEquals(\PeskyORM\ORM\Column::TYPE_INT, $idCol->getOrmType());
        static::assertEquals(DbExpr::create('nextval(\'settings_id_seq\'::regclass)'), $idCol->getDefault());
        static::assertEquals(null, $idCol->getNumberPrecision());
        static::assertEquals(null, $idCol->getLimit());
        static::assertTrue($idCol->isPrimaryKey());
        static::assertFalse($idCol->isUnique());
        static::assertFalse($idCol->isForeignKey());
        static::assertFalse($idCol->isNullable());

        $keyCol = $description->getColumn('key');
        static::assertEquals('key', $keyCol->getName());
        static::assertEquals('varchar', $keyCol->getDbType());
        static::assertEquals(\PeskyORM\ORM\Column::TYPE_STRING, $keyCol->getOrmType());
        static::assertEquals(null, $keyCol->getDefault());
        static::assertEquals(null, $keyCol->getNumberPrecision());
        static::assertEquals(100, $keyCol->getLimit());
        static::assertFalse($keyCol->isPrimaryKey());
        static::assertTrue($keyCol->isUnique());
        static::assertFalse($keyCol->isForeignKey());
        static::assertFalse($keyCol->isNullable());

        $valueCol = $description->getColumn('value');
        static::assertEquals('value', $valueCol->getName());
        static::assertEquals('json', $valueCol->getDbType());
        static::assertEquals(\PeskyORM\ORM\Column::TYPE_JSON, $valueCol->getOrmType());
        static::assertEquals('{}', $valueCol->getDefault());
        static::assertEquals(null, $valueCol->getNumberPrecision());
        static::assertEquals(null, $valueCol->getLimit());
        static::assertFalse($valueCol->isPrimaryKey());
        static::assertFalse($valueCol->isUnique());
        static::assertFalse($valueCol->isForeignKey());
        static::assertFalse($valueCol->isNullable());

        $description = $adapter->describeTable('admins');
        static::assertEquals('admins', $description->getName());
        static::assertEquals('public', $description->getDbSchema());
        static::assertCount(16, $description->getColumns());
        static::assertTrue($description->getColumn('login')->isUnique());
        static::assertTrue($description->getColumn('email')->isUnique());
        static::assertTrue($description->getColumn('parent_id')->isForeignKey());
        static::assertTrue($description->getColumn('remember_token')->isNullable());
    }

}