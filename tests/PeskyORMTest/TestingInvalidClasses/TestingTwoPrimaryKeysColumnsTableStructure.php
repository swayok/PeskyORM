<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingTwoPrimaryKeysColumnsTableStructure extends TableStructure
{
    
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'invalid';
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