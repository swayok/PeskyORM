<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingInvalidRelationsInTableStructure extends TableStructure
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
        /** @noinspection PhpParamsInspection */
        $this->addRelation($this);
    }
}