<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingSettingsTableStructure extends TableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'settings';
    }

    private function id() {
        return Column::create(Column::TYPE_INT)
            ->itIsPrimaryKey()
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
        ;
    }

    private function key() {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
        ;
    }

    private function value() {
        return Column::create(Column::TYPE_JSONB)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('{}')
        ;
    }
}