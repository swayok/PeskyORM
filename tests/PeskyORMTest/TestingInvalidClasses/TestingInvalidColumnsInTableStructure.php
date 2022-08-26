<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingInvalidColumnsInTableStructure extends TableStructure
{
    
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'invalid';
    }
    
    private function invalid()
    {
        return $this;
    }
    
    private function pk1()
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
    
    private function pk2()
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
}