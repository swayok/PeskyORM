<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableStructure;

class TestingInvalidColumnsInTableStructure extends DbTableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'invalid';
    }

    private function invalid() {
        return $this;
    }

    private function pk1() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey();
    }

    private function pk2() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey();
    }
}