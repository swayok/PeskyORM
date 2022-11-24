<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\Table;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

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