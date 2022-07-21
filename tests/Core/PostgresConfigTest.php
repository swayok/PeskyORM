<?php

namespace Tests\Core;

use InvalidArgumentException;
use PeskyORM\Config\Connection\PostgresConfig;
use PHPUnit\Framework\TestCase;
use TypeError;

class PostgresConfigTest extends TestCase {
    
    public function testInvalidDbName() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new PostgresConfig(null, null, null);
    }
    
    public function testInvalidDbName2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new PostgresConfig('', null, null);
    }
    
    public function testInvalidDbName3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        new PostgresConfig(false, null, null);
    }
    
    public function testInvalidDbName4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new PostgresConfig([], null, null);
    }
    
    public function testInvalidDbName5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument must be a string");
        new PostgresConfig(true, null, null);
    }
    
    public function testInvalidDbUser() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new PostgresConfig('test', null, null);
    }
    
    public function testInvalidDbUser2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new PostgresConfig('test', '', null);
    }
    
    public function testInvalidDbUser3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        new PostgresConfig('test', false, null);
    }
    
    public function testInvalidDbUser4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new PostgresConfig('test', [], null);
    }
    
    public function testInvalidDbUser5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument must be a string");
        new PostgresConfig('test', true, null);
    }
    
    public function testInvalidDbPassword() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new PostgresConfig('test', 'test', null);
    }
    
    public function testInvalidDbPassword2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new PostgresConfig('test', 'test', '');
    }
    
    public function testInvalidDbPassword3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        new PostgresConfig('test', 'test', false);
    }
    
    
    public function testInvalidDbPassword4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        new PostgresConfig('test', 'test', []);
    }
    
    public function testInvalidDbPassword5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument must be a string");
        new PostgresConfig('test', 'test', true);
    }
    
    public function testInvalidDbHost() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(null);
    }
    
    public function testInvalidDbHost2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setDbHost([]);
    }
    
    public function testInvalidDbHost3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument must be a string");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(true);
    }
    
    public function testInvalidDbHost4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB host argument cannot be empty");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(false);
    }
    
    public function testInvalidDbPort() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }
    
    public function testInvalidDbPort2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort(null);
    }
    
    public function testInvalidDbPort3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        $config = new PostgresConfig('test', 'test', 'test');
        /** @noinspection PhpParamsInspection */
        $config->setDbPort([]);
    }
    
    public function testInvalidOptions() {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage("setOptions() must be of the type array");
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setOptions(null);
    }

    public function testValidConfig() {
        $config = new PostgresConfig('dbname', 'username', 'password');
        $this->assertEquals('username', $config->getUserName());
        $this->assertEquals('password', $config->getUserPassword());
        $this->assertEquals([], $config->getOptions());
        $this->assertEquals('pgsql:host=localhost;port=5432;dbname=dbname', $config->getPdoConnectionString());

        $config->setDbHost('192.168.0.1');
        $this->assertEquals('pgsql:host=192.168.0.1;port=5432;dbname=dbname', $config->getPdoConnectionString());

        $config->setDbPort(9999);
        $this->assertEquals('pgsql:host=192.168.0.1;port=9999;dbname=dbname', $config->getPdoConnectionString());

        $config->setDbPort('8888');
        $this->assertEquals('pgsql:host=192.168.0.1;port=8888;dbname=dbname', $config->getPdoConnectionString());

        $testOptions = ['test' => 'test'];
        $config->setOptions($testOptions);
        $this->assertEquals($testOptions, $config->getOptions());
    }
    
    public function testInvalidDbNameFromArray() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB name argument");
        PostgresConfig::fromArray([]);
    }
    
    public function testInvalidDbUserFromArray() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB user argument");
        PostgresConfig::fromArray([
            'database' => 'test'
        ]);
    }
    
    public function testInvalidDbPasswordFromArray() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB password argument");
        PostgresConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }
    
    public function testInvalidDbPortFromArray() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB port argument must be an integer number");
        PostgresConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
            'password' => 'test',
            'port' => 'test'
        ]);
    }

    public function testValidConfigFromArray() {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '',
            'host' => '',
            'options' => ''
        ];
        $config = PostgresConfig::fromArray($test);
        $this->assertEquals("pgsql:host=localhost;port=5432;dbname={$test['database']}", $config->getPdoConnectionString());
        $this->assertEquals($test['username'], $config->getUserName());
        $this->assertEquals($test['password'], $config->getUserPassword());
        $this->assertEquals([], $config->getOptions());
    }

    public function testValidConfigFromArray2() {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'options' => ['test' => 'test']
        ];
        $config = PostgresConfig::fromArray($test);
        $this->assertEquals(
            "pgsql:host={$test['host']};port={$test['port']};dbname={$test['database']}",
            $config->getPdoConnectionString()
        );
        $this->assertEquals($test['options'], $config->getOptions());
    }

}
