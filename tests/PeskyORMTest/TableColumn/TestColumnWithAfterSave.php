<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TableColumn;

use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;

class TestColumnWithAfterSave extends StringColumn
{
    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate
    ): RecordValueContainerInterface {
        if ($isUpdate) {
            throw new \UnexpectedValueException('login: update!');
        }
        return $valueContainer;
    }
}