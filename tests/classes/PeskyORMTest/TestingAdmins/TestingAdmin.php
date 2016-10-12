<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\DbRecord;

class TestingAdmin extends DbRecord {

    /**
     * @return TestingAdminsTable
     */
    static public function getTable() {
        return TestingAdminsTable::getInstance();
    }
}