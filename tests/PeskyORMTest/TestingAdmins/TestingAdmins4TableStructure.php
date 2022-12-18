<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingAdmins4TableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'admins';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(
            (new TimestampColumn('updated_at'))
                ->setValueAutoUpdater(function () {
                    return DbExpr::create('NOW()');
                })
        );
        $this->importMissingColumnsConfigsFromDbTableDescription();
    }

    protected function registerRelations(): void
    {
        $this->addRelation(
            new Relation(
                'parent_id',
                Relation::BELONGS_TO,
                TestingAdminsTable::class,
                'id',
                'Parent'
            )
        );
    }
}