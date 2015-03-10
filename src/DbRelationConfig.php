<?php

namespace ORM;


class DbRelationConfig {

    const HAS_ONE = 'one';
    const HAS_MANY = 'many';
    const BELONGS_TO = 'belongs';

    protected $type;

    protected $table;
    protected $column;

    protected $foreignTable;
    protected $foreignColumn;

    /** @var array */
    protected $additionalJoinConditions = array();

    public function __construct($table, $column, $type, $foreignTable, $foreignColumn) {
        $this->type = $type;
        $this->table = $table;
        $this->column = $column;
        $this->foreignTable = $foreignTable;
        $this->foreignColumn = $foreignColumn;
    }

    /**
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param mixed $type
     * @return $this
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @param mixed $table
     * @return $this
     */
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getColumn() {
        return $this->column;
    }

    /**
     * @param mixed $column
     * @return $this
     */
    public function setColumn($column) {
        $this->column = $column;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getForeignTable() {
        return $this->foreignTable;
    }

    /**
     * @param mixed $foreignTable
     * @return $this
     */
    public function setForeignTable($foreignTable) {
        $this->foreignTable = $foreignTable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getForeignColumn() {
        return $this->foreignColumn;
    }

    /**
     * @param mixed $foreignColumn
     * @return $this
     */
    public function setForeignColumn($foreignColumn) {
        $this->foreignColumn = $foreignColumn;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalJoinConditions() {
        return $this->additionalJoinConditions;
    }

    /**
     * @param array $additionalJoinConditions
     * @return $this
     */
    public function setAdditionalJoinConditions($additionalJoinConditions) {
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }



}