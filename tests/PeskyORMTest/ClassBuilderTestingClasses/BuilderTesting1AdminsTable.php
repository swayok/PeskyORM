<?php
declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

use PeskyORM\ORM\Table\Table;

class BuilderTesting1AdminsTable extends Table
{
    protected function __construct()
    {
        parent::__construct(
            new BuilderTesting1AdminsTableStructure(),
            BuilderTesting1Admin::class,
            'Admins'
        );
    }
}