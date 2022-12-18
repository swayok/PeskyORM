<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure2 extends TableStructure
{
    public function getTableName(): string
    {
        return 'some_table';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(new IdColumn('valid'));
    }

    protected function registerRelations(): void
    {
        $this->addRelation(
            new Relation(
                'local_invalid',
                Relation::HAS_MANY,
                TestingAdminsTable::class,
                'parent_id',
                'InvalidLocalColumnName'
            )
        );
    }
}