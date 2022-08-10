<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

require_once __DIR__ . '/PostgresAdapterSelectDataTest.php';

use InvalidArgumentException;
use PeskyORM\Core\Select;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class MysqlAdapterSelectDataTest extends PostgresAdapterSelectDataTest
{
    
    protected static function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testInvalidAnalyzeColumnName1()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name: [test test]");
        static::getValidAdapter()->selectColumn('admins', 'test test');
    }
    
    public function testInvalidAnalyzeColumnName2()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid db entity name: [test%test]");
        static::getValidAdapter()->selectColumn('admins', 'test%test');
    }
    
    public function testInvalidWith1()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$selectAlias argument does not fit DB entity naming rules (usually alphanumeric string with underscores)"
        );
        $select = new Select('admins', static::getValidAdapter());
        $withSelect = new Select('admins', static::getValidAdapter());
        $select->with($withSelect, 'asdas as das das');
        static::assertTrue(true);
    }
}
