<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableStructure;

class TestingSettingsTableStructure extends DbTableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'settings';
    }

    private function id() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey()
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
        ;
    }

    private function key() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
        ;
    }

    private function value() {
        return DbTableColumn::create(DbTableColumn::TYPE_JSONB)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('{}')
        ;
    }
}