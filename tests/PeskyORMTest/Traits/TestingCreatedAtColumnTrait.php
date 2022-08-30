<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait TestingCreatedAtColumnTrait
{
    
    private function created_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP_WITH_TZ)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('now()'));
    }
    
}