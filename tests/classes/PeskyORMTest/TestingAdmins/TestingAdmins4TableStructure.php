<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;

class TestingAdmins4TableStructure extends TableStructure {

    static protected $autodetectColumnConfigs = true;

    static public function getTableName() {
        return 'admins';
    }

    private function updated_at() {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }

    private function Parent() {
        return Relation::create('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }

}