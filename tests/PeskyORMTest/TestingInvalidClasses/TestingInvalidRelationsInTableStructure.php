<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingInvalidRelationsInTableStructure extends TableStructure
{
    
    static public function getTableName(): string
    {
        return 'some_table';
    }
    
    private function valid()
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
    
    
    private function InvalidClass()
    {
        return $this;
    }
    
}