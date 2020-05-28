<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

/**
 * @method $this setId($value, $isFromDb = false)
 * @method $this setParent($value, $isFromDb = false)
 * @method $this setChildren($value, $isFromDb = false)
 *
 * @property Record $Parent
 */
class TestingAdmin extends Record {

    /**
     * @return TestingAdminsTable
     */
    static public function getTable() {
        return TestingAdminsTable::getInstance();
    }
}