<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

class TestingAdmin extends Record {

    /**
     * @return TestingAdminsTable
     */
    static public function getTable() {
        return TestingAdminsTable::getInstance();
    }
}