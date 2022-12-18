<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Table\Table;

class TestingAdmins3Table extends Table
{
    protected function __construct()
    {
        parent::__construct(
            new TestingAdmins3TableStructure(),
            TestingAdmin::class
        );
    }
}