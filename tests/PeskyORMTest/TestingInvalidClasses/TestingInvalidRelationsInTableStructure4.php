<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column;
use PeskyORM\ORM\TableStructure\TableStructure;
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