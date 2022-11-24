<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\TableInterface;

class TestingAdmin3 extends Record
{
    
    public static function getTable(): TableInterface
    {
        return TestingAdmins3Table::getInstance();
    }

    public function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, bool $isUpdate): void
    {
        parent::runColumnSavingExtenders($columnsToSave, $dataSavedToDb, $updatesReceivedFromDb, $isUpdate);
    }
    
}