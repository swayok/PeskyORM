<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\Table;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

class TestingAdminsTable extends Table
{
    public function getTableStructure(): TableStructureInterface
    {
        return TestingAdminsTableStructure::getInstance();
    }
    
    public function newRecord(): RecordInterface
    {
        return TestingAdmin::newEmptyRecord();
    }
    
}