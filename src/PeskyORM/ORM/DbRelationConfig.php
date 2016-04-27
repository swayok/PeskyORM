<?php

namespace PeskyORM\ORM;

class DbRelationConfig {

    const HAS_ONE = DbJoinConfig::HAS_ONE;
    const HAS_MANY = DbJoinConfig::HAS_MANY;
    const BELONGS_TO = DbJoinConfig::BELONGS_TO;

    const JOIN_LEFT = DbJoinConfig::JOIN_LEFT;
    const JOIN_RIGHT = DbJoinConfig::JOIN_RIGHT;
    const JOIN_INNER = DbJoinConfig::JOIN_INNER;

    /** @var DbTableStructure */
    protected $localTableSchema;

    /** @var string */
    protected $id;
    /** @var string */
    protected $type;
    /** @var  */
    protected $joinType = self::JOIN_LEFT;

    /** @var string */
    protected $localTableName;
    /** @var string */
    protected $localColumn;

    /** @var DbTable */
    protected $foreignTable;
    /** @var string */
    protected $foreignTableName;
    /** @var string */
    protected $foreignColumn;

    /** @var string */
    protected $displayField;

    /** @var array */
    protected $additionalJoinConditions = [];

    /**
     * @param DbTableStructure $localTableSchema
     * @param string $localColumn
     * @param string $type
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return DbRelationConfig
     */
    static public function create(
        DbTableStructure $localTableSchema,
        $localColumn,
        $type,
        $foreignTableName,
        $foreignColumn
    ) {
        return new DbRelationConfig($localTableSchema, $localColumn, $type, $foreignTableName, $foreignColumn);
    }

    /**
     * @param DbTableStructure $localTableSchema
     * @param string $localColumn
     * @param string $type
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return DbRelationConfig
     */
    public function __construct(
        DbTableStructure $localTableSchema,
        $localColumn,
        $type,
        $foreignTableName,
        $foreignColumn
    ) {
        $this->localTableSchema = $localTableSchema;
        $this->localTableName = $localTableSchema->getName();
        $this->localColumn = $localColumn;
        $this->type = $type;
        $this->foreignTableName = $foreignTableName;
        $this->foreignColumn = $foreignColumn;
        $this->displayField = $localColumn;
        $this->id = "{$this->localTableName}.{$this->localColumn} {$this->type} {$this->foreignTableName}.{$this->foreignColumn}";
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
    public function getLocalTableName() {
        return $this->localTableName;
    }

    /**
     * @param string $localTableName
     * @return $this
     */
    public function setLocalTableName($localTableName) {
        $this->localTableName = $localTableName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocalColumn() {
        return $this->localColumn;
    }

    /**
     * @param string $localColumn
     * @return $this
     */
    public function setLocalColumn($localColumn) {
        $this->localColumn = $localColumn;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTableName() {
        return $this->foreignTableName;
    }

    /**
     * @param string $foreignTableName
     * @return $this
     */
    public function setForeignTableName($foreignTableName) {
        $this->foreignTableName = $foreignTableName;
        return $this;
    }

    /**
     * @return DbTableInterface
     */
    public function getForeignTable() {
        if ($this->foreignTable === null) {
            $this->foreignTable = DbClassesManager::getTableInstance($this->foreignTableName);
        }
        return $this->foreignTable;
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
     * @return DbTableStructure
     */
    public function getDbTableSchema() {
        return $this->localTableSchema;
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

}