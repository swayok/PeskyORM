<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableRelation;
use PeskyORM\ORM\DbTableStructure;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure extends DbTableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'some_table';
    }

    private function valid() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey();
    }


    private function InvalidClass() {
        return $this;
    }

    private function InvalidLocalColumnName() {
        return DbTableRelation::create('local_invalid', DbTableRelation::HAS_MANY, TestingAdminsTable::class, 'id');
    }

    private function InvalidForeignColumnName() {
        return DbTableRelation::create('valid', DbTableRelation::HAS_MANY, TestingAdminsTable::class, 'foreign_invalid');
    }

    private function InvalidForeignTableClass() {
        return DbTableRelation::create('valid', DbTableRelation::HAS_MANY, '___class_invalid', 'id');
    }

}