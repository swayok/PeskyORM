<?php /** @noinspection ALL */
declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

class BuilderTesting1AdminsTable extends \PeskyORM\ORM\Table\Table
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