<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;

class PostgresConfigTest extends BaseTestCase
{
    
    public function testInvalidDbName1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(null, null, null);
    }
    
    public function testInvalidDbName2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DB name argument cannot be empty');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('', 'test', 'test');
    }
    
    public function testInvalidDbName3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(false, null, null);
    }
    
    public function testInvalidDbName4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig([], null, null);
    }
    
    public function testInvalidDbName5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbName) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig(true, null, null);
    }
    
    public function testInvalidDbUser1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', null, null);
    }
    
    public function testInvalidDbUser2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', '', 'test');
    }
    
    public function testInvalidDbUser3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', false, null);
    }
    
    public function testInvalidDbUser4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', [], null);
    }
    
    public function testInvalidDbUser5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($user) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', true, null);
    }
    
    public function testInvalidDbPassword1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', null);
    }
    
    public function testInvalidDbPassword2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new PostgresConfig('test', 'test', '');
    }
    
    public function testInvalidDbPassword3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', false);
    }
    
    public function testInvalidDbPassword4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', []);
    }
    
    public function testInvalidDbPassword5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($password) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new PostgresConfig('test', 'test', true);
    }
    
    public function testInvalidDbHost1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(null);
    }
    
    public function testInvalidDbHost2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost([]);
    }
    
    public function testInvalidDbHost3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(true);
    }
    
    public function testInvalidDbHost4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($dbHost) must be of type string');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost(false);
    }
    
    public function testInvalidDbHost5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DB host argument cannot be empty');
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbHost('');
    }
    
    public function testInvalidDbPort1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }
    
    public function testInvalidDbPort2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort(null);
    }
    
    public function testInvalidDbPort3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort([]);
    }
    
    public function testInvalidDbPort4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort('123q');
    }
    
    public function testInvalidDbPort5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort('12 3');
    }
    
    public function testInvalidDbPort6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be a positive integer number or numeric string");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        $config->setDbPort(123.4);
    }
    
    public function testInvalidOptions(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$options) must be of type array");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setOptions(null);
    }
    
    public function testValidConfig(): void
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
    
    public function testInvalidFromArray1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(null);
    }
    
    public function testInvalidFromArray2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray('');
    }
    
    public function testInvalidFromArray3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(true);
    }
    
    public function testInvalidFromArray4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(false);
    }
    
    public function testInvalidFromArray5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray($this);
    }
    
    public function testInvalidFromArray6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($config) must be of type array');
        /** @noinspection PhpParamsInspection */
        PostgresConfig::fromArray(1243);
    }
    
    public function testInvalidDbNameFromArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'database\' key value');
        PostgresConfig::fromArray([]);
    }
    
    public function testInvalidDbUserFromArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'username\' key value');
        PostgresConfig::fromArray([
            'database' => 'test',
        ]);
    }
    
    public function testInvalidDbPasswordFromArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$config argument must contain not empty \'password\' key value');
        PostgresConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }
    
    public function testInvalidDbPortFromArray(): void
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
    
    public function testValidConfigFromArray(): void
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
    
    public function testValidConfigFromArray2(): void
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
