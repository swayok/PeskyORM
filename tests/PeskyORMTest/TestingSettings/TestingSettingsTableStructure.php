<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingSettingsTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'settings';
    }
    
    private function id(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function key(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function value(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_JSONB)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('{}');
    }
}