<?php

namespace Tests\PeskyORMTest\Traits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait TestingTimestampColumnsTrait
{
    
    private function created_at()
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
    
    private function updated_at()
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
}