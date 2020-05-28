<?php

namespace Tests\Core;

use InvalidArgumentException;
use PeskyORM\Config\Connection\MysqlConfig;
use PHPUnit\Framework\TestCase;
use TypeError;

class MysqlConfigTest extends TestCase {

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
        /** @noinspection PhpParamsInspection */
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
        /** @noinspection PhpParamsInspection */
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
        /** @noinspection PhpParamsInspection */
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
        /** @noinspection PhpParamsInspection */
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
        /** @noinspection PhpParamsInspection */
        $config->setDbPort([]);
    }

    /**
     * @expectedException TypeError
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
        $this->assertEquals('username', $config->getUserName());
        $this->assertEquals('password', $config->getUserPassword());
        $this->assertEquals([], $config->getOptions());
        $this->assertEquals('mysql:host=localhost;port=3306;dbname=dbname;charset=utf8', $config->getPdoConnectionString());

        $config->setDbHost('192.168.0.1');
        $this->assertEquals('mysql:host=192.168.0.1;port=3306;dbname=dbname;charset=utf8', $config->getPdoConnectionString());

        $config->setDbPort(9999);
        $this->assertEquals('mysql:host=192.168.0.1;port=9999;dbname=dbname;charset=utf8', $config->getPdoConnectionString());

        $config->setDbPort('8888');
        $this->assertEquals('mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=utf8', $config->getPdoConnectionString());

        $config->setCharset('cp1251');
        $this->assertEquals('mysql:host=192.168.0.1;port=8888;dbname=dbname;charset=cp1251', $config->getPdoConnectionString());

        $testOptions = ['test' => 'test'];
        $config->setOptions($testOptions);
        $this->assertEquals($testOptions, $config->getOptions());

        $config->setUnixSocket('/tmp/mysql.sock');
        $this->assertEquals('mysql:unix_socket=/tmp/mysql.sock;dbname=dbname;charset=cp1251', $config->getPdoConnectionString());
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
            'database' => 'test'
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB password argument
     */
    public function testInvalidDbPasswordFromArray() {
        MysqlConfig::fromArray([
            'database' => 'test',
            'username' => 'test',
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage DB port argument must be an integer number
     */
    public function testInvalidDbPortFromArray() {
        MysqlConfig::fromArray([
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
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            "mysql:host=localhost;port=3306;dbname={$test['database']};charset=utf8",
            $config->getPdoConnectionString()
        );
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
            'charset' => '1251',
            'unix_socket' => '',
            'options' => ['test' => 'test']
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['database']};charset={$test['charset']}",
            $config->getPdoConnectionString()
        );
        $this->assertEquals($test['options'], $config->getOptions());
    }

    public function testValidConfigFromArray3() {
        $test = [
            'database' => 'dbname',
            'username' => 'username',
            'password' => 'password',
            'port' => '1234',
            'host' => '192.168.0.1',
            'unix_socket' => '/tmp/mysql.sock',
            'options' => ['test' => 'test']
        ];
        $config = MysqlConfig::fromArray($test);
        $this->assertEquals(
            "mysql:host={$test['host']};port={$test['port']};dbname={$test['database']};charset=utf8",
            $config->getPdoConnectionString()
        );
        $this->assertEquals($test['options'], $config->getOptions());
    }
}
