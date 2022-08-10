<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure3 extends TableStructure
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
    
    private function InvalidLocalColumnName()
    {
        return Relation::create('local_invalid', Relation::HAS_MANY, TestingAdminsTable::class, 'id');
    }
    
}