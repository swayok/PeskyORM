<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructureInterface;

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