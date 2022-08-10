<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use ReflectionClass;

// WARNING: PostgreSQL Server shoud not have next lines in pg_hba.conf:
//      host all all 0.0.0.0/0 trust
//      host all all ::1/128 trust
// Instead there should be lines like
//      host all all 0.0.0.0/0 md5
//      host all all ::1/128 md5

class PostgresAdapterConnectionTest extends BaseTestCase
{
    
    static private function getValidAdapter()
    {
        return TestingApp::getPgsqlConnection();
    }
    
    public function testConnectionWithInvalidUserName()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches("(password authentication failed for user)");
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => 'totally_not_existing_user',
            'password' => 'this_password_is_for_not_existing_user',
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserName2()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches("(password authentication failed for user)");
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => static::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => 'this_password_is_for_not_existing_user',
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidDbName()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("database \"totally_not_existing_db\" does not exist");
        $config = PostgresConfig::fromArray([
            'database' => 'totally_not_existing_db',
            'username' => static::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => static::getValidAdapter()
                ->getConnectionConfig()
                ->getUserPassword(),
        ]);
        $adapter = new Postgres($config);
        $adapter->getConnection();
    }
    
    public function testConnectionWithInvalidUserPassword()
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("password authentication failed for user");
        $config = PostgresConfig::fromArray([
            'database' => static::getValidAdapter()
                ->getConnectionConfig()
                ->getDbName(),
            'username' => static::getValidAdapter()
                ->getConnectionConfig()
                ->getUserName(),
            'password' => 'this_password_is_for_not_existing_user',
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
    
    public function testValidConnection()
    {
        $adapter = static::getValidAdapter();
        $adapter->getConnection();
        $stmnt = $adapter->query('SELECT 1');
        static::assertEquals(1, $stmnt->rowCount());
    }
    
    public function testDisconnect()
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
