<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use ReflectionClass;

class MysqlAdapterConnectionTest extends BaseTestCase
{
    
    private static function getValidAdapter(): Mysql
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testConnectionWithInvalidUserName(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user',
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserName2(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => self::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => 'this_password_is_for_not_existing_user',
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidDbName(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => self::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => self::getValidAdapter()
                ->getConnectionConfig()
                ->getUserPassword(),
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserPassword(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("Access denied for user");
        $config = MysqlConfig::fromArray([
            'database' => self::getValidAdapter()
                ->getConnectionConfig()
                ->getDbName(),
            'username' => self::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => 'this_password_is_for_not_existing_user',
        ]);
        $adapter = new Mysql($config);
        $adapter->getConnection();
    }
    
    /**
     * Note: very slow
     */
    /*public function testConnectionWithInvalidDbPort2() {
        $this->expectException(\PDOException::class);
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
    
    public function testValidConnection(): void
    {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        static::assertEquals(1, $stmnt->rowCount());
    }
    
    public function testDisconnect(): void
    {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $adapter->disconnect();
        $reflector = new ReflectionClass($adapter);
        $prop = $reflector->getProperty('pdo');
        $prop->setAccessible(true);
        static::assertEquals(null, $prop->getValue($adapter));
        $reflector->getProperty('pdo')
            ->setAccessible(false);
    }
}
