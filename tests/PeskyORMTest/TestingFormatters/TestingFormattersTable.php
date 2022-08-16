<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructureInterface;

class TestingFormattersTable extends Table
{
    public function getTableStructure(): TableStructureInterface
    {
        return TestingFormattersTableStructure::getInstance();
    }
    
    public function newRecord(): RecordInterface
    {
        return TestingFormatter::newEmptyRecord();
    }
    
}