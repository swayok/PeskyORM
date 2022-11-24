<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\Column;

trait TestingIdColumnTrait
{
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->disallowsNullValues();
    }
}