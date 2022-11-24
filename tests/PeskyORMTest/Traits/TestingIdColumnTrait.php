<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;

trait TestingIdColumnTrait
{
    
    private function id(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey()
            ->disallowsNullValues();
    }
}