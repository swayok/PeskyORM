<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\TableStructureInterface;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class TestingAdmins3Table extends TestingBaseTable
{
    
    public function getTableStructure(): TableStructureInterface
    {
        return TestingAdmins3TableStructure::getInstance();
    }
    
    
}