<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class MysqlAdapterDeleteDataTest extends PostgresAdapterDeleteDataTest
{
    
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }
    
}
