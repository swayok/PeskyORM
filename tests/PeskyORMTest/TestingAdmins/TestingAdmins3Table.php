<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class TestingAdmins3Table extends TestingBaseTable
{
    
    public function getTableStructure(): TableStructureInterface
    {
        return TestingAdmins3TableStructure::getInstance();
    }
    
    
}