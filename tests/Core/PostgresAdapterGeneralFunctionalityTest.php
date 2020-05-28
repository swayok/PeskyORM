<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORMTest\TestingApp;

class PostgresAdapterGeneralFunctionalityTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    public static function tearDownAfterClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static private function getValidAdapter() {
        return TestingApp::getPgsqlConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserName() {
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserName2() {
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => static::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage database "totally_not_existing_db" does not exist
     */
    public function testConnectionWithInvalidDbName() {
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => static::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => static::getValidAdapter()->getConnectionConfig()->getUserPassword()
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage password authentication failed for user
     */
    public function testConnectionWithInvalidUserPassword() {
        $config = PostgresConfig::fromArray([
            'database' => static::getValidAdapter()->getConnectionConfig()->getDbName(),
            'username' => static::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }

    /**
     * Note: very slow
     */
    /*public function testConnectionWithInvalidDbPort2() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('could not connect to server');
        $config = PostgresConfig::fromArray([
            'database' => static::getValidAdapter()->getConnectionConfig()->getDbName(),
            'username' => static::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => static::getValidAdapter()->getConnectionConfig()->getUserPassword(),
            'port' => '9999'
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }*/

    public function testValidConnection() {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        $this->assertEquals(1, $stmnt->rowCount());
    }

    public function testDisconnect() {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $adapter->disconnect();
        $reflector = new ReflectionClass($adapter);
        $prop = $reflector->getProperty('pdo');
        $prop->setAccessible(true);
        $this->assertEquals(null, $prop->getValue($adapter));
        $reflector->getProperty('pdo')->setAccessible(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid db entity name
     */
    public function testQuotingOfInvalidDbEntity() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('";DROP table1;');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Db entity name must be a not empty string
     */
    public function testQuotingOfInvalidDbEntity2() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName(['arrr']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Db entity name must be a not empty string
     */
    public function testQuotingOfInvalidDbEntity3() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName($adapter);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Db entity name must be a not empty string
     */
    public function testQuotingOfInvalidDbEntity4() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Db entity name must be a not empty string
     */
    public function testQuotingOfInvalidDbEntity5() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid db entity name [colname->->]
     */
    public function testQuotingOfInvalidDbEntity6() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname->->');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid db entity name [colname-> ->]
     */
    public function testQuotingOfInvalidDbEntity7() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname-> ->');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Db entity name must be a not empty string
     */
    public function testQuotingOfInvalidDbEntity8() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Value in $fieldType argument must be a constant like
     */
    public function testQuotingOfInvalidDbValueType() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('test', 'abrakadabra');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. String [abrakadabra] received
     */
    public function testQuotingOfInvalidIntDbValue() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('abrakadabra', PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Object fo class [\PeskyORM\Adapter\Postgres] received
     */
    public function testQuotingOfInvalidIntDbValue2() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue($adapter, PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Array received
     */
    public function testQuotingOfInvalidIntDbValue3() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(['key' => 'val'], PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Resource received
     */
    public function testQuotingOfInvalidIntDbValue4() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(curl_init('http://test.url'), PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Boolean [true] received
     */
    public function testQuotingOfInvalidIntDbValue5() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(true, PDO::PARAM_INT);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $value expected to be integer or numeric string. Boolean [false] received
     */
    public function testQuotingOfInvalidIntDbValue6() {
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(false, PDO::PARAM_INT);
    }

    /**
     * @expectedException TypeError
     * @expectedExceptionMessage must be an instance of PeskyORM\Core\DbExpr, string given
     */
    public function testQuotingOfInvalidDbExpr() {
        $adapter = static::getValidAdapter();
        $adapter->quoteDbExpr('test');
    }

    public function testQuoting() {
        $adapter = static::getValidAdapter();
        // names
        $this->assertEquals('"table1"', $adapter->quoteDbEntityName('table1'));
        $this->assertEquals('*', $adapter->quoteDbEntityName('*'));
        $this->assertEquals('"table"."colname"', $adapter->quoteDbEntityName('table.colname'));
        $this->assertEquals('"table"."colname"->\'jsonkey\'', $adapter->quoteDbEntityName('table.colname->jsonkey'));
        $this->assertEquals('"table"."colname"#>\'jsonkey\'', $adapter->quoteDbEntityName('table.colname #> jsonkey'));
        $this->assertEquals('"table"."colname"->>\'json key\'', $adapter->quoteDbEntityName('table.colname ->> json key'));
        $this->assertEquals('"table"."colname"#>>\'json key\'', $adapter->quoteDbEntityName('table.colname #>> \'json key\''));
        $this->assertEquals('"table"."colname"->\'json key\'', $adapter->quoteDbEntityName('table.colname -> "json key"'));
        $this->assertEquals('"table"."colname"->\'json key\'->>\'json key 2\'', $adapter->quoteDbEntityName('table.colname -> "json key" ->> json key 2'));
        // values
        $this->assertEquals("''';DROP table1;'", $adapter->quoteValue('\';DROP table1;'));
        $this->assertEquals('TRUE', $adapter->quoteValue(true));
        $this->assertEquals('TRUE', $adapter->quoteValue(1, PDO::PARAM_BOOL));
        $this->assertEquals('TRUE', $adapter->quoteValue('1', PDO::PARAM_BOOL));
        $this->assertEquals('FALSE', $adapter->quoteValue(false));
        $this->assertEquals('FALSE', $adapter->quoteValue(0, PDO::PARAM_BOOL));
        $this->assertEquals('FALSE', $adapter->quoteValue('0', PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null));
        $this->assertEquals('NULL', $adapter->quoteValue('abrakadabra', PDO::PARAM_NULL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_INT));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_STR));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_LOB));
        $this->assertEquals("'123'", $adapter->quoteValue(123));
        $this->assertEquals("'123'", $adapter->quoteValue(123, PDO::PARAM_INT));
        $this->assertEquals(
            'DELETE FROM "table1" WHERE "col1" = \'value1\'',
            $adapter->quoteDbExpr(DbExpr::create('DELETE FROM `table1` WHERE `col1` = ``value1``'))
        );
    }

    public function testBuildColumnsList() {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildColumnsList');
        $method->setAccessible(true);
        $colsList = $method->invoke($adapter, ['column1', 'alias.column2']);
        $this->assertEquals('("column1","alias"."column2")', $colsList);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument cannot be empty
     */
    public function testInvalidColumnsInBuildValuesList() {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $method->invoke($adapter, [], []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $valuesAssoc array does not contain key [col2]
     */
    public function testInvalidDataInBuildValuesList() {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $method->invoke($adapter, ['col1', 'col2'], ['col1' => '1']);
    }

    public function testBuildValuesList() {
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
            'col7' => 1.22
        ];
        $columns = array_keys($data);
        $valsList = $method->invoke($adapter, $columns, $data);
        $this->assertEquals("('val1','1',NULL,TRUE,FALSE,'','1.22')", $valsList);
        $valsList = $method->invoke($adapter, $columns, $data, ['col2' => PDO::PARAM_BOOL]);
        $this->assertEquals("('val1',TRUE,NULL,TRUE,FALSE,'','1.22')", $valsList);
    }

    

}
