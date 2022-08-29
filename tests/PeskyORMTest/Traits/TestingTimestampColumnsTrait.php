<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait TestingTimestampColumnsTrait
{
    
    private function created_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
    
    private function updated_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
}