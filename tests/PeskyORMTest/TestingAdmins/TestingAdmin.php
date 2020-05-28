<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

/**
 * @method $this setId($value, $isFromDb = false)
 */
class TestingAdmin extends Record {

    /**
     * @return TestingAdminsTable
     */
    static public function getTable() {
        return TestingAdminsTable::getInstance();
    }
}