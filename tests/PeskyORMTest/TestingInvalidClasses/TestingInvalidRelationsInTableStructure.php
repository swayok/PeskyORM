<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingInvalidRelationsInTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'some_table';
    }
    
    private function valid(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
    
    
    private function InvalidClass(): TestingInvalidRelationsInTableStructure
    {
        return $this;
    }
    
}