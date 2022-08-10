<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterDeleteDataTest.php';

class MysqlAdapterDeleteDataTest extends PostgresAdapterDeleteDataTest
{
    
    /**
     * @return \PeskyORM\Adapter\Mysql
     */
    static protected function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
}
