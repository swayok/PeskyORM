<?php

namespace Tests\Core;

require_once __DIR__ . '/PostgresAdapterSelectDataTest.php';

use InvalidArgumentException;
use PeskyORM\Core\Select;
use Tests\PeskyORMTest\TestingApp;

class MysqlAdapterSelectDataTest extends PostgresAdapterSelectDataTest
{
    
    static protected function getValidAdapter()
    {
        return TestingApp::getMysqlConnection();
    }
    
    public function convertTestDataForAdminsTableAssert($data)
    {
        foreach ($data as &$item) {
            $item['id'] = (string)$item['id'];
            $item['is_superadmin'] = $item['is_superadmin'] ? '1' : '0';
            $item['is_active'] = $item['is_active'] ? '1' : '0';
            $item['created_at'] = preg_replace('%\+00$%', '', $item['created_at']);
            $item['updated_at'] = preg_replace('%\+00$%', '', $item['updated_at']);
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
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
