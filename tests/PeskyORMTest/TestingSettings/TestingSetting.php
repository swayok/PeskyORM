<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\TableInterface;

class TestingSetting extends Record
{
    
    /**
     * @return TableInterface
     */
    public static function getTable(): TableInterface
    {
        return TestingSettingsTable::getInstance();
    }
}