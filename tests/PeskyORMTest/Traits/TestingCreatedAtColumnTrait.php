<?php

namespace Tests\PeskyORMTest\Traits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait TestingCreatedAtColumnTrait
{
    
    private function created_at()
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
    
}