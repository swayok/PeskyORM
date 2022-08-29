<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;

class PostgresConfigTest extends BaseTestCase
{
    
    public function testInvalidDbName1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(null, null, null);
    }
    
    public function testInvalidDbName2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DB name argument cannot be empty');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('', 'test', 'test');
    }
    
    public function testInvalidDbName3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(false, null, null);
    }
    
    public function testInvalidDbName4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig([], null, null);
    }
    
    public function testInvalidDbName5()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(true, null, null);
    }
    
    public function testInvalidDbUser1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', null, null);
    }
    
    public function testInvalidDbUser2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', '', 'test');
    }
    
    public function testInvalidDbUser3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', false, null);
    }
    
    public function testInvalidDbUser4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', [], null);
    }
    
    public function testInvalidDbUser5()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', true, null);
    }
    
    public function testInvalidDbPassword1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', null);
    }
    
    public function testInvalidDbPassword2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new PostgresConfig('test', 'test', '');
    }
    
    public function testInvalidDbPassword3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', false);
    }
    
    public function testInvalidDbPassword4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', []);
    }
    
    public function testInvalidDbPassword5()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', true);
    }
    
    public function testInvalidDbHost1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(null);
    }
    
    public function testInvalidDbHost2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost([]);
    }
    
    public function testInvalidDbHost3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(true);
    }
    
    public function testInvalidDbHost4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(false);
    }
    
    public function testInvalidDbHost5()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DB host argument cannot be empty');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost('');
    }
    
    public function testInvalidDbPort1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }
    
    public function testInvalidDbPort2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort(null);
    }
    
    public function testInvalidDbPort3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort([]);
    }
    
    public function testInvalidDbPort4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort('123q');
    }
    
    public function testInvalidDbPort5()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort('12 3');
    }
    
    public function testInvalidDbPort6()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort(' 123');
    }
    
    public function testInvalidDbPort7()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort(123.4);
    }
    
    public function testInvalidOptions()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$options) must be of type array");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setOptions(null);
    }
    
    public function testValidConfig()
    {
        $config = new PostgresConfig('dbname', 'username', 'password');
        $defaultOptions = $config->getOptions();
        static::assertEquals('username', $config->getUserName());
        static::assertEquals('password', $config->getUserPassword());
        static::assertEquals($defaultOptions, $config->getOptions());
        static::assertEquals('pgsql:host=localhost;port=5432;dbname=dbname', $config->getPdoConnectionString());
        
        $config->setDbHost('192.168.0.1');
        static::assertEquals('pgsql:host=192.168.0.1;port=5432;dbname=dbname', $config->getPdoConnectionString());
        
        $config->setDbPort(9999);
        static::assertEquals('pgsql:host=192.168.0.1;port=9999;dbname=dbname', $config->getPdoConnectionString());
        
        $config->setDbPort('8888');
        static::assertEquals('pgsql:host=192.168.0.1;port=8888;dbname=dbname', $config->getPdoConnectionString());
        
        $testOptions = ['test' => 'test'];
        $config->setOptions($testOptions);
        static::assertEquals($testOptions, $config->getOptions());
    }
    
    public function testInvalidFromArray1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(null);
    }
    
    public function testInvalidFromArray2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray('');
    }
    
    public function testInvalidFromArray3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(true);
    }
    
    public function testInvalidFromArray4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(false);
    }
    
    public function testInvalidFromArray5()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray($this);
    }
    
    public function testInvalidFromArray6()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(1243);
    }
    
    public function testInvalidDbNameFromArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'database\' key value');
        PostgresConfig::fromArray([]);
    }
    
    public function testInvalidDbUserFromArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'username\' key value');
        PostgresConfig::fromArray([
            'database' => 'test',
        ]);
    }
    
    public function testInvalidDbPasswordFromArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'password\' key value');
        PostgresConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }
    
    public function testInvalidDbPortFromArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        PostgresConfig::fromArray([
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
            'port' => '',
            'host' => '',
            'options' => '',
        ];
        $emptyConfig = new PostgresConfig('test', 'user', 'pass');
        $defaultOptions = $emptyConfig->getOptions();
    
        $config = PostgresConfig::fromArray($test);
        static::assertEquals("pgsql:host=localhost;port=5432;dbname={$test['database']}", $config->getPdoConnectionString());
        static::assertEquals($test['username'], $config->getUserName());
        static::assertEquals($test['password'], $config->getUserPassword());
        static::assertEquals($defaultOptions, $config->getOptions());
    }
    
    public function testValidConfigFromArray2()
    {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'options' => ['test' => 'test'],
        ];
        $config = PostgresConfig::fromArray($test);
        static::assertEquals(
            "pgsql:host={$test['host']};port={$test['port']};dbname={$test['database']}",
            $config->getPdoConnectionString()
        );
        static::assertEquals($test['options'], $config->getOptions());
    }
    
}
