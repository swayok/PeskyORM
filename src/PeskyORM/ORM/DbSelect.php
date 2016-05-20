<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class DbSelect {

    /**
     * Main table name to select data from
     * @var string
     */
    protected $tableName;

    /**
     * @var DbTable
     */
    protected $table;

    /**
     * @var DbTableStructure
     */
    protected $tableStructure;

    /**
     * @param string $tableName
     * @return $this
     */
    static public function from($tableName) {
        return new static($tableName);
    }

    /**
     * @param string|DbTable $table - table name or DbTable object
     */
    public function __construct($table) {
        if ($table instanceof DbTable) {
            $this->tableName = $table->getTableName();
            $this->table = $table;
            $this->tableStructure = $table->getStructure();
        } else {
            $this->tableName = $table;
            $this->table = DbClassesManager::getTableInstance($table);
            $this->tableStructure = $this->table->getStructure();
        }
    }

    /**
     * @param string|array $columns
     * @return $this
     */
    public function columns($columns) {
        return $this;
    }

    /**
     * Build query from passed array
     * @param array $conditionsAndOptions
     * @return $this
     */
    public function parseArray(array $conditionsAndOptions) {
        $conditionsAndOptions = $this->prepareSelect($conditionsAndOptions);
        // todo: config query from $conditionsAndOptions (don't forget about 'FIELDS' key)
        return $this;
    }

    /**
     * @return array
     */
    public function fetchOne() {
        // todo: protect from empty conditions
        return $this->_fetch(Utils::FETCH_FIRST);
    }

    /**
     * @return DbRecord
     */
    public function fetchOneAsDbRecord() {
        return DbClassesManager::newRecord($this->tableName)->fromDbData($this->fetchOne());
    }

    /**
     * @return array
     */
    public function fetchMany() {
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @return array
     */
    public function fetchNextPage() {
        // todo: analyze LIMIT and OFFSET and update OFFSET to fetch next pack of data
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public function fetchCount($ignoreLeftJoins = true) {
        return (int) $this->table->getConnection()->query($this->buildCountQuery($ignoreLeftJoins), Utils::FETCH_VALUE);
    }

    /**
     * @return array
     */
    public function fetchColumn() {
        return $this->_fetch(Utils::FETCH_COLUMN);
    }

    /**
     * @param string $keysColumn
     * @param string $valuesColumn
     * @return array
     */
    public function fetchAssoc($keysColumn, $valuesColumn) {
        $this->columns(['key' => $keysColumn, 'value' => $valuesColumn]);
        /** @var array $records */
        $records = $this->_fetch(Utils::FETCH_ALL);
        $assoc = [];
        foreach ($records as $data) {
            $assoc[$data[$keysColumn]] = $data[$valuesColumn];
        }
        return $assoc;
    }

    /**
     * @param DbExpr $expression
     * @return string
     * @throws \InvalidArgumentException
     */
    public function fetchValue(DbExpr $expression) {
        return $this->columns([$expression])
            ->_fetch(Utils::FETCH_VALUE);
    }

    /**
     * @param string $selectionType - one of PeskyORM\Core\Utils::FETCH_*
     * @return array|string
     */
    protected function _fetch($selectionType) {
        $data = $this->table->getConnection()->query($this->buildQuery(), $selectionType);
        return $data;
    }

    /**
     * @return string
     */
    public function buildQuery() {
        $query = 'SELECT ';
        return $query;
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return string
     */
    public function buildCountQuery($ignoreLeftJoins = true) {
        // todo: remove ORDER, LIMIT, OFFSET, LEFT JOINs
        // todo: remove LEFT JOINs if $ignoreLeftJoins === true
        // todo: SELECT COUNT(*) FROM ....
        $query = 'SELECT ';
        return $query;
    }

    /**
     * @return string
     */
    public function getSelectionType() {
        return $this->selectionType;
    }

    /**
     * @return DbTable
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return DbTableStructure
     */
    public function getTableStructure() {
        return $this->tableStructure;
    }
    
    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $conditionsAndOptions
     * @return mixed $where
     */
    public function resolveContains(array $conditionsAndOptions) {
        if (is_array($conditionsAndOptions)) {
            if (!empty($conditionsAndOptions['CONTAIN'])) {
                if (!is_array($conditionsAndOptions['CONTAIN'])) {
                    $conditionsAndOptions['CONTAIN'] = [$conditionsAndOptions['CONTAIN']];
                }
                $conditionsAndOptions['JOIN'] = [];

                foreach ($conditionsAndOptions['CONTAIN'] as $alias => $columns) {
                    if (is_int($alias)) {
                        $alias = $columns;
                        $columns = !empty($relation['fields']) ? $relation['fields'] : '*';
                    }
                    $relationConfig = $this->getTableRealtaion($alias);
                    if ($relationConfig->getType() === DbTableRelation::HAS_MANY) {
                        throw new DbModelException($this, "Queries with one-to-many joins are not allowed via 'CONTAIN' key");
                    } else {
                        $model = $this->getRelatedModel($alias);
                        $additionalConditions = $relationConfig->getAdditionalJoinConditions();
                        $joinType = $relationConfig->getJoinType();
                        if (is_array($columns)) {
                            if (isset($columns['TYPE'])) {
                                $joinType = $columns['TYPE'];
                            }
                            unset($columns['TYPE']);
                            if (isset($columns['CONDITIONS'])) {
                                $additionalConditions = array_replace_recursive($additionalConditions, $columns['CONDITIONS']);
                            }
                            unset($columns['CONDITIONS']);
                            if (!empty($columns['CONTAIN'])) {
                                $subContains = ['CONTAIN' => $columns['CONTAIN']];
                            }
                            unset($columns['CONTAIN']);
                            if (empty($columns)) {
                                $columns = '*';
                            }
                        }

                        $conditionsAndOptions['JOIN'][$alias] = DbJoinConfig::create($alias)
                            ->setConfigForLocalTable($this, $relationConfig->getColumn())
                            ->setJoinType($joinType)
                            ->setConfigForForeignTable($model, $relationConfig->getForeignColumn())
                            ->setAdditionalJoinConditions($additionalConditions)
                            ->setForeignColumnsToSelect($columns)
                            ->getConfigsForDbQuery();

                        if (!empty($subContains)) {
                            $subJoins = $model->resolveContains($subContains);
                            $conditionsAndOptions['JOIN'] = array_merge($conditionsAndOptions['JOIN'], $subJoins['JOIN']);
                        }
                    }
                }
                if (empty($conditionsAndOptions['JOIN'])) {
                    unset($conditionsAndOptions['JOIN']);
                }
            }
            unset($conditionsAndOptions['CONTAIN']);
        }
        return $conditionsAndOptions;
    }

    /**
     * Add columns into options and resolve contains
     * @param mixed $options
     * @return array|mixed
     */
    protected function prepareSelect($options) {
        if (!is_array($options)) {
            if (!empty($options) && is_string($options)) {
                $options = [$options];
            } else {
                $options = [];
            }
        } else {
            $options = $this->resolveContains($options);
        }
        return $options;
    }

    protected function validate() {
        return $this;
    }
    
}