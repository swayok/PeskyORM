<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableStructureInterface;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class TestingAdminsTableLongAlias extends TestingBaseTable
{
    
    public function getTableAlias(): string
    {
        return 'TestingAdminsTableLongAliasReallyLongButWeNeedAtLeast60Characters';
    }
    
    public function getTableStructure(): TableStructureInterface
    {
        return TestingAdminsTableStructure::getInstance();
    }
    
    public function newRecord(): RecordInterface
    {
        return TestingAdmin::newEmptyRecord();
    }
}