<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\DbRecord;

class TestingAdmin2 extends DbRecord {

    /**
     * @return TestingAdminsTable
     */
    static public function getTable() {
        return TestingAdminsTable::getInstance();
    }

    protected function beforeSave(array $columnsToSave, array $data, $isUpdate) {
        if ($isUpdate) {
            return ['login' => ['error']];
        }
        return [];
    }

    protected function afterSave($isCreated) {
        throw new \BadMethodCallException('after: no-no-no!');
    }


}