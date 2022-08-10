<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Record;

class TestingSetting extends Record
{
    
    /**
     * @return TestingSettingsTable
     */
    public static function getTable()
    {
        return TestingSettingsTable::getInstance();
    }
}