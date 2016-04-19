<?php

use PeskyORM\Config\Connection\MysqlConfig;

class MysqlConfigTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName() {
        new MysqlConfig(null, null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName2() {
        new MysqlConfig('', null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName3() {
        new MysqlConfig(false, null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument cannot be empty
     */
    public function testInvalidDbName4() {
        new MysqlConfig([], null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument must be a string
     */
    public function testInvalidDbName5() {
        new MysqlConfig(true, null, null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser() {
        new MysqlConfig('test', null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser2() {
        new MysqlConfig('test', '', null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser3() {
        new MysqlConfig('test', false, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument cannot be empty
     */
    public function testInvalidDbUser4() {
        new MysqlConfig('test', [], null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument must be a string
     */
    public function testInvalidDbUser5() {
        new MysqlConfig('test', true, null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword() {
        new MysqlConfig('test', 'test', null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword2() {
        new MysqlConfig('test', 'test', '');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword3() {
        new MysqlConfig('test', 'test', false);
    }


    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument cannot be empty
     */
    public function testInvalidDbPassword4() {
        new MysqlConfig('test', 'test', []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument must be a string
     */
    public function testInvalidDbPassword5() {
        new MysqlConfig('test', 'test', true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost2() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument must be a string
     */
    public function testInvalidDbHost3() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB host argument cannot be empty
     */
    public function testInvalidDbHost4() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbHost(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbPort('test');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort2() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbPort(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPort3() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setDbPort([]);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     * @expectedExceptionMessage setOptions() must be of the type array
     */
    public function testInvalidOptions() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setOptions(null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB charset argument cannot be empty
     */
    public function testInvalidCharset() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setCharset(null);
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB charset argument must be a string
     */
    public function testInvalidCharset2() {
        $config = new MysqlConfig('test', 'test', 'test');
        $config->setCharset(true);
    }

    public function testValidConfig() {
        $config = new MysqlConfig('dbname', 'username', 'password');
        $this->assertEquals($config->getUserName(), 'username');
        $this->assertEquals($config->getUserPassword(), 'password');
        $this->assertEquals($config->getOptions(), []);
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:host=localhost;port=3306;dbname=dbname;charset=utf8');

        $config->setDbHost('192.168.0.1');
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:host=192.168.0.1;port=3306;dbname=dbname;charset=utf8');

        $config->setDbPort(9999);
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:host=192.168.0.1;port=9999;dbname=dbname;charset=utf8');

        $config->setDbPort('8888');
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=utf8');

        $config->setCharset('cp1251');
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=cp1251');

        $testOptions = ['test' => 'test'];
        $config->setOptions($testOptions);
        $this->assertEquals($config->getOptions(), $testOptions);

        $config->setUnixSocket('/tmp/mysql.sock');
        $this->assertEquals($config->getPdoConnectionString(), 'mysql:unix_socket=/tmp/mysql.sock;dbname=dbname;charset=cp1251');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB name argument
     */
    public function testInvalidDbNameFromArray() {
        MysqlConfig::fromArray([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB user argument
     */
    public function testInvalidDbUserFromArray() {
        MysqlConfig::fromArray([
            'name' => 'test'
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument
     */
    public function testInvalidDbPasswordFromArray() {
        MysqlConfig::fromArray([
            'name' => 'test',
            'user' => 'test',
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPortFromArray() {
        MysqlConfig::fromArray([
            'name' => 'test',
            'user' => 'test',
            'password' => 'test',
            'port' => 'test'
        ]);
    }

    public function testValidConfigFromArray() {
        $test = [
            'name' => 'dbname',
            'user' => 'username',
            'password' => 'password',
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            $config->getPdoConnectionString(),
            "mysql:host=localhost;port=3306;dbname={$test['name']};charset=utf8"
        );
        $this->assertEquals($config->getUserName(), $test['user']);
        $this->assertEquals($config->getUserPassword(), $test['password']);
        $this->assertEquals($config->getOptions(), []);
    }

    public function testValidConfigFromArray2() {
        $test = [
            'name' => 'dbname',
            'user' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'charset' => '1251',
            'unix_socket' => '',
            'options' => ['test' => 'test']
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            $config->getPdoConnectionString(),
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['name']};charset={$test['charset']}"
        );
        $this->assertEquals($config->getOptions(), $test['options']);
    }

    public function testValidConfigFromArray3() {
        $test = [
            'name' => 'dbname',
            'user' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'unix_socket' => '/tmp/mysql.sock',
            'options' => ['test' => 'test']
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            $config->getPdoConnectionString(),
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['name']};charset=utf8"
        );
        $this->assertEquals($config->getOptions(), $test['options']);
    }
}
