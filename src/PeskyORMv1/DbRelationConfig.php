<?php

namespace PeskyORM;

class DbRelationConfig {

    const HAS_ONE = DbJoinConfig::HAS_ONE;
    const HAS_MANY = DbJoinConfig::HAS_MANY;
    const BELONGS_TO = DbJoinConfig::BELONGS_TO;

    const JOIN_LEFT = DbJoinConfig::JOIN_LEFT;
    const JOIN_RIGHT = DbJoinConfig::JOIN_RIGHT;
    const JOIN_INNER = DbJoinConfig::JOIN_INNER;

    /** @var DbTableConfig */
    protected $dbTableConfig;

    /** @var string */
    protected $id;
    /** @var string */
    protected $type;
    /** @var  */
    protected $joinType = self::JOIN_LEFT;

    /** @var string */
    protected $table;
    /** @var string */
    protected $column;

    /** @var string */
    protected $foreignTable;
    /** @var string */
    protected $foreignColumn;

    /** @var string */
    protected $displayField;

    /** @var array */
    protected $additionalJoinConditions = array();

    /** @var array */
    protected $customData = array();

    /**
     * @param DbTableConfig $dbTableConfig
     * @param string $column
     * @param string $type
     * @param string $foreignTable
     * @param string $foreignColumn
     * @return DbRelationConfig
     */
    static public function create(DbTableConfig $dbTableConfig, $column, $type, $foreignTable, $foreignColumn) {
        return new DbRelationConfig($dbTableConfig, $column, $type, $foreignTable, $foreignColumn);
    }

    /**
     * @param DbTableConfig $dbTableConfig
     * @param string $column
     * @param string $type
     * @param string $foreignTable
     * @param string $foreignColumn
     * @return DbRelationConfig
     */
    public function __construct(DbTableConfig $dbTableConfig, $column, $type, $foreignTable, $foreignColumn) {
        $this->dbTableConfig = $dbTableConfig;
        $this->table = $dbTableConfig->getName();
        $this->column = $column;
        $this->type = $type;
        $this->foreignTable = $foreignTable;
        $this->foreignColumn = $foreignColumn;
        $this->displayField = $column;
        $this->id = "{$this->table}.{$this->column} {$this->type} {$this->foreignTable}.{$this->foreignColumn}";
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * @return string
     */
    public function getColumn() {
        return $this->column;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function setColumn($column) {
        $this->column = $column;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTable() {
        return $this->foreignTable;
    }

    /**
     * @param string $foreignTable
     * @return $this
     */
    public function setForeignTable($foreignTable) {
        $this->foreignTable = $foreignTable;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignColumn() {
        return $this->foreignColumn;
    }

    /**
     * @param string $foreignColumn
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

    /**
     * @return DbTableConfig
     */
    public function getDbTableConfig() {
        return $this->dbTableConfig;
    }

    /**
     * @return string
     */
    public function getDisplayField() {
        return $this->displayField;
    }

    /**
     * @param string $displayField
     * @return $this
     */
    public function setDisplayField($displayField) {
        $this->displayField = $displayField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getJoinType() {
        return $this->joinType;
    }

    /**
     * @param mixed $joinType
     * @return $this
     */
    public function setJoinType($joinType) {
        $this->joinType = $joinType;
        return $this;
    }

    /**
     * @param null|string $key
     * @return array|null
     */
    public function getCustomData($key = null) {
        if (empty($key)) {
            return $this->customData;
        } else if (is_array($this->customData) && array_key_exists($key, $this->customData)) {
            return $this->customData[$key];
        } else {
            return null;
        }
    }

    /**
     * @param array $customData
     * @return $this
     */
    public function setCustomData($customData) {
        $this->customData = $customData;
        return $this;
    }



}