<?php

namespace Tests\Core;

require_once __DIR__ . '/PostgresAdapterUpdateDataTest.php';

use Tests\PeskyORMTest\TestingApp;

class MysqlAdapterUpdateDataTest extends PostgresAdapterUpdateDataTest
{
    
    static protected function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
}
