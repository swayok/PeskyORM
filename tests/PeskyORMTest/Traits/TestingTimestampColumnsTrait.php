<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\Column;

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