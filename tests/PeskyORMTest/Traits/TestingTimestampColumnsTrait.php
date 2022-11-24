<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;

trait TestingTimestampColumnsTrait
{
    
    private function created_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
    
    private function updated_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
}