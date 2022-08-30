<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure2 extends TableStructure
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
    
    private function InvalidLocalColumnName(): Relation
    {
        return Relation::create('local_invalid', Relation::HAS_MANY, TestingAdminsTable::class, 'id');
    }
    
    private function InvalidForeignColumnName(): Relation
    {
        return Relation::create('valid', Relation::HAS_MANY, TestingAdminsTable::class, 'foreign_invalid');
    }
    
    private function InvalidForeignTableClass(): Relation
    {
        return Relation::create('valid', Relation::HAS_MANY, '___class_invalid', 'id');
    }
    
}