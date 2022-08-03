<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Table;

class TestingAdminsTable extends Table
{
    public function getTableStructure(): TestingAdminsTableStructure {
        return TestingAdminsTableStructure::getInstance();
    }
    
    public function newRecord(): TestingAdmin
    {
        return TestingAdmin::newEmptyRecord();
    }
    
}