<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORMTest\TestingBaseTable;

class TestingAdminsTableLongAlias extends TestingBaseTable {

    public function getTableAlias() {
        return 'TestingAdminsTableLongAliasReallyLooooooong';
    }

    public function getTableStructure() {
        return TestingAdminsTableStructure::getInstance();
    }

    public function newRecord() {
        return TestingAdmin::newEmptyRecord();
    }
}