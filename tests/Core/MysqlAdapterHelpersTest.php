<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\TableDescription;
use PeskyORM\ORM\Column;
use PeskyORM\Tests\PeskyORMTest\Adapter\MysqlTesting;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterHelpersTest.php';

class MysqlAdapterHelpersTest extends PostgresAdapterHelpersTest
{
    
    /**
     * @return MysqlTesting
     */
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testConvertConditionOperatorForStringComparison(): void
    {
        $adapter = self::getValidAdapter();
        
        $operator = $adapter->convertConditionOperator('SIMILAR TO', 'qweq');
        static::assertEquals('LIKE', $operator);
        $operator = $adapter->convertConditionOperator('NOT SIMILAR TO', 'qwe');
        static::assertEquals('NOT LIKE', $operator);
        
        $operator = $adapter->convertConditionOperator('REGEXP', 'qwe');
        static::assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEXP', 'qwe');
        static::assertEquals('NOT REGEXP', $operator);
        
        $operator = $adapter->convertConditionOperator('REGEX', 'eqe');
        static::assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEX', 'qwe');
        static::assertEquals('NOT REGEXP', $operator);
        
        $operator = $adapter->convertConditionOperator('~', 'qwe');
        static::assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('!~', 'qew');
        static::assertEquals('NOT REGEXP', $operator);
        
        $operator = $adapter->convertConditionOperator('~*', 'ewqe');
        static::assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('!~*', 'qwe');
        static::assertEquals('NOT REGEXP', $operator);
    }
    
    public function testQuoteJsonSelectorValue(): void
    {
        static::assertEquals(
            "'$.key'",
            static::getValidAdapter()->quoteJsonSelectorValue('key')
        );
        static::assertEquals(
            "'$.\\\"key\\\"'",
            static::getValidAdapter()->quoteJsonSelectorValue('"key"')
        );
        static::assertEquals(
            "'$[0]'",
            static::getValidAdapter()->quoteJsonSelectorValue('[0]')
        );
    }
    
    public function testQuoteJsonSelectorExpression(): void
    {
        static::assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(`table`.`col_name`->\'$.key1\'->>\'$.key 2\', \'$.key 3\'), \'$.key 4\'))',
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
            '`table`.`col_name`->\'$[2]\'',
            static::getValidAdapter()->quoteJsonSelectorExpression([
                'table.col_name',
                '->',
                '2',
            ])
        );
    }
    
    public function testAssembleConditionValueAdapterSpecific(): void
    {
        $adapter = static::getValidAdapter();
        static::assertEquals(
            $adapter->quoteValue('string'),
            $adapter->assembleConditionValue('string', '@>')
        );
        static::assertEquals(
            'string',
            $adapter->assembleConditionValue('string', '@>', true)
        );
        static::assertEquals(
            $adapter->quoteValue(json_encode(['key' => 'value'])),
            $adapter->assembleConditionValue(['key' => 'value'], '@>')
        );
        static::assertEquals(
            json_encode(['key' => 'value']),
            $adapter->assembleConditionValue(json_encode(['key' => 'value']), '@>', true)
        );
        static::assertEquals(
            $adapter->quoteValue(json_encode([1, 2, 3])),
            $adapter->assembleConditionValue([1, 2, 3], '<@')
        );
        static::assertEquals(
            json_encode([1, 2, 3]),
            $adapter->assembleConditionValue(json_encode([1, 2, 3]), '<@', true)
        );
    }
    
    public function testAssembleConditionAdapterSpecific(): void
    {
        $adapter = static::getValidAdapter();
        $column = $adapter->quoteDbEntityName('colname');
        $one = $adapter->quoteValue('one');
        $many = $adapter->quoteValue('many');
        static::assertEquals(
            "JSON_CONTAINS({$column}, " . $adapter->assembleConditionValue(['test' => '1'], '@>') . ')',
            $adapter->assembleCondition($column, '@>', ['test' => '1'])
        );
        static::assertEquals(
            "JSON_CONTAINS({$column}, " . $adapter->assembleConditionValue(json_encode(['test' => '1']), '@>', true) . ')',
            $adapter->assembleCondition($column, '@>', json_encode(['test' => '1']), true)
        );
        static::assertEquals(
            "JSON_CONTAINS({$column}, " . $adapter->assembleConditionValue(['test' => '2'], '<@') . ')',
            $adapter->assembleCondition($column, '<@', ['test' => '2'])
        );
        static::assertEquals(
            "JSON_CONTAINS({$column}, " . $adapter->assembleConditionValue(json_encode(['test' => '2']), '<@', true) . ')',
            $adapter->assembleCondition($column, '<@', json_encode(['test' => '2']), true)
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, " . $adapter->quoteValue('$.test') . ')',
            $adapter->assembleCondition($column, '?', 'test')
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, test)",
            $adapter->assembleCondition($column, '?', 'test', true)
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $one, " . $adapter->quoteValue('$.test') . ')',
            $adapter->assembleCondition($column, '?|', 'test')
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $one, test)",
            $adapter->assembleCondition($column, '?|', 'test', true)
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $one, " . $adapter->quoteValue('$.test1') . ', ' . $adapter->quoteValue('$[0][1]') . ')',
            $adapter->assembleCondition($column, '?|', ['test1', '[0][1]'])
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $one, test1, [0][1])",
            $adapter->assembleCondition($column, '?|', ['test1', '[0][1]'], true)
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, " . $adapter->quoteValue('$.test') . ')',
            $adapter->assembleCondition($column, '?&', 'test')
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, test)",
            $adapter->assembleCondition($column, '?&', 'test', true)
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, " . $adapter->quoteValue('$.test1') . ', ' . $adapter->quoteValue('$[0][1]') . ')',
            $adapter->assembleCondition($column, '?&', ['test1', '[0][1]'])
        );
        static::assertEquals(
            "JSON_CONTAINS_PATH({$column}, $many, test1, [0][1])",
            $adapter->assembleCondition($column, '?&', ['test1', '[0][1]'], true)
        );
    }
    
    /**
     * @covers Postgres::extractLimitAndPrecisionForColumnDescription()
     * @covers Postgres::cleanDefaultValueForColumnDescription()
     * @covers DbAdapter::describeTable()
     * @covers Postgres::describeTable()
     */
    public function testDescribeTable(): void
    {
        // Mysql::extractLimitAndPrecisionForColumnDescription()
        static::assertEquals(
            [null, null],
            static::getValidAdapter()->extractLimitAndPrecisionForColumnDescription('timestamp')
        );
        static::assertEquals(
            [11, null],
            static::getValidAdapter()->extractLimitAndPrecisionForColumnDescription('int(11)')
        );
        static::assertEquals(
            [200, null],
            static::getValidAdapter()->extractLimitAndPrecisionForColumnDescription('varchar(200)')
        );
        static::assertEquals(
            [8, 2],
            static::getValidAdapter()->extractLimitAndPrecisionForColumnDescription('float(8,2)')
        );
        // Mysql::cleanDefaultValueForColumnDescription()
        static::assertEquals(
            '',
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('')
        );
        static::assertEquals(
            ' ',
            static::getValidAdapter()->cleanDefaultValueForColumnDescription(' ')
        );
        static::assertEquals(
            'a',
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('a')
        );
        static::assertEquals(
            null,
            static::getValidAdapter()->cleanDefaultValueForColumnDescription(null)
        );
        static::assertEquals(
            true,
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('true')
        );
        static::assertEquals(
            false,
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('false')
        );
        static::assertEquals(
            11,
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('11')
        );
        static::assertEquals(
            11.1,
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('11.1')
        );
        static::assertEquals(
            "'11.1'",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("'11.1'")
        );
        static::assertEquals(
            DbExpr::create('NOW()'),
            static::getValidAdapter()->cleanDefaultValueForColumnDescription('CURRENT_TIMESTAMP')
        );
        static::assertEquals(
            "'",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("'")
        );
        static::assertEquals(
            "test'quote",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("test'quote")
        );
        static::assertEquals(
            "test'",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("test'")
        );
        static::assertEquals(
            "'quote",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("'quote")
        );
        static::assertEquals(
            "'quote'",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("'quote'")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            static::getValidAdapter()->cleanDefaultValueForColumnDescription("'quote'test ' asd'")
        );
        // Mysql::describeTable()
        $adapter = static::getValidAdapter();
        $description = $adapter->describeTable('settings');
        static::assertInstanceOf(TableDescription::class, $adapter->describeTable('admins'));
        static::assertEquals('settings', $description->getName());
        static::assertEquals(null, $description->getDbSchema());
        static::assertCount(3, $description->getColumns());
        
        $idCol = $description->getColumn('id');
        static::assertEquals('id', $idCol->getName());
        static::assertEquals('int(11)', $idCol->getDbType());
        static::assertEquals(Column::TYPE_INT, $idCol->getOrmType());
        static::assertEquals(null, $idCol->getDefault());
        static::assertEquals(null, $idCol->getNumberPrecision());
        static::assertEquals(11, $idCol->getLimit());
        static::assertTrue($idCol->isPrimaryKey());
        static::assertFalse($idCol->isUnique());
        static::assertFalse($idCol->isForeignKey());
        static::assertFalse($idCol->isNullable());
        
        $keyCol = $description->getColumn('key');
        static::assertEquals('key', $keyCol->getName());
        static::assertEquals('varchar(100)', $keyCol->getDbType());
        static::assertEquals(Column::TYPE_STRING, $keyCol->getOrmType());
        static::assertEquals(null, $keyCol->getDefault());
        static::assertEquals(null, $keyCol->getNumberPrecision());
        static::assertEquals(100, $keyCol->getLimit());
        static::assertFalse($keyCol->isPrimaryKey());
        static::assertTrue($keyCol->isUnique());
        static::assertFalse($keyCol->isForeignKey());
        static::assertFalse($keyCol->isNullable());
        
        $valueCol = $description->getColumn('value');
        static::assertEquals('value', $valueCol->getName());
        static::assertEquals('text', $valueCol->getDbType());
        static::assertEquals(Column::TYPE_TEXT, $valueCol->getOrmType());
        static::assertEquals(null, $valueCol->getDefault());
        static::assertEquals(null, $valueCol->getNumberPrecision());
        static::assertEquals(null, $valueCol->getLimit());
        static::assertFalse($valueCol->isPrimaryKey());
        static::assertFalse($valueCol->isUnique());
        static::assertFalse($valueCol->isForeignKey());
        static::assertFalse($valueCol->isNullable());
        
        $description = $adapter->describeTable('admins');
        static::assertEquals('admins', $description->getName());
        static::assertEquals(null, $description->getDbSchema());
        static::assertCount(17, $description->getColumns());
        static::assertTrue(
            $description->getColumn('login')
                ->isUnique()
        );
        static::assertTrue(
            $description->getColumn('email')
                ->isUnique()
        );
        static::assertFalse(
            $description->getColumn('parent_id')
                ->isForeignKey()
        ); //< description does not show this
        static::assertTrue(
            $description->getColumn('remember_token')
                ->isNullable()
        );
        static::assertEquals(
            DbExpr::create('NOW()'),
            $description->getColumn('created_at')
                ->getDefault()
        );
    }
    
    public function testInvalidPkName4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$pkName must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)");
        static::getValidAdapter()->guardPkNameArg('teasd as das d 90as9()');
    }
    
    public function testIsValidDbEntityNameAndJsonSelector2(): void
    {
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test test->test'));
        static::assertFalse(static::getValidAdapter()->isValidJsonSelector('test#test->test'));
        
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName('test test'));
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName('test$test'));
        static::assertFalse(static::getValidAdapter()->isValidDbEntityName('test->test', false));
    }
}