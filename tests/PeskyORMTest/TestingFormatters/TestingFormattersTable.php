<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use PeskyORM\ORM\Table;

class TestingFormattersTable extends Table
{
    public function getTableStructure(): TestingFormattersTableStructure {
        return TestingFormattersTableStructure::getInstance();
    }
    
    public function newRecord(): TestingFormatter
    {
        return TestingFormatter::newEmptyRecord();
    }
    
}