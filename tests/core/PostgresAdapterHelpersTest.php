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
}