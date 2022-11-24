<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\Column;
use PeskyORM\ORM\TableStructure\TableStructure;

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