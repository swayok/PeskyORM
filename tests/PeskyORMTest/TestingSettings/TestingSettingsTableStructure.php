<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\MixedJsonColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingSettingsTableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'settings';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(
            new IdColumn()
        );
        $this->addColumn(
            (new StringColumn('key'))
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new MixedJsonColumn('value'))
                ->setDefaultValue('{}')
        );
    }

    protected function registerRelations(): void
    {
    }
}