<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\TableInterface;

class TestingAdmin3 extends Record
{
    
    /**
     * @return TableInterface
     */
    public static function getTable(): TableInterface
    {
        return TestingAdmins3Table::getInstance();
    }

    public function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, bool $isUpdate)
    {
        parent::runColumnSavingExtenders($columnsToSave, $dataSavedToDb, $updatesReceivedFromDb, $isUpdate);
    }
    
}