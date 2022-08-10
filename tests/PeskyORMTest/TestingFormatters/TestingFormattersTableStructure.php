<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingFormattersTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function created_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->allowsNullValues()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
    
    private function created_at_unix(): Column
    {
        return Column::create(Column::TYPE_UNIX_TIMESTAMP)
            ->allowsNullValues();
    }
    
    private function creation_date(): Column
    {
        return Column::create(Column::TYPE_DATE)
            ->allowsNullValues();
    }
    
    private function creation_time(): Column
    {
        return Column::create(Column::TYPE_TIME)
            ->allowsNullValues();
    }
    
    private function json_data1(): Column
    {
        return Column::create(Column::TYPE_JSONB)
            ->disallowsNullValues()
            ->setDefaultValue('{}');
    }
    
    private function json_data2(): Column
    {
        return Column::create(Column::TYPE_JSON)
            ->disallowsNullValues()
            ->setClassNameForValueToObjectFormatter(TestingFormatterJsonObject::class);
    }
    
    
}
