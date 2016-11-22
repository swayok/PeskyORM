<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingInvalidColumnsInTableStructure extends TableStructure {

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
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }

    private function pk2() {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
}