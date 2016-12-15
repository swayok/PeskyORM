<?php

namespace PeskyORM\ORM;

class FakeTable extends Table {

    protected $tableName;
    protected $tableStructure;

    /**
     * @param string $tableName
     * @param array $columns - key-value array where key is column name and value is column type or
     *      key is int and value is column name
     */
    public function __construct($tableName, array $columns) {
        $this->tableName = $tableName;
        $this->getTableStructure()->setTableColumns($columns);
    }

    /**
     * Table schema description
     * @return FakeTableStructure
     */
    public function getTableStructure() {
        if (!$this->tableStructure) {
            $this->tableStructure = FakeTableStructure::makeNewFakeStructure($this->tableName);
        }
        return $this->tableStructure;
    }

    /**
     * @return Record
     */
    public function newRecord() {
        //todo: make a FakeRecordClass
    }
}