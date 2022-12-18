<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingNoPkColumnInTableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'invalid';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(
            new IntegerColumn('not_a_pk')
        );
    }

    protected function registerRelations(): void
    {

    }
}