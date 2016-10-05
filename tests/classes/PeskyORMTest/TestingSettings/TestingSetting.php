<?php

namespace PeskyORMTest\TestingSettings;

use PeskyORM\ORM\DbRecord;

class TestingSetting extends DbRecord {

    static protected $tableClass = TestingSettingsTable::class;
}