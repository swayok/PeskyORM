<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsManager;
use PeskyORM\DbExpr;
use PeskyORM\Join\JoinConfig;
use PeskyORM\Profiling\PdoProfilingHelper;
use PeskyORM\Profiling\TraceablePDO;
use PeskyORM\Select\Select;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Utils\DbAdapterMethodArgumentUtils;
use PeskyORM\Utils\QueryBuilderUtils;

class PostgresAdapterHelpersTest extends BaseTestCase
{
    
    /**
     * @return PostgresTesting
     */
    protected static function getValidAdapter(bool $reuseExisting = true): DbAdapterInterface
    {
        return TestingApp::getPgsqlConnection($reuseExisting);
    }
    
    public function testInvalidTable(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), null);
    }
    
    public function testInvalidTable2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$table argument value must be a not-empty string.');
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), '');
    }
    
    public function testInvalidTable3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), false);
    }
    
    public function testInvalidTable4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), true);
    }
    
    public function testInvalidTable5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), $this);
    }
    
    public function testInvalidTable6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($table) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), 123);
    }
    
    public function testInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$data argument value cannot be empty');
        DbAdapterMethodArgumentUtils::guardDataArg([]);
    }
    
    public function testInvalidData2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($data) must be of type array, string given');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardDataArg('test');
    }
    
    public function testInvalidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns argument value cannot be empty');
        DbAdapterMethodArgumentUtils::guardColumnsListArg([]);
    }
    
    public function testInvalidColumns2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columns) must be of type array, string given');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardColumnsListArg('test');
    }
    
    public function testInvalidColumns3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of');
        DbAdapterMethodArgumentUtils::guardColumnsListArg([$this]);
    }
    
    public function testInvalidColumns4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string.');
        DbAdapterMethodArgumentUtils::guardColumnsListArg([DbExpr::create('test')], false);
    }
    
    public function testInvalidConditions1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg('');
    }
    
    public function testInvalidConditions2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditions argument value cannot be empty');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg([]);
    }
    
    public function testInvalidConditions3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg(new \Exception('test'));
    }
    
    public function testInvalidConditions4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg(false);
    }
    
    public function testInvalidConditions5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg(true);
    }
    
    public function testInvalidConditions6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($conditions) must be of type');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardConditionsArg(123);
    }
    
    public function testInvalidReturning(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg('*');
    }
    
    public function testInvalidReturning2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg(new \Exception('test'));
    }
    
    public function testInvalidReturning3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($returning) must be of type array|bool');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg(123);
    }

    public function testInvalidReturning4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$returning[0]: value must be a not-empty string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg([123]);
    }

    public function testInvalidReturning5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$returning[0]: value must be a not-empty string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg([[]]);
    }

    public function testInvalidReturning6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$returning[0]: value must be a not-empty string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardReturningArg([true]);
    }
    
    public function testInvalidPkName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$pkName argument value must be a not-empty string.');
        DbAdapterMethodArgumentUtils::guardPkNameArg(static::getValidAdapter(), '');
    }
    
    public function testInvalidPkName2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($pkName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        DbAdapterMethodArgumentUtils::guardPkNameArg(static::getValidAdapter(), true);
    }
    
    public function testInvalidPkName3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($pkName) must be of type string, array given');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardPkNameArg(static::getValidAdapter(), []);
    }
    
    public function testInvalidPkName4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$pkName argument value (teasd as das d 90as9()) must be a string that matches DB entity naming rules');
        DbAdapterMethodArgumentUtils::guardPkNameArg(static::getValidAdapter(), 'teasd as das d 90as9()');
        static::assertTrue(true);
    }
    
    public function testInvalidPkName5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($pkName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        DbAdapterMethodArgumentUtils::guardPkNameArg(static::getValidAdapter(), DbExpr::create('test'));
    }
    
    public function testValidArgs(): void
    {
        DbAdapterMethodArgumentUtils::guardTableNameArg(static::getValidAdapter(), 'table');
        DbAdapterMethodArgumentUtils::guardDataArg(['key' => 'value']);
        DbAdapterMethodArgumentUtils::guardColumnsListArg(['key1', DbExpr::create('key2')]);
        DbAdapterMethodArgumentUtils::guardConditionsArg(['column !=' => 'value']);
        DbAdapterMethodArgumentUtils::guardConditionsArg(DbExpr::create('test'));
        DbAdapterMethodArgumentUtils::guardReturningArg(true);
        DbAdapterMethodArgumentUtils::guardReturningArg(false);
        DbAdapterMethodArgumentUtils::guardReturningArg(['key1', 'key2']);
        static::assertTrue(true);
    }
    
    public function testInvalidNormalizeConditionOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition operator [>=] does not support list of values");
        $adapter = static::getValidAdapter();
        $adapter->normalizeConditionOperator('>=', [1, 2, 3]);
    }
    
    public function testNormalizeConditionOperatorForNullValue(): void
    {
        $adapter = static::getValidAdapter();
        
        $operator = $adapter->normalizeConditionOperator('=', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->normalizeConditionOperator('IS', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->normalizeConditionOperator('>=', null);
        static::assertEquals('IS', $operator);
        $operator = $adapter->normalizeConditionOperator('OPER', null);
        static::assertEquals('IS', $operator);
        
        $operator = $adapter->normalizeConditionOperator('IS', '1');
        static::assertEquals('=', $operator);
        
        $operator = $adapter->normalizeConditionOperator('!=', null);
        static::assertEquals('IS NOT', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT', null);
        static::assertEquals('IS NOT', $operator);
        $operator = $adapter->normalizeConditionOperator('IS NOT', null);
        static::assertEquals('IS NOT', $operator);
        
        $operator = $adapter->normalizeConditionOperator('NOT', '1');
        static::assertEquals('!=', $operator);
        $operator = $adapter->normalizeConditionOperator('IS NOT', '1');
        static::assertEquals('!=', $operator);
    }
    
    public function testNormalizeConditionOperatorForArrayValue(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->normalizeConditionOperator('=', [1, 2, 3]);
        static::assertEquals('IN', $operator);
        $operator = $adapter->normalizeConditionOperator('!=', [1, 2, 3]);
        static::assertEquals('NOT IN', $operator);
        
        $operator = $adapter->normalizeConditionOperator('IN', 1);
        static::assertEquals('=', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT IN', 1);
        static::assertEquals('!=', $operator);
        
        $operator = $adapter->normalizeConditionOperator('IN', [1, 2, 3]);
        static::assertEquals('IN', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT IN', [1, 2, 3]);
        static::assertEquals('NOT IN', $operator);
        
        $operator = $adapter->normalizeConditionOperator('IN', DbExpr::create('SELECT'));
        static::assertEquals('IN', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT IN', DbExpr::create('SELECT'));
        static::assertEquals('NOT IN', $operator);
    }
    
    public function testNormalizeConditionOperator(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->normalizeConditionOperator('BETWEEN', [1, 2, 3]);
        static::assertEquals('BETWEEN', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT BETWEEN', 'oo');
        static::assertEquals('NOT BETWEEN', $operator);
        
        $operator = $adapter->normalizeConditionOperator('>', 1);
        static::assertEquals('>', $operator);
        $operator = $adapter->normalizeConditionOperator('<', 'a');
        static::assertEquals('<', $operator);
        $operator = $adapter->normalizeConditionOperator('CUSTOM', 'qwe');
        static::assertEquals('CUSTOM', $operator);
        $operator = $adapter->normalizeConditionOperator('LIKE', 'weqwe');
        static::assertEquals('LIKE', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT LIKE', 'ewqewqe');
        static::assertEquals('NOT LIKE', $operator);
    }
    
    public function testNormalizeConditionOperatorForStringComparison(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->normalizeConditionOperator('SIMILAR TO', 'qwe');
        static::assertEquals('SIMILAR TO', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT SIMILAR TO', 'qwe');
        static::assertEquals('NOT SIMILAR TO', $operator);
        
        $operator = $adapter->normalizeConditionOperator('REGEXP', 'wqe');
        static::assertEquals('~*', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT REGEXP', 'ewqe');
        static::assertEquals('!~*', $operator);
        
        $operator = $adapter->normalizeConditionOperator('REGEX', 'weq');
        static::assertEquals('~*', $operator);
        $operator = $adapter->normalizeConditionOperator('NOT REGEX', 'qwe');
        static::assertEquals('!~*', $operator);
        
        $operator = $adapter->normalizeConditionOperator('~', 'ewq');
        static::assertEquals('~', $operator);
        $operator = $adapter->normalizeConditionOperator('!~', 'ewqe');
        static::assertEquals('!~', $operator);
        
        $operator = $adapter->normalizeConditionOperator('~*', 'ewqe');
        static::assertEquals('~*', $operator);
        $operator = $adapter->normalizeConditionOperator('!~*', 'ewqe');
        static::assertEquals('!~*', $operator);
    }

    public function testConvertConditionOperator(): void
    {
        $adapter = self::getValidAdapter();
        // really normalized operators
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('SIMILAR TO');
        static::assertEquals('SIMILAR TO', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('NOT SIMILAR TO');
        static::assertEquals('NOT SIMILAR TO', $operator);

        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('~');
        static::assertEquals('~', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('!~');
        static::assertEquals('!~', $operator);

        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('?');
        static::assertEquals('??', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('?|');
        static::assertEquals('??|', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('?&');
        static::assertEquals('??&', $operator);

        // not normalized operators - returns anything passed inside
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('not an operator');
        static::assertEquals('not an operator', $operator);

        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('REGEXP');
        static::assertEquals('REGEXP', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('NOT REGEXP');
        static::assertEquals('NOT REGEXP', $operator);

        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('REGEX');
        static::assertEquals('REGEX', $operator);
        $operator = $adapter->convertNormalizedConditionOperatorForDbQuery('NOT REGEX');
        static::assertEquals('NOT REGEX', $operator);
    }
    
    public function testInvalidArgsInMakeSelectQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$table argument value must be a not-empty string.');
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('');
    }
    
    public function testInvalidArgsInMakeSelectQuery2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditions argument may contain only objects of class');
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [$this])->fetchOne();
    }
    
    public function testInvalidArgsInMakeSelectQuery3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($conditionsAndOptions) must be of type');
        $adapter = self::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->makeSelectQuery('table', 'string');
    }

    public function testInvalidArgsInMakeSelectQuery4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'GROUP\']: value must be an array');
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [QueryBuilderUtils::QUERY_PART_GROUP => ' '])->fetchOne();
    }

    public function testInvalidArgsInMakeSelectQuery5(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('$conditionsAndOptions array cannot contain options: ' . QueryBuilderUtils::QUERY_PART_CONTAINS);
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [QueryBuilderUtils::QUERY_PART_CONTAINS => ' ']);
    }

    public function testInvalidArgsInMakeSelectQuery6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$distinctColumns[0] argument value must be a not-empty string');
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [QueryBuilderUtils::QUERY_PART_DISTINCT => ' '])->fetchOne();
    }

    public function testInvalidArgsInMakeSelectQuery7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$distinctColumns[0] argument value must be a not-empty string');
        $adapter = self::getValidAdapter();
        $adapter->makeSelectQuery('table', [QueryBuilderUtils::QUERY_PART_DISTINCT => [' ']])->fetchOne();
    }

    public function testMakeSelectQuery(): void
    {
        $adapter = self::getValidAdapter();
        
        $select = $adapter->makeSelectQuery('test_table')
            ->columns(['col1', DbExpr::create('`col2` as `col22`')]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `tbl_TestTable_0`.`col1` AS `col_TestTable__col1_0`,'
                    . ' (`col2` as `col22`)'
                    . ' FROM `test_table` AS `tbl_TestTable_0`',
                    false
                )
            ),
            $select->getQuery()
        );
        
        $select = $adapter->makeSelectQuery('test_table', DbExpr::create('WHERE `col1` > ``0``', false));
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `tbl_TestTable_0`.* FROM `test_table` AS `tbl_TestTable_0` WHERE `col1` > ``0``',
                    false
                )
            ),
            $select->columns('*')->getQuery()
        );

        $select = $adapter->makeSelectQuery('test_table', ['col1 >' => 0]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `tbl_TestTable_0`.* FROM `test_table` AS `tbl_TestTable_0`'
                    . ' WHERE `tbl_TestTable_0`.`col1` > ``0``',
                    false
                )
            ),
            $select->columns('*')->getQuery()
        );

        $select = $adapter->makeSelectQuery('test_table', [
            'col1 >' => 0,
            'col2 <' => 1,
            'OR' => ['col3' => 3, 'col4' => 4]
        ]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `tbl_TestTable_0`.* FROM `test_table` AS `tbl_TestTable_0`'
                    . ' WHERE `tbl_TestTable_0`.`col1` > ``0``'
                    . ' AND `tbl_TestTable_0`.`col2` < ``1``'
                    . ' AND (`tbl_TestTable_0`.`col3` = ``3`` OR `tbl_TestTable_0`.`col4` = ``4``)',
                    false
                )
            ),
            $select->columns('*')->getQuery()
        );

        $options = [
            QueryBuilderUtils::QUERY_PART_WITH => ['test' => Select::from('some_table', $adapter)],
            QueryBuilderUtils::QUERY_PART_JOINS => [new JoinConfig('Test', JoinConfig::JOIN_INNER, 'test', 'id', 'other', 'id')],
            QueryBuilderUtils::QUERY_PART_GROUP => [DbExpr::create('[grouping]')],
            QueryBuilderUtils::QUERY_PART_HAVING => [DbExpr::create('[having filters]')],
            QueryBuilderUtils::QUERY_PART_ORDER => [DbExpr::create('[ordering]')],
            QueryBuilderUtils::QUERY_PART_LIMIT => 1,
            QueryBuilderUtils::QUERY_PART_OFFSET => 2,
        ];
        $select = $adapter->makeSelectQuery('test_table', array_merge(
            ['col1 >' => 0],
            $options
        ));
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'WITH `test` AS (SELECT `tbl_SomeTable_0`.* FROM `some_table` AS `tbl_SomeTable_0`)'
                    . ' SELECT `tbl_TestTable_0`.*, `tbl_Test_1`.* FROM `test_table` AS `tbl_TestTable_0`'
                    . ' INNER JOIN `other` AS `tbl_Test_1`'
                    . ' ON (`tbl_Test_1`.`id` = `test`.`id`)'
                    . ' WHERE `tbl_TestTable_0`.`col1` > ``0``'
                    . ' GROUP BY [grouping] HAVING ([having filters]) ORDER BY [ordering]'
                    . ' LIMIT 1 OFFSET 2',
                    false
                )
            ),
            $select->columns('*')->getQuery()
        );

        $select = $adapter->makeSelectQuery('test_table', $options);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'WITH `test` AS (SELECT `tbl_SomeTable_1`.* FROM `some_table` AS `tbl_SomeTable_1`)'
                    . ' SELECT `tbl_TestTable_0`.*, `tbl_Test_1`.* FROM `test_table` AS `tbl_TestTable_0`'
                    . ' INNER JOIN `other` AS `tbl_Test_1` ON (`tbl_Test_1`.`id` = `test`.`id`)'
                    . ' GROUP BY [grouping] HAVING ([having filters]) ORDER BY [ordering]'
                    . ' LIMIT 1 OFFSET 2',
                    false
                )
            ),
            $select->columns('*')->getQuery()
        );

        // DISTINCT tests
        $select = $adapter->makeSelectQuery('admins', [
            QueryBuilderUtils::QUERY_PART_DISTINCT => true
        ]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT DISTINCT `tbl_Admins_0`.`id` AS "col_Admins__id_0" FROM `admins` AS `tbl_Admins_0`',
                    false
                )
            ),
            $select->columns('id')->getQuery()
        );

        $select = $adapter->makeSelectQuery('admins', [
            QueryBuilderUtils::QUERY_PART_DISTINCT => false
        ]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT `tbl_Admins_0`.`id` AS "col_Admins__id_0" FROM `admins` AS `tbl_Admins_0`',
                    false
                )
            ),
            $select->columns('id')->getQuery()
        );

        $select = $adapter->makeSelectQuery('admins', [
            QueryBuilderUtils::QUERY_PART_DISTINCT => ['id', 'login']
        ]);
        static::assertEquals(
            $adapter->quoteDbExpr(
                DbExpr::create(
                    'SELECT DISTINCT ON ("tbl_Admins_0"."id","tbl_Admins_0"."login")'
                    . ' `tbl_Admins_0`.`id` AS "col_Admins__id_0",'
                    . ' "tbl_Admins_0"."login" AS "col_Admins__login_1"'
                    . ' FROM `admins` AS `tbl_Admins_0`',
                    false
                )
            ),
            $select->columns('id', 'login')->getQuery()
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
        static::assertTrue(static::getValidAdapter()->isValidJsonSelector('test#test->test'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test$test'));
        static::assertTrue(static::getValidAdapter()->isValidDbEntityName('test->test', false));
    }
    
    public function testInvalidAssembleConditionValue11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operators must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(null, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue12(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operators must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(1, 'NOT BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue13(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operators must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(false, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue14(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition value for BETWEEN and NOT BETWEEN operators must be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue(true, 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue15(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue([], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue16(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to be an array with 2 values: [min, max]");
        static::getValidAdapter()
            ->assembleConditionValue([true], 'BETWEEN');
    }
    
    public function testInvalidAssembleConditionValue17(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("BETWEEN and NOT BETWEEN conditions require value to be an array with 2 values: [min, max]");
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
        $this->expectExceptionMessage("Condition value with \$valueAlreadyQuoted === true must be a string but it is array");
        static::getValidAdapter()
            ->assembleCondition('"column"', '@>', ['key' => 'value'], true);
    }
    
    public function testAssembleCondition(): void
    {
        $adapter = static::getValidAdapter();
        $column = $adapter->quoteDbEntityName('colname');
        static::assertEquals(
            $column . ' = ' . $adapter->quoteValue('test'),
            $adapter->assembleCondition($column, '=', 'test')
        );
        static::assertEquals(
            $column . ' = test',
            $adapter->assembleCondition($column, '=', 'test', true)
        );
        static::assertEquals(
            $column . " IN ('1', '2')",
            $adapter->assembleCondition($column, 'IN', [1, 2])
        );
        static::assertEquals(
            $column . ' IN (1, 2)',
            $adapter->assembleCondition($column, 'IN', [1, 2], true)
        );
        static::assertEquals(
            $column . " BETWEEN '1' AND '2'",
            $adapter->assembleCondition($column, 'BETWEEN', [1, 2])
        );
        static::assertEquals(
            $column . ' BETWEEN 1 AND 2',
            $adapter->assembleCondition($column, 'BETWEEN', [1, 2], true)
        );
    }
    
    public function testAssembleConditionAdapterSpecific(): void
    {
        $adapter = static::getValidAdapter();
        $column = $adapter->quoteDbEntityName('colname');
        static::assertEquals(
            $column . ' @> ' . $adapter->quoteValue('test') . '::jsonb',
            $adapter->assembleCondition($column, '@>', 'test')
        );
        static::assertEquals(
            $column . ' @> test::jsonb',
            $adapter->assembleCondition($column, '@>', 'test', true)
        );
        static::assertEquals(
            $column . ' <@ ' . $adapter->quoteValue('test') . '::jsonb',
            $adapter->assembleCondition($column, '<@', 'test')
        );
        static::assertEquals(
            $column . ' <@ test::jsonb',
            $adapter->assembleCondition($column, '<@', 'test', true)
        );
        static::assertEquals(
            "{$column} ?? " . $adapter->quoteValue('test'),
            $adapter->assembleCondition($column, '?', 'test')
        );
        static::assertEquals(
            "{$column} ?? test",
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

    /** @noinspection PhpUnusedParameterInspection */
    public function testCustomOperatorAssembler(): void
    {
        $adapter = static::getValidAdapter();

        // new operator
        $adapter::addConditionAssemblerForOperator(
            '>=<',
            static function (
                string $quotedColumn,
                string $operator,
                mixed $rawValue,
                bool $valueAlreadyQuoted = false
            ) {
                return 'test1';
            }
        );
        static::assertEquals('test1', $adapter->assembleCondition('column', '>=<', 'value'));

        // override existing
        $adapter::addConditionAssemblerForOperator(
            '>=<',
            static function (
                string $quotedColumn,
                string $operator,
                mixed $rawValue,
                bool $valueAlreadyQuoted = false
            ) {
                return 'test2';
            }
        );
        static::assertEquals('test2', $adapter->assembleCondition('column', '>=<', 'value'));
    }

    public function testConnectionWrapping1(): void
    {
        TestingApp::resetConnections();
        $adapter = static::getValidAdapter();
        $adapter->disconnect();
        $adapter->setConnectionWrapper(null);
        // connect and get plain PDO
        $pdo = $adapter->getConnection();
        static::assertEquals(\PDO::class, get_class($pdo)); //< exact match required here
        // wrap after connection established
        DbConnectionsManager::startProfilingForConnection($adapter);
        $wrappedPdo = $adapter->getConnection();
        static::assertInstanceOf(TraceablePDO::class, $wrappedPdo);
        // unwrap
        $adapter->setConnectionWrapper(null);
        $pdo = $adapter->getConnection();
        static::assertEquals(\PDO::class, get_class($pdo));
        TestingApp::resetConnections();
    }

    public function testConnectionWrapping2(): void
    {
        TestingApp::resetConnections();
        $adapter = static::getValidAdapter();
        // wrap before connection established
        DbConnectionsManager::startProfilingForConnection($adapter);
        $wrappedPdo = $adapter->getConnection();
        static::assertInstanceOf(TraceablePDO::class, $wrappedPdo);
        // unwrap
        $adapter->setConnectionWrapper(null);
        $pdo = $adapter->getConnection();
        static::assertEquals(\PDO::class, get_class($pdo)); //< exact match required here
        TestingApp::resetConnections();
    }

    public function testProfiler(): void
    {
        TestingApp::resetConnections();
        PdoProfilingHelper::forgetConnections();
        $adapter = static::getValidAdapter();
        DbConnectionsManager::startProfilingForConnection($adapter);
        $wrappedPdo = $adapter->getConnection();
        static::assertInstanceOf(TraceablePDO::class, $wrappedPdo);
        static::assertCount(1, PdoProfilingHelper::getConnections());
        $adapter->query('SELECT NOW()');
        $profilingInfo = PdoProfilingHelper::collect();
        static::assertNotEmpty($profilingInfo);
        static::assertArrayHasKey('statements_count', $profilingInfo);
        static::assertArrayHasKey('failed_statements_count', $profilingInfo);
        static::assertArrayHasKey('accumulated_duration', $profilingInfo);
        static::assertArrayHasKey('max_memory_usage', $profilingInfo);
        static::assertArrayHasKey('statements', $profilingInfo);
        static::assertEquals(1, $profilingInfo['statements_count']);
        static::assertEquals(0, $profilingInfo['failed_statements_count']);
        static::assertGreaterThan(0.0, $profilingInfo['accumulated_duration']);
        static::assertGreaterThan(0.0, $profilingInfo['max_memory_usage']);
        static::assertIsArray($profilingInfo['statements']);
        static::assertNotEmpty($profilingInfo['statements']);
        foreach ($profilingInfo['statements'] as $statements) {
            static::assertIsArray($statements);
            foreach ($statements as $statementInfo) {
                static::assertArrayHasKey('sql', $statementInfo);
                static::assertArrayHasKey('row_count', $statementInfo);
                static::assertArrayHasKey('prepared_statement_id', $statementInfo);
                static::assertArrayHasKey('params', $statementInfo);
                static::assertArrayHasKey('duration', $statementInfo);
                static::assertArrayHasKey('memory_before', $statementInfo);
                static::assertArrayHasKey('memory_used', $statementInfo);
                static::assertArrayHasKey('memory_after', $statementInfo);
                static::assertArrayHasKey('is_success', $statementInfo);
                static::assertArrayHasKey('error_code', $statementInfo);
                static::assertArrayHasKey('error_message', $statementInfo);
                static::assertArrayHasKey('started_at', $statementInfo);
                static::assertArrayHasKey('ended_at', $statementInfo);
            }
        }
        TestingApp::resetConnections();
        PdoProfilingHelper::forgetConnections();
    }

    public function testTransactionsTracing(): void
    {
        TestingApp::resetConnections();
        PdoProfilingHelper::forgetConnections();
        $adapter = static::getValidAdapter();
        $adapter->setTransactionsTracing(true);
        static::assertTrue($adapter->isTransactionsTracingEnabled());
        static::assertCount(0, $adapter->getTransactionsTraces());
        $adapter->begin();
        static::assertCount(1, $adapter->getTransactionsTraces());
        $adapter->rollBack();
        static::assertCount(2, $adapter->getTransactionsTraces());
        $adapter->begin();
        static::assertCount(3, $adapter->getTransactionsTraces());
        $adapter->commit();
        static::assertCount(4, $adapter->getTransactionsTraces());
        try {
            $adapter->commit();
        } catch (\Throwable) {
        }
        static::assertCount(5, $adapter->getTransactionsTraces());
        static::assertArrayHasKey('5:failed', $adapter->getTransactionsTraces());
        try {
            $adapter->rollBack();
        } catch (\Throwable) {
        }
        static::assertCount(6, $adapter->getTransactionsTraces());
        static::assertArrayHasKey('6:failed', $adapter->getTransactionsTraces());
        TestingApp::resetConnections();
        PdoProfilingHelper::forgetConnections();
    }
}