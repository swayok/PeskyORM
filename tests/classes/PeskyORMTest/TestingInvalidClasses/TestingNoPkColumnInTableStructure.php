<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingNoPkColumnInTableStructure extends TableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'invalid';
    }

    private function not_a_pk() {
        return Column::create(Column::TYPE_INT);
    }
}