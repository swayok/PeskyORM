<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterInsertDataTest.php';

class MysqlAdapterInsertDataTest extends PostgresAdapterInsertDataTest
{
    
    protected static function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
}
