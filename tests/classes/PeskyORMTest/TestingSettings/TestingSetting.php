<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORM\ORM\DbRecord;

class TestingSetting extends DbRecord {

    /**
     * @return TestingSettingsTable
     */
    static public function getTable() {
        return TestingSettingsTable::getInstance();
    }
}