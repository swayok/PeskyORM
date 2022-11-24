<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingInvalidRelationsInTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'some_table';
    }
    
    private function valid(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey();
    }
    
    
    private function InvalidClass(): TestingInvalidRelationsInTableStructure
    {
        return $this;
    }
    
}