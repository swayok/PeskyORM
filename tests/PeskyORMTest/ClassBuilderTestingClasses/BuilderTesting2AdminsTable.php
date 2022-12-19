<?php
declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class BuilderTesting2AdminsTable extends TestingBaseTable
{
    protected function __construct()
    {
        parent::__construct(
            new BuilderTesting2AdminsTableStructure(),
            BuilderTesting2Admin::class,
            'Admins'
        );
    }
}