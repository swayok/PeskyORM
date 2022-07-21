<?php

namespace Tests\Core;

use InvalidArgumentException;
use PDO;
use PDOException;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\DbExpr;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\PeskyORMTest\TestingApp;
use TypeError;

class MysqlAdapterGeneralFunctionalityTest extends TestCase {

    public static function setUpBeforeClass(): void {
        TestingApp::clearTables(static::getValidAdapter());
    }

    public static function tearDownAfterClass(): void {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static private function getValidAdapter() {
        return TestingApp::getMysqlConnection();
    }
    
    public function testConnectionWithInvalidUserName() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserName2() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => self::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidDbName() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => self::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => self::getValidAdapter()->getConnectionConfig()->getUserPassword()
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserPassword() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => self::getValidAdapter()->getConnectionConfig()->getDbName(),
            'username' => self::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => 'this_password_is_for_not_existing_user'
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }

    /**
     * Note: very slow
     */
    /*public function testConnectionWithInvalidDbPort2() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('SQLSTATE[HY000]');
        $config = MysqlConfig::fromArray([
            'database' => self::getValidAdapter()->getConnectionConfig()->getDbName(),
            'username' => self::getValidAdapter()->getConnectionConfig()->getUserName(),
            'password' => self::getValidAdapter()->getConnectionConfig()->getUserPassword(),
            'port' => '9999'
        ]);
        $adapter = new Mysql($config);
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
    
    public function testQuotingOfInvalidDbEntity() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('";DROP table1;');
    }
    
    public function testQuotingOfInvalidDbEntity2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteDbEntityName(['arrr']);
    }
    
    public function testQuotingOfInvalidDbEntity3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteDbEntityName($adapter);
    }
    
    public function testQuotingOfInvalidDbEntity4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName(true);
    }
    
    public function testQuotingOfInvalidDbEntity5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName(false);
    }
    
    public function testQuotingOfInvalidDbEntity6() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name [colname->->]");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname->->');
    }
    
    public function testQuotingOfInvalidDbEntity7() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name [colname-> ->]");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('colname-> ->');
    }
    
    public function testQuotingOfInvalidDbEntity8() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Db entity name must be a not empty string");
        $adapter = static::getValidAdapter();
        $adapter->quoteDbEntityName('');
    }
    
    public function testQuotingOfInvalidDbValueType() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Value in \$fieldType argument must be a constant like");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('test', 'abrakadabra');
    }
    
    public function testQuotingOfInvalidIntDbValue() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. String [abrakadabra] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue('abrakadabra', PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Object fo class [\PeskyORM\Adapter\Mysql] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue($adapter, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Array received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(['key' => 'val'], PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Resource received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(curl_init('http://test.url'), PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Boolean [true] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(true, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidIntDbValue6() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$value expected to be integer or numeric string. Boolean [false] received");
        $adapter = static::getValidAdapter();
        $adapter->quoteValue(false, PDO::PARAM_INT);
    }
    
    public function testQuotingOfInvalidDbExpr() {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage("must be an instance of PeskyORM\Core\DbExpr, string given");
        $adapter = static::getValidAdapter();
        /** @noinspection PhpParamsInspection */
        $adapter->quoteDbExpr('test');
    }

    public function testQuoting() {
        $adapter = static::getValidAdapter();
        // names
        $this->assertEquals('`table1`', $adapter->quoteDbEntityName('table1'));
        $this->assertEquals('*', $adapter->quoteDbEntityName('*'));
        $this->assertEquals('`table`.`colname`', $adapter->quoteDbEntityName('table.colname'));
        $this->assertEquals(
            '`table`.`colname`->\'$.jsonkey\'',
            $adapter->quoteDbEntityName('table.colname->jsonkey')
        );
        $this->assertEquals(
            'JSON_EXTRACT(`table`.`colname`, \'$.jsonkey\')',
            $adapter->quoteDbEntityName('table.colname #> jsonkey')
        );
        $this->assertEquals(
            '`table`.`colname`->>\'$[0][1]\'',
            $adapter->quoteDbEntityName('table.colname ->> [0][1]')
        );
        $this->assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(`table`.`colname`, \'$.json key\'))',
            $adapter->quoteDbEntityName('table.colname #>> \'json key\'')
        );
        $this->assertEquals(
            '`table`.`colname`->\'$.json key\'',
            $adapter->quoteDbEntityName('table.colname -> "json key"')
        );
        $this->assertEquals(
            '`table`.`colname`->\'$.json key\'->>\'$.json key 2\'',
            $adapter->quoteDbEntityName('table.colname -> "json key" ->> json key 2')
        );
        $this->assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(`table`.`colname`->\'$.json key\', \'$.json key 2\'))',
            $adapter->quoteDbEntityName('table.colname -> "json key" #>> json key 2')
        );
        $this->assertEquals(
            'JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(`table`.`colname`, \'$.json key\'), \'$.json key 2\'))',
            $adapter->quoteDbEntityName('table.colname #> "json key" #>> json key 2')
        );
        // values
        $this->assertEquals("'\\';DROP table1;'", $adapter->quoteValue('\';DROP table1;'));
        $this->assertEquals('1', $adapter->quoteValue(true));
        $this->assertEquals('1', $adapter->quoteValue(1, PDO::PARAM_BOOL));
        $this->assertEquals('1', $adapter->quoteValue('1', PDO::PARAM_BOOL));
        $this->assertEquals('0', $adapter->quoteValue(false));
        $this->assertEquals('0', $adapter->quoteValue(0, PDO::PARAM_BOOL));
        $this->assertEquals('0', $adapter->quoteValue('0', PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null));
        $this->assertEquals('NULL', $adapter->quoteValue('abrakadabra', PDO::PARAM_NULL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_INT));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_BOOL));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_STR));
        $this->assertEquals('NULL', $adapter->quoteValue(null, PDO::PARAM_LOB));
        $this->assertEquals("'123'", $adapter->quoteValue(123));
        $this->assertEquals("'123'", $adapter->quoteValue(123, PDO::PARAM_INT));
        /** @noinspection SqlWithoutWhere */
        $this->assertEquals(
            'DELETE FROM `table1` WHERE `col1` = \'value1\'',
            $adapter->quoteDbExpr(DbExpr::create('DELETE FROM `table1` WHERE `col1` = ``value1``'))
        );
    }

    public function testBuildColumnsList() {
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildColumnsList');
        $method->setAccessible(true);
        $colsList = $method->invoke($adapter, ['column1', 'alias.column2']);
        $this->assertEquals('(`column1`,`alias`.`column2`)', $colsList);
    }
    
    public function testInvalidColumnsInBuildValuesList() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument cannot be empty");
        $adapter = static::getValidAdapter();
        $method = (new ReflectionClass($adapter))->getMethod('buildValuesList');
        $method->setAccessible(true);
        $method->invoke($adapter, [], []);
    }
    
    public function testInvalidDataInBuildValuesList() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$valuesAssoc array does not contain key [col2]");
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
        $this->assertEquals("('val1','1',NULL,1,0,'','1.22')", $valsList);
        $valsList = $method->invoke($adapter, $columns, $data, ['col2' => PDO::PARAM_BOOL]);
        $this->assertEquals("('val1',1,NULL,1,0,'','1.22')", $valsList);
    }
}
