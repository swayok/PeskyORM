<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

require_once __DIR__ . '/PostgresAdapterSelectDataTest.php';

use InvalidArgumentException;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\Select;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class MysqlAdapterSelectDataTest extends PostgresAdapterSelectDataTest
{
    
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function testInvalidAnalyzeColumnName1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("SELECT: Invalid column name: [test test]");
        static::getValidAdapter()->selectColumn('admins', 'test test');
    }
    
    public function testInvalidAnalyzeColumnName2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("SELECT: Invalid column name: [test%test]");
        static::getValidAdapter()->selectColumn('admins', 'test%test');
    }

    public function testInvalidWith1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$selectName argument value does not fit DB entity naming rules');
        $select = new Select('admins', static::getValidAdapter());
        $withSelect = new Select('admins', static::getValidAdapter());
        $select->with($withSelect, 'asdas as das das');
        static::assertTrue(true);
    }

    public function testJsonSelects(): void
    {
        static::assertTrue(true);
    }
}
