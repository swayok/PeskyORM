<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\VirtualTable;

use PeskyORM\ORM\TableStructure\TableColumn\Column\VirtualColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class VirtualTableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'virtual';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(new VirtualColumn(
            'test',
            function () {
                return false;
            },
            function () {
                return null;
            }
        ));
    }

    protected function registerRelations(): void
    {
    }


}