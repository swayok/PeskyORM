<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\ORM\Column;

trait TestingIdColumnTrait
{
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->disallowsNullValues();
    }
}