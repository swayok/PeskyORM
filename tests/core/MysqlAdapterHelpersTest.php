<?php

require_once __DIR__ . '/PostgresAdapterHelpersTest.php';

class MysqlAdapterHelpersTest extends PostgresAdapterHelpersTest {

    static protected function getValidAdapter() {
        return \PeskyORMTest\TestingApp::getMysqlConnection();
    }

    public function testConvertConditionOperatorForStringComparison() {
        $adapter = $this->getValidAdapter();

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

    public function testQuoteJsonSelectorValue() {
        static::assertEquals(
            "'$.key'",
            $this->invokePrivateAdapterMethod('quoteJsonSelectorValue', 'key')
        );
        static::assertEquals(
            "'$.\\\"key\\\"'",
            $this->invokePrivateAdapterMethod('quoteJsonSelectorValue', '"key"')
        );
        static::assertEquals(
            "'$[0]'",
            $this->invokePrivateAdapterMethod('quoteJsonSelectorValue', '[0]')
        );
    }

    public function testQuoteJsonSelectorExpression() {
        static::assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(`table`.`col_name`->\'$.key1\'->>\'$.key 2\', \'$.key 3\'), \'$.key 4\'))',
            $this->invokePrivateAdapterMethod('quoteJsonSelectorExpression', [
                'table.col_name', '->', 'key1', '->>', '"key 2"', '#>', '`key 3`', '#>>', "'key 4'"
            ])
        );
        static::assertEquals(
            '`table`.`col_name`->\'$[2]\'',
            $this->invokePrivateAdapterMethod('quoteJsonSelectorExpression', [
                'table.col_name', '->', '2'
            ])
        );
    }

    public function testAssembleConditionValueAdapterSpecific() {
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

    public function testAssembleConditionAdapterSpecific() {
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

    public function testDescribeTable() {
        // todo: added tests for describe table (MySQL)
    }


}