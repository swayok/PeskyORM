<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure4 extends TableStructure
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
    
    private function InvalidForeignColumnName(): Relation
    {
        return Relation::create('valid', Relation::HAS_MANY, TestingAdminsTable::class, 'foreign_invalid');
    }
    
}