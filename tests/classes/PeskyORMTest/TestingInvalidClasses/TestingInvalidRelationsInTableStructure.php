<?php

namespace PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TestingInvalidRelationsInTableStructure extends TableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'some_table';
    }

    private function valid() {
        return Column::create(Column::TYPE_INT)
            ->itIsPrimaryKey();
    }


    private function InvalidClass() {
        return $this;
    }

    private function InvalidLocalColumnName() {
        return Relation::create('local_invalid', Relation::HAS_MANY, TestingAdminsTable::class, 'id');
    }

    private function InvalidForeignColumnName() {
        return Relation::create('valid', Relation::HAS_MANY, TestingAdminsTable::class, 'foreign_invalid');
    }

    private function InvalidForeignTableClass() {
        return Relation::create('valid', Relation::HAS_MANY, '___class_invalid', 'id');
    }

}