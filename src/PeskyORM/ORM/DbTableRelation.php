<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbJoinConfig;

class DbTableRelation {

    const HAS_ONE = 'has_one';
    const HAS_MANY = 'has_many';
    const BELONGS_TO = 'belongs_to';

    const JOIN_LEFT = DbJoinConfig::JOIN_LEFT;
    const JOIN_RIGHT = DbJoinConfig::JOIN_RIGHT;
    const JOIN_INNER = DbJoinConfig::JOIN_INNER;

    /** @var DbTableStructure */
    protected $localTableStructure;

    /** @var string */
    protected $name;
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
    protected $foreignTableClass;
    /** @var string */
    protected $foreignColumn;

    /** @var string */
    protected $displayColumnName;

    /** @var array */
    protected $additionalJoinConditions = [];

    /**
     * @param DbTableStructure $localTableStructure
     * @param string $localColumn
     * @param string $type
     * @param string $foreignTableClass
     * @param string $foreignColumn
     * @return DbTableRelation
     */
    static public function create(
        DbTableStructure $localTableStructure,
        $localColumn,
        $type,
        $foreignTableClass,
        $foreignColumn
    ) {
        return new DbTableRelation($localTableStructure, $localColumn, $type, $foreignTableClass, $foreignColumn);
    }

    /**
     * @param DbTableStructure $localTableStructure
     * @param string $localColumn
     * @param string $type
     * @param string $foreignTableClass
     * @param string $foreignColumn
     */
    public function __construct(
        DbTableStructure $localTableStructure,
        $localColumn,
        $type,
        $foreignTableClass,
        $foreignColumn
    ) {
        $this->localTableStructure = $localTableStructure;
        $this->localTableName = $localTableStructure->getName();
        $this->localColumn = $localColumn;
        $this->type = $type;
        $this->foreignTableClass = $foreignTableClass;
        $this->foreignColumn = $foreignColumn;
        $this->displayColumnName = $localColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     * @throws \BadMethodCallException
     */
    public function getName() {
        if (empty($this->name)) {
            throw new \BadMethodCallException('Relation name is not set');
        }
        return $this->name;
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
    public function getForeignTableClass() {
        return $this->foreignTableClass;
    }

    /**
     * @param string $foreignTableClass
     * @return $this
     */
    public function setForeignTableClass($foreignTableClass) {
        $this->foreignTableClass = $foreignTableClass;
        return $this;
    }

    /**
     * @return DbTableInterface
     * @throws \BadMethodCallException
     */
    public function getForeignTable() {
        if ($this->foreignTable === null) {
            $this->foreignTable = call_user_func([$this->foreignTableClass, 'getInstance']);
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
    public function getDbTableStructure() {
        return $this->localTableStructure;
    }

    /**
     * @return string
     */
    public function getDisplayColumnName() {
        return $this->displayColumnName;
    }

    /**
     * @param string $columnName
     * @return $this
     */
    public function setDisplayColumnName($columnName) {
        $this->displayColumnName = $columnName;
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