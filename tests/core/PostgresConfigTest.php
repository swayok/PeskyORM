<?php

use PeskyORM\Config\Connection\PostgresConfig;

class PostgresConfigTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName() {
        new PostgresConfig(null, null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName2() {
        new PostgresConfig('', null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName3() {
        new PostgresConfig(false, null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName4() {
        new PostgresConfig([], null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument must be a string
     */
    public function testInvalidDbName5() {
        new PostgresConfig(true, null, null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser() {
        new PostgresConfig('test', null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser2() {
        new PostgresConfig('test', '', null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser3() {
        new PostgresConfig('test', false, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser4() {
        new PostgresConfig('test', [], null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument must be a string
     */
    public function testInvalidDbUser5() {
        new PostgresConfig('test', true, null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword() {
        new PostgresConfig('test', 'test', null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword2() {
        new PostgresConfig('test', 'test', '');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword3() {
        new PostgresConfig('test', 'test', false);
    }


    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword4() {
        new PostgresConfig('test', 'test', []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument must be a string
     */
    public function testInvalidDbPassword5() {
        new PostgresConfig('test', 'test', true);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost2() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost([]);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument must be a string
     */
    public function testInvalidDbHost3() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(true);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost4() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbHost(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort2() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort3() {
        $config = new PostgresConfig('test', 'test', 'test');
        $config->setDbPort([]);
    }

    /**
     * @expectedException TypeError
     * @expectedExceptionMessage setOptions() must be of the type array
     */
    public function testInvalidOptions() {
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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument
     */
    public function testInvalidDbNameFromArray() {
        PostgresConfig::fromArray([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument
     */
    public function testInvalidDbUserFromArray() {
        PostgresConfig::fromArray([
            'database' => 'test'
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument
     */
    public function testInvalidDbPasswordFromArray() {
        PostgresConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPortFromArray() {
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
