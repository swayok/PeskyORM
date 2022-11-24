<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingTwoPrimaryKeysColumnsTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'invalid';
    }
    
    private function pk1(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey();
    }
    
    private function pk2(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey();
    }
}