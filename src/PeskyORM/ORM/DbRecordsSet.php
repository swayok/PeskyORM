<?php

namespace PeskyORM\ORM;

class DbRecordsSet {

    /**
     * @var DbTable
     */
    protected $table;

    /**
     * @var DbSelect
     */
    protected $select;
    /**
     * @var array
     */
    protected $records = null;

    /**
     * @param DbTable $table
     * @param DbSelect|array $records
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function createFromArray(DbTable $table, array $records) {
        return new static($table, $records);
    }

    /**
     * @param DbSelect $dbSelect
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function createFromDbSelect(DbSelect $dbSelect) {
        return new static($dbSelect->getTable(), $dbSelect);
    }

    /**
     * @param DbTable $table
     * @param DbSelect $dbSelectOrRecords
     * @throws \InvalidArgumentException
     */
    protected function __construct(DbTable $table, $dbSelectOrRecords) {
        $this->table = $table;
        if ($dbSelectOrRecords instanceof DbSelect) {
            $this->select = $dbSelectOrRecords;
        } else {
            $this->records = $dbSelectOrRecords;
        }
    }


}