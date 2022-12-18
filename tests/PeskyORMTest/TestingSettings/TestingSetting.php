<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Record\Record;

class TestingSetting extends Record
{
    public function __construct()
    {
        parent::__construct(TestingSettingsTable::getInstance());
    }
}