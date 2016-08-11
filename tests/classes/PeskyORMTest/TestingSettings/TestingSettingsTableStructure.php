<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORM\ORM\DbTableStructure;

class TestingSettingsTableStructure extends DbTableStructure {

    /**
     * @return string
     */
    static public function getTableName() {
        return 'settings';
    }
}