<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\TableStructure\TableColumn\Column;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingSettingsTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'settings';
    }
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function key(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues();
    }
    
    private function value(): Column
    {
        return Column::create(Column::TYPE_JSONB)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('{}');
    }
}