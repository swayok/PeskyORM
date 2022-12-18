<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingTwoPrimaryKeysColumnsTableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'invalid';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(new IdColumn('pk1'));
        $this->addColumn(new IdColumn('pk2'));
    }

    protected function registerRelations(): void
    {

    }
}