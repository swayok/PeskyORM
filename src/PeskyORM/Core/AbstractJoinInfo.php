<?php

namespace PeskyORM\Core;

abstract class AbstractJoinInfo {

    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_INNER = 'inner';

    const NAME_VALIDATION_REGEXP = '%^[A-Z][a-zA-Z0-9]*$%';   //< CamelCase

    /** @var string */
    protected $joinName = null;
    /** @var string */
    protected $tableName = null;
    /** @var string|null */
    protected $tableSchema = null;
    /** @var string|null */
    protected $tableAlias = null;
    /** @var string */
    protected $columnName = null;
    /** @var string */
    protected $joinType = null;
    /** @var string */
    protected $foreignTableName = null;
    /** @var string|null */
    protected $foreignTableSchema = null;
    /** @var string */
    protected $foreignColumnName = null;
    /** @var array */
    protected $additionalJoinConditions = [];
    /** @var array */
    protected $foreignColumnsToSelect = ['*'];

    /**
     * @param string $joinName
     * @throws \InvalidArgumentException
     */
    public function __construct($joinName) {
        $this->setJoinName($joinName);
    }

    /**
     * @param string $joinName
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function create($joinName) {
        return new static($joinName);
    }

    /**
     * @return string
     */
    public function getColumnName() {
        return $this->columnName;
    }

    /**
     * @param string $columnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setColumnName($columnName) {
        if (empty($columnName) || !is_string($columnName)) {
            throw new \InvalidArgumentException('$columnName argument must be a not-empty string');
        }
        $this->columnName = $columnName;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignColumnName() {
        return $this->foreignColumnName;
    }

    /**
     * @param string $foreignColumnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnName($foreignColumnName) {
        if (empty($foreignColumnName) || !is_string($foreignColumnName)) {
            throw new \InvalidArgumentException('$foreignColumnName argument must be a not-empty string');
        }
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTableName() {
        return $this->foreignTableName;
    }

    /**
     * @return null|string
     */
    public function getForeignTableSchema() {
        return $this->foreignTableSchema;
    }

    /**
     * @return string
     */
    public function getJoinName() {
        return $this->joinName;
    }

    /**
     * @param string $joinName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinName($joinName) {
        if (empty($joinName) || !is_string($joinName)) {
            throw new \InvalidArgumentException('$joinName argument must be a not-empty string');
        } else if (!preg_match(static::NAME_VALIDATION_REGEXP, $joinName)) {
            throw new \InvalidArgumentException(
                "\$joinName argument contains invalid value: '$joinName'. Pattern: "
                    . static::NAME_VALIDATION_REGEXP . '. Example: CamelCase1'
            );
        }
        $this->joinName = $joinName;
        return $this;
    }

    /**
     * @return string
     */
    public function getJoinType() {
        return $this->joinType;
    }

    /**
     * @param string $joinType
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinType($joinType) {
        if (empty($joinType) || !is_string($joinType)) {
            throw new \InvalidArgumentException('$joinType argument must be a not-empty string');
        } else if (!in_array($joinType, [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT], true)) {
            throw new \InvalidArgumentException(
                '$joinType argument must be one of: ' . implode(',', [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT])
            );
        }
        $this->joinType = $joinType;
        return $this;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return bool
     */
    protected function hasTableName() {
        return !empty($this->tableName);
    }

    /**
     * @return null|string
     */
    public function getTableSchema() {
        return $this->tableSchema;
    }

    /**
     * @return string
     */
    public function getTableAlias() {
        return $this->tableAlias;
    }

    /**
     * @param string $alias
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableAlias($alias) {
        if (empty($alias) || !is_string($alias)) {
            throw new \InvalidArgumentException('$alias argument must be a not-empty string');
        }
        $this->tableAlias = $alias;
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
    public function setAdditionalJoinConditions(array $additionalJoinConditions) {
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }

    /**
     * @return array
     */
    public function getForeignColumnsToSelect() {
        return $this->foreignColumnsToSelect;
    }

    /**
     * @param array $columns - use '*' or ['*'] to select all columns and empty array to select none
     * @return $this
     */
    public function setForeignColumnsToSelect(...$columns) {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $this->foreignColumnsToSelect = $columns;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return (
            $this->hasTableName()
            && !empty($this->getColumnName())
            && !empty($this->getForeignTableName())
            && !empty($this->getForeignColumnName())
            && !empty($this->getJoinType())
            && !empty($this->getJoinName())
        );
    }

}