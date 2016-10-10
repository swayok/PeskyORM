<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableStructure;

class TestingNoPkColumnInTableStructure extends DbTableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'invalid';
    }

    private function not_a_pk() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT);
    }
}