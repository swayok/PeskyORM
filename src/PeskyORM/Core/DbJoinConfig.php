<?php

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;

class DbJoinConfig {

    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_INNER = 'inner';

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
     * @param string $joinName
     * @param string $tableName
     * @param string $column
     * @param string $joinType
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return DbJoinConfig
     * @throws \InvalidArgumentException
     */
    static public function construct($joinName, $tableName, $column, $joinType, $foreignTableName, $foreignColumn) {
        return self::create($joinName)
            ->setConfigForLocalTable($tableName, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignTableName, $foreignColumn);
    }

    /**
     * @param string $tableName
     * @param string $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable($tableName, $column) {
        return $this->setTableName($tableName)->setColumnName($column);
    }

    /**
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable($foreignTableName, $foreignColumn) {
        return $this->setForeignTableName($foreignTableName)->setForeignColumnName($foreignColumn);
    }

    /**
     * @return null|string
     */
    public function getColumnName() {
        return $this->columnName;
    }

    /**
     * @param null|string $columnName
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
     * @return null|string
     */
    public function getForeignColumnName() {
        return $this->foreignColumnName;
    }

    /**
     * @param null|string $foreignColumnName
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
     * @param string $foreignTableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableName($foreignTableName) {
        if (empty($foreignTableName) || !is_string($foreignTableName)) {
            throw new \InvalidArgumentException('$foreignTableName argument must be a not-empty string');
        }
        $this->foreignTableName = $foreignTableName;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getForeignTableSchema() {
        return $this->foreignTableSchema;
    }

    /**
     * @param null|string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableSchema($schema) {
        if ($schema !== null && (!is_string($schema) || empty($schema)) ) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->foreignTableSchema = $schema;
        return $this;
    }

    /**
     * @return null|string
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
     * @param string $tableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableName($tableName) {
        if (empty($tableName) || !is_string($tableName)) {
            throw new \InvalidArgumentException('$tableName argument must be a not-empty string');
        }
        $this->tableName = $tableName;
        if ($this->tableAlias === null) {
            $this->setTableAlias(StringUtils::classify($this->tableName));
        }
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTableSchema() {
        return $this->tableSchema;
    }

    /**
     * @param null|string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableSchema($schema) {
        if ($schema !== null && (!is_string($schema) || empty($schema)) ) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->tableSchema = $schema;
        return $this;
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
     * @return array|string
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
            !empty($this->getTableName())
            && !empty($this->getColumnName())
            && !empty($this->getForeignTableName())
            && !empty($this->getForeignColumnName())
            && !empty($this->getJoinType())
            && !empty($this->getJoinName())
        );
    }

}