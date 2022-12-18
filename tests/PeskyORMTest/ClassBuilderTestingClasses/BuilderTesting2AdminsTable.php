<?php /** @noinspection ALL */
declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

class BuilderTesting2AdminsTable extends \PeskyORM\Tests\PeskyORMTest\TestingBaseTable
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