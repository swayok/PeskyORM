<?php

namespace Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingSettingsTableStructure extends TableStructure {

    static public function getTableName(): string {
        return 'settings';
    }

    private function id() {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
        ;
    }

    private function key() {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
        ;
    }

    private function value() {
        return Column::create(Column::TYPE_JSONB)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('{}')
        ;
    }
}