<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingAdmins4TableStructure extends TableStructure
{
    
    protected static bool $autodetectColumns = true;
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    private function updated_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }
    
    private function Parent(): Relation
    {
        return Relation::create('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }
    
}