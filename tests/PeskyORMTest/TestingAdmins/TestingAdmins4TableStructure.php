<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingAdmins4TableStructure extends TableStructure
{
    protected bool $autodetectColumns = true;
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    private function updated_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }

    private function Parent(): Relation
    {
        return new Relation('parent_id', Relation::BELONGS_TO, TestingAdminsTable::getInstance(), 'id');
    }
    
}