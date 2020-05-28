<?php

namespace Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Record;

class TestingSetting extends Record {

    /**
     * @return TestingSettingsTable
     */
    static public function getTable() {
        return TestingSettingsTable::getInstance();
    }
}