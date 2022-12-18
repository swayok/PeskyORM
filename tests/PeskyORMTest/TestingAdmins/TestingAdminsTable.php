<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Table\Table;

class TestingAdminsTable extends Table
{
    protected function __construct()
    {
        parent::__construct(
            new TestingAdminsTableStructure(),
            TestingAdmin::class
        );
    }
}