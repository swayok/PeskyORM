<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
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