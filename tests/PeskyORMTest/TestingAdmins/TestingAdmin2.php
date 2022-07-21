<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

class TestingAdmin2 extends Record
{
    
    /**
     * @return TestingAdminsTable
     */
    static public function getTable()
    {
        return TestingAdminsTable::getInstance();
    }
    
    protected function beforeSave(array $columnsToSave, array $data, bool $isUpdate)
    {
        if ($isUpdate) {
            return ['login' => ['error']];
        }
        return [];
    }
    
    protected function afterSave(bool $isCreated, array $updatedColumns = [])
    {
        throw new \BadMethodCallException('after: no-no-no!');
    }
    
    protected function beforeDelete()
    {
        if ($this->getPrimaryKeyValue() !== 0) {
            throw new \BadMethodCallException('before delete: no-no-no!');
        }
    }
    
    protected function afterDelete()
    {
        throw new \BadMethodCallException('after delete: no-no-no!');
    }
    
    
}
