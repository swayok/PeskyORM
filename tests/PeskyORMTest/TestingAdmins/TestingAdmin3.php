<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

class TestingAdmin3 extends Record
{
    
    /**
     * @return TestingAdmins3Table
     */
    static public function getTable()
    {
        return TestingAdmins3Table::getInstance();
    }

    public function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, bool $isUpdate)
    {
        parent::runColumnSavingExtenders($columnsToSave, $dataSavedToDb, $updatesReceivedFromDb, $isUpdate);
    }
    
}