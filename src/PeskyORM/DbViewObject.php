<?php

namespace PeskyORM;
use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbObjectException;

/**
 * Class DbViewObject
 */
class DbViewObject extends DbObject {

    public function save($verifyDbExistance = false, $createIfNotExists = false, $saveRelations = false) {
        throw new DbObjectException($this, 'Saving data to a DB View is impossible');
    }

    protected function saveFiles($fieldNames = null) {
        throw new DbObjectException($this, 'Saving data to a DB View is impossible');
    }

    public function saveUpdates($fieldNames = null) {
        throw new DbObjectException($this, 'Saving data to a DB View is impossible');
    }

    public function delete($resetFields = true, $ignoreIfNotExists = false) {
        throw new DbObjectException($this, 'Deleting data from a DB View is impossible');
    }

}