<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;

class PostgresAdapterHelpersTest extends PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
    }

    static public function tearDownAfterClass() {
        static::$dbConnectionConfig = null;
    }

    static protected function getValidAdapter() {
        $adapter = new Postgres(static::$dbConnectionConfig);
        $adapter->writeTransactionQueriesToLastQuery = false;
        return $adapter;
    }

    /**
     * @param string $methodName
     * @param mixed $arg
     * @return ReflectionMethod
     */
    protected function invokePrivateAdapterMethod($methodName, $arg) {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($adapter, $arg);
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
     * @expectedException PHPUnit_Framework_Error
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
     * @expectedException PHPUnit_Framework_Error
     * @expectedExceptionMessage guardColumnsArg() must be of the type array
     */
    public function testInvalidColumns2() {
        $this->invokePrivateAdapterMethod('guardColumnsArg', 'test');
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
        $this->invokePrivateAdapterMethod('guardPkNameArg', \PeskyORM\Core\DbExpr::create('test'));
    }

    public function testValidArgs() {
        $this->invokePrivateAdapterMethod('guardTableNameArg', 'table');
        $this->invokePrivateAdapterMethod('guardDataArg', ['key' => 'value']);
        $this->invokePrivateAdapterMethod('guardColumnsArg', ['key1', 'key2']);
        $this->invokePrivateAdapterMethod('guardConditionsArg', 'test');
        $this->invokePrivateAdapterMethod('guardConditionsArg', \PeskyORM\Core\DbExpr::create('test'));
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
        $this->assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('IS', null);
        $this->assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('>=', null);
        $this->assertEquals('IS', $operator);
        $operator = $adapter->convertConditionOperator('OPER', null);
        $this->assertEquals('IS', $operator);

        $operator = $adapter->convertConditionOperator('!=', null);
        $this->assertEquals('IS NOT', $operator);
        $operator = $adapter->convertConditionOperator('NOT', null);
        $this->assertEquals('IS NOT', $operator);
        $operator = $adapter->convertConditionOperator('IS NOT', null);
        $this->assertEquals('IS NOT', $operator);
    }

    public function testConvertConditionOperatorForArrayValue() {
        $adapter = $this->getValidAdapter();

        $operator = $adapter->convertConditionOperator('=', [1, 2, 3]);
        $this->assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('!=', [1, 2, 3]);
        $this->assertEquals('NOT IN', $operator);

        $operator = $adapter->convertConditionOperator('IN', 1);
        $this->assertEquals('=', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', 1);
        $this->assertEquals('!=', $operator);

        $operator = $adapter->convertConditionOperator('IN', [1, 2, 3]);
        $this->assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', [1, 2, 3]);
        $this->assertEquals('NOT IN', $operator);

        $operator = $adapter->convertConditionOperator('IN', \PeskyORM\Core\DbExpr::create('SELECT'));
        $this->assertEquals('IN', $operator);
        $operator = $adapter->convertConditionOperator('NOT IN', \PeskyORM\Core\DbExpr::create('SELECT'));
        $this->assertEquals('NOT IN', $operator);
    }

    public function testConvertConditionOperator() {
        $adapter = $this->getValidAdapter();

        $operator = $adapter->convertConditionOperator('BETWEEN', [1, 2, 3]);
        $this->assertEquals('BETWEEN', $operator);
        $operator = $adapter->convertConditionOperator('NOT BETWEEN', 'oo');
        $this->assertEquals('NOT BETWEEN', $operator);

        $operator = $adapter->convertConditionOperator('>', 1);
        $this->assertEquals('>', $operator);
        $operator = $adapter->convertConditionOperator('<', 'a');
        $this->assertEquals('<', $operator);
        $operator = $adapter->convertConditionOperator('CUSTOM', 'qwe');
        $this->assertEquals('CUSTOM', $operator);
        $operator = $adapter->convertConditionOperator('LIKE', 'weqwe');
        $this->assertEquals('LIKE', $operator);
        $operator = $adapter->convertConditionOperator('NOT LIKE', 'ewqewqe');
        $this->assertEquals('NOT LIKE', $operator);
    }

    public function testConvertConditionOperatorForStringComparison() {
        $adapter = $this->getValidAdapter();

        $operator = $adapter->convertConditionOperator('SIMILAR TO', 'qwe');
        $this->assertEquals('SIMILAR TO', $operator);
        $operator = $adapter->convertConditionOperator('NOT SIMILAR TO', 'qwe');
        $this->assertEquals('NOT SIMILAR TO', $operator);

        $operator = $adapter->convertConditionOperator('REGEXP', 'wqe');
        $this->assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEXP', 'ewqe');
        $this->assertEquals('!~*', $operator);

        $operator = $adapter->convertConditionOperator('REGEX', 'weq');
        $this->assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('NOT REGEX', 'qwe');
        $this->assertEquals('!~*', $operator);

        $operator = $adapter->convertConditionOperator('~', 'ewq');
        $this->assertEquals('~', $operator);
        $operator = $adapter->convertConditionOperator('!~', 'ewqe');
        $this->assertEquals('!~', $operator);

        $operator = $adapter->convertConditionOperator('~*', 'ewqe');
        $this->assertEquals('~*', $operator);
        $operator = $adapter->convertConditionOperator('!~*', 'ewqe');
        $this->assertEquals('!~*', $operator);
    }


}