<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\DbRecord;

class TestingAdmin3 extends DbRecord {

    /**
     * @return TestingAdmins3Table
     */
    static public function getTable() {
        return TestingAdmins3Table::getInstance();
    }

}