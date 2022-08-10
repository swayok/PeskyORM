<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use InvalidArgumentException;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;

class MysqlConfigTest extends BaseTestCase
{
    
    public function testInvalidDbName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new MysqlConfig(null, null, null);
    }
    
    public function testInvalidDbName2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new MysqlConfig('', null, null);
    }
    
    public function testInvalidDbName3()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new MysqlConfig(false, null, null);
    }
    
    public function testInvalidDbName4()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new MysqlConfig([], null, null);
    }
    
    public function testInvalidDbName5()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument must be a string");
        new MysqlConfig(true, null, null);
    }
    
    public function testInvalidDbUser()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new MysqlConfig('test', null, null);
    }
    
    public function testInvalidDbUser2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new MysqlConfig('test', '', null);
    }
    
    public function testInvalidDbUser3()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new MysqlConfig('test', false, null);
    }
    
    public function testInvalidDbUser4()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new MysqlConfig('test', [], null);
    }
    
    public function testInvalidDbUser5()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument must be a string");
        new MysqlConfig('test', true, null);
    }
    
    public function testInvalidDbPassword()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new MysqlConfig('test', 'test', null);
    }
    
    public function testInvalidDbPassword2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new MysqlConfig('test', 'test', '');
    }
    
    public function testInvalidDbPassword3()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new MysqlConfig('test', 'test', false);
    }
    
    
    public function testInvalidDbPassword4()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new MysqlConfig('test', 'test', []);
    }
    
    public function testInvalidDbPassword5()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument must be a string");
        new MysqlConfig('test', 'test', true);
    }
    
    public function testInvalidDbHost()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(null);
    }
    
    public function testInvalidDbHost2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new MysqlConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setDbHost([]);
    }
    
    public function testInvalidDbHost3()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument must be a string");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(true);
    }
    
    public function testInvalidDbHost4()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(false);
    }
    
    public function testInvalidDbPort()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }
    
    public function testInvalidDbPort2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbPort(null);
    }
    
    public function testInvalidDbPort3()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new MysqlConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setDbPort([]);
    }
    
    public function testInvalidOptions()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$options) must be of type array");
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setOptions(null);
    }
    
    public function testInvalidCharset()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$charset) must be of type string");
        $config = new MysqlConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setCharset(null);
    }
    
    public function testInvalidCharset2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$charset) must be of type string");
        $config = new MysqlConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setCharset(true);
    }
    
    public function testValidConfig()
    {
        $config = new MysqlConfig('dbname', 'username', 'password');
        static::assertEquals('username', $config->getUserName());
        static::assertEquals('password', $config->getUserPassword());
        static::assertEquals([], $config->getOptions());
        static::assertEquals('mysql:host=localhost;port=3306;dbname=dbname;charset=utf8', $config->getPdoConnectionString());
        
        $config->setDbHost('192.168.0.1');
        static::assertEquals('mysql:host=192.168.0.1;port=3306;dbname=dbname;charset=utf8', $config->getPdoConnectionString());
        
        $config->setDbPort(9999);
        static::assertEquals('mysql:host=192.168.0.1;port=9999;dbname=dbname;charset=utf8', $config->getPdoConnectionString());
        
        $config->setDbPort('8888');
        static::assertEquals('mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=utf8', $config->getPdoConnectionString());
        
        $config->setCharset('cp1251');
        static::assertEquals('mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=cp1251', $config->getPdoConnectionString());
        
        $testOptions = ['test' => 'test'];
        $config->setOptions($testOptions);
        static::assertEquals($testOptions, $config->getOptions());
        
        $config->setUnixSocket('/tmp/mysql.sock');
        static::assertEquals('mysql:unix_socket=/tmp/mysql.sock;dbname=dbname;charset=cp1251', $config->getPdoConnectionString());
    }
    
    public function testInvalidDbNameFromArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument");
        MysqlConfig::fromArray([]);
    }
    
    public function testInvalidDbUserFromArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument");
        MysqlConfig::fromArray([
            'database' => 'test',
        ]);
    }
    
    public function testInvalidDbPasswordFromArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument");
        MysqlConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }
    
    public function testInvalidDbPortFromArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        MysqlConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
            'password' => 'test',
            'port' => 'test',
        ]);
    }
    
    public function testValidConfigFromArray()
    {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
        ];
        $config = MysqlConfig::fromArray($test);
        static::assertEquals(
            "mysql:host=localhost;port=3306;dbname={$test['database']};charset=utf8",
            $config->getPdoConnectionString()
        );
        static::assertEquals($test['username'], $config->getUserName());
        static::assertEquals($test['password'], $config->getUserPassword());
        static::assertEquals([], $config->getOptions());
    }
    
    public function testValidConfigFromArray2()
    {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'charset' => '1251',
            'unix_socket' => '',
            'options' => ['test' => 'test'],
        ];
        $config = MysqlConfig::fromArray($test);
        static::assertEquals(
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['database']};charset={$test['charset']}",
            $config->getPdoConnectionString()
        );
        static::assertEquals($test['options'], $config->getOptions());
    }
    
    public function testValidConfigFromArray3()
    {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'unix_socket' => '/tmp/mysql.sock',
            'options' => ['test' => 'test'],
        ];
        $config = MysqlConfig::fromArray($test);
        static::assertEquals(
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['database']};charset=utf8",
            $config->getPdoConnectionString()
        );
        static::assertEquals($test['options'], $config->getOptions());
    }
}
