<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresAdapterDeleteDataTest.php';

class MysqlAdapterDeleteDataTest extends PostgresAdapterDeleteDataTest
{
    
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }
    
}
