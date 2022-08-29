<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

require_once __DIR__ . '/PostgresAdapterUpdateDataTest.php';

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class MysqlAdapterUpdateDataTest extends PostgresAdapterUpdateDataTest
{
    
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }
    
}
