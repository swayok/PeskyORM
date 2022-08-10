<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class TestingAdminsTableLongAlias extends TestingBaseTable
{
    
    public function getTableAlias(): string
    {
        return 'TestingAdminsTableLongAliasReallyLooooooong';
    }
    
    public function getTableStructure()
    {
        return TestingAdminsTableStructure::getInstance();
    }
    
    public function newRecord()
    {
        return TestingAdmin::newEmptyRecord();
    }
}