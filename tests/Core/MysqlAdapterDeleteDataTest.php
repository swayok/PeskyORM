<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterDeleteDataTest.php';

class MysqlAdapterDeleteDataTest extends PostgresAdapterDeleteDataTest
{
    
    /**
     * @return Mysql
     */
    protected static function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
}
