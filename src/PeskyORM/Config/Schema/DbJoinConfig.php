<?php

namespace PeskyORM\Config\Schema;

use PeskyORM\Exception\DbQueryException;
use PeskyORM\Exception\DbUtilsException;

class DbJoinConfig {

    const HAS_ONE = 'has_one';
    const HAS_MANY = 'has_many';
    const BELONGS_TO = 'belongs_to';

    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_INNER = 'inner';

    /** @var string|null */
    protected $joinAlias = null;
    /** @var DbModel|null */
    protected $model = null;
    /** @var string|null */
    protected $column = null;
    /** @var string|null */
    protected $joinType = null;
    /** @var DbModel|null */
    protected $foreignModel = null;
    /** @var string|null */
    protected $foreignColumn = null;
    /** @var array */
    protected $additionalJoinConditions = [];
    /** @var array|string */
    protected $foreignColumnsToSelect = '*';

    /**
     * DbJoinConfig constructor.
     * @param string $joinAlias
     */
    public function __construct($joinAlias) {
        $this->joinAlias = $joinAlias;
    }

    static public function create($joinAlias) {
        return new DbJoinConfig($joinAlias);
    }

    /**
     * @param string $joinAlias
     * @param DbModel $model
     * @param string $column
     * @param string $joinType
     * @param string $foreignModelAlias
     * @param string $foreignColumn
     * @return DbJoinConfig
     */
    static public function construct($joinAlias, DbModel $model, $column, $joinType, DbModel $foreignModel, $foreignColumn) {
        return self::create($joinAlias)
            ->setConfigForLocalTable($model, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignModel, $foreignColumn);
    }

    /**
     * @param DbModel $model
     * @param string $column
     * @return $this
     */
    public function setConfigForLocalTable(DbModel $model, $column) {
        return $this->setModel($model)->setColumn($column);
    }

    /**
     * @param DbModel $foreignModel
     * @param string $foreignColumn
     * @return $this
     */
    public function setConfigForForeignTable(DbModel $foreignModel, $foreignColumn) {
        return $this->setForeignModel($foreignModel)->setForeignColumn($foreignColumn);
    }

    /**
     * @return array
     */
    public function getConfigsForDbQuery() {
        return array(
            'type' => $this->getJoinType(),
            'table1_model' => $this->getForeignModel(),
            'table1_field' => $this->getForeignColumn(),
            'table1_alias' => $this->getJoinAlias(),
            'table2_alias' => $this->getModel()->getAlias(),
            'table2_field' => $this->getColumn(),
            'conditions' => $this->getAdditionalJoinConditions(),
            'fields' => $this->getForeignColumnsToSelect()
        );
    }

    /**
     * @return null|string
     */
    public function getColumn() {
        return $this->column;
    }

    /**
     * @param null|string $column
     * @return $this
     */
    public function setColumn($column) {
        $this->column = $column;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getForeignColumn() {
        return $this->foreignColumn;
    }

    /**
     * @param null|string $foreignColumn
     * @return $this
     */
    public function setForeignColumn($foreignColumn) {
        $this->foreignColumn = $foreignColumn;
        return $this;
    }

    /**
     * @return null|DbModel
     */
    public function getForeignModel() {
        return $this->foreignModel;
    }

    /**
     * @param null|DbModel $foreignModel
     * @return $this
     */
    public function setForeignModel($foreignModel) {
        $this->foreignModel = $foreignModel;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getJoinAlias() {
        return $this->joinAlias;
    }

    /**
     * @param null|string $joinAlias
     * @return $this
     */
    public function setJoinAlias($joinAlias) {
        $this->joinAlias = $joinAlias;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getJoinType() {
        return $this->joinType;
    }

    /**
     * @param null|string $joinType
     * @return $this
     */
    public function setJoinType($joinType) {
        $this->joinType = $joinType;
        return $this;
    }

    /**
     * @return null|DbModel
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * @param null|DbModel $model
     * @return $this
     */
    public function setModel($model) {
        $this->model = $model;
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
     * @param array|string $foreignColumnsToSelect
     * @return $this
     */
    public function setForeignColumnsToSelect($foreignColumnsToSelect = '*') {
        $this->foreignColumnsToSelect = $foreignColumnsToSelect;
        return $this;
    }

}