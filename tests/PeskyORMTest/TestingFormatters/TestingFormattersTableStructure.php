<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingFormattersTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    private function id(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function created_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP)
            ->allowsNullValues()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
    
    private function created_at_unix(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_UNIX_TIMESTAMP)
            ->allowsNullValues();
    }
    
    private function creation_date(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_DATE)
            ->allowsNullValues();
    }
    
    private function creation_time(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIME)
            ->allowsNullValues();
    }
    
    private function json_data1(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_JSONB)
            ->disallowsNullValues()
            ->setDefaultValue('{}');
    }
    
    private function json_data2(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_JSON)
            ->disallowsNullValues()
            ->setClassNameForValueToObjectFormatter(TestingFormatterJsonObject::class);
    }
    
    
}
