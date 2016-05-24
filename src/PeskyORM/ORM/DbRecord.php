<?php

namespace PeskyORM\ORM;

abstract class DbRecord {

    public function hasPrimaryKeyValue() {
        return false;
    }

    public function existsInDb() {
        return $this->hasPrimaryKeyValue() && $this->getPrimaryKeyValue()->isItFromDb();
    }

    public function getPrimaryKeyValue() {
        return DbRecordValue::create(DbTableColumn::create('', 'id'), $this);
    }

    /**
     * @return DbTable
     */
    static public function getTable() {

    }

}