<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingSettings;

use PeskyORM\ORM\Table\Table;

class TestingSettingsTable extends Table
{
    protected function __construct()
    {
        parent::__construct(
            new TestingSettingsTableStructure(),
            TestingSetting::class,
        );
    }
}