<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORMTest\TestingBaseDbTable;

class TestingSettingsTable extends TestingBaseDbTable {

    /**
     * Table Name
     * @return string
     */
    static public function getTableName() {
        return 'settings';
    }
}