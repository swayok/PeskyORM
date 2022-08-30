<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

class TestingNoPkColumnInTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'invalid';
    }
    
    private function not_a_pk(): Column
    {
        return Column::create(Column::TYPE_INT);
    }
}