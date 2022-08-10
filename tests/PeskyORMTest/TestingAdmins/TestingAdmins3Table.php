<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class TestingAdmins3Table extends TestingBaseTable
{
    
    public function getTableStructure()
    {
        return TestingAdmins3TableStructure::getInstance();
    }
    
    
}