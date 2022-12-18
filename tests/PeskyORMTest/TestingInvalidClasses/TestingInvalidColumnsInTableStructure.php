<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableStructure;

class TestingInvalidColumnsInTableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'invalid';
    }

    protected function registerColumns(): void
    {
        /** @noinspection PhpParamsInspection */
        $this->addColumn($this);
    }

    protected function registerRelations(): void
    {
    }
}