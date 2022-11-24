<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\Column;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingTwoPrimaryKeysColumnsTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'invalid';
    }
    
    private function pk1(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
    
    private function pk2(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey();
    }
}