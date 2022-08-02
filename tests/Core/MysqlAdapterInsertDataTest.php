<?php

namespace Tests\Core;

use Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterInsertDataTest.php';

class MysqlAdapterInsertDataTest extends PostgresAdapterInsertDataTest
{
    
    static protected function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
}
