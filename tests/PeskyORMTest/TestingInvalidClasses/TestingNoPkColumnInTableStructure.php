<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingNoPkColumnInTableStructure extends TableStructure
{
    
    static public function getTableName(): string
    {
        return 'invalid';
    }
    
    private function not_a_pk()
    {
        return Column::create(Column::TYPE_INT);
    }
}