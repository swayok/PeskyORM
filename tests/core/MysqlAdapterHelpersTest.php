<?php

require_once __DIR__ . '/PostgresAdapterHelpersTest.php';

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;

class MysqlAdapterHelpersTest extends PostgresAdapterHelpersTest {

    /** @var MysqlConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = MysqlConfig::fromArray($data['pgsql']);
    }

    static public function tearDownAfterClass() {
        static::$dbConnectionConfig = null;
    }

    static protected function getValidAdapter() {
        return new Mysql(static::$dbConnectionConfig);
    }

    public function testConvertConditionOperatorForStringComparison() {
        $adapter = $this->getValidAdapter();

        $operator = $adapter->convertConditionOperator('SIMILAR TO', 'qweq');
        $this->assertEquals('LIKE', $operator);
        $operator = $adapter->convertConditionOperator('NOT SIMILAR TO', 'qwe');
        $this->assertEquals('NOT LIKE', $operator);

        $operator = $adapter->convertConditionOperator('REGEXP', 'qwe');
        $this->assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEXP', 'qwe');
        $this->assertEquals('NOT REGEXP', $operator);

        $operator = $adapter->convertConditionOperator('REGEX', 'eqe');
        $this->assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEX', 'qwe');
        $this->assertEquals('NOT REGEXP', $operator);

        $operator = $adapter->convertConditionOperator('~', 'qwe');
        $this->assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('!~', 'qew');
        $this->assertEquals('NOT REGEXP', $operator);

        $operator = $adapter->convertConditionOperator('~*', 'ewqe');
        $this->assertEquals('REGEXP', $operator);
        $operator = $adapter->convertConditionOperator('!~*', 'qwe');
        $this->assertEquals('NOT REGEXP', $operator);
    }
}