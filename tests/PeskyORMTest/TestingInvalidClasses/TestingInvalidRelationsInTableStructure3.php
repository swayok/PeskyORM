<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure3 extends TableStructure
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
    
    private function InvalidLocalColumnName(): Relation
    {
        return new Relation('local_invalid', Relation::HAS_MANY, TestingAdminsTable::class, 'id');
    }
    
}