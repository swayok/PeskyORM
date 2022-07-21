<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

class TestingAdmin3 extends Record
{
    
    /**
     * @return TestingAdmins3Table
     */
    static public function getTable()
    {
        return TestingAdmins3Table::getInstance();
    }
    
}