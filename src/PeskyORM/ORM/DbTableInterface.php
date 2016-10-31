<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;

interface DbTableInterface {

    /**
     * Table Name
     * @return string
     */
    static public function getName();

    /**
     * Table alias.
     * For example: if table name is 'user_actions' the alias might be 'UserActions'
     * @return string
     */
    static public function getAlias();

    /**
     * @return DbAdapterInterface
     */
    static public function getConnection();

    /**
     * @return DbTableInterface
     */
    static public function getInstance();

    /**
     * Table schema description
     * @return DbTableStructure
     */
    static public function getStructure();

    /**
     * Table schema description
     * @return DbTableStructure
     */
    public function getTableStructure();

    /**
     * @param string $relationName
     * @param string|null $alterTableAlias - alter this table's alias in join config
     * @return OrmJoinConfig
     */
    static public function getJoinConfigForRelation($relationName, $alterTableAlias = null);

    /**
     * @return bool
     */
    static public function hasPkColumn();

    /**
     * @return DbTableColumn
     */
    static public function getPkColumn();

    /**
     * @return mixed
     */
    static public function getPkColumnName();

    /**
     * @return DbRecord
     */
    public function newRecord();

    /**
     * @return null|string
     */
    static public function getLastQuery();

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return DbRecordsSet
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditions = [], \Closure $configurator = null);

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectColumn($column, array $conditions = [], \Closure $configurator = null);

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], \Closure $configurator = null);

    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectOne($columns, array $conditions, \Closure $configurator = null);

    /**
     * Get 1 record from DB as DbRecord
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectOneAsDbRecord($columns, array $conditions, \Closure $configurator = null);

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return string
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditions = [], \Closure $configurator = null);

    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \InvalidArgumentException
     */
    static public function hasMatchingRecord(array $conditions, \Closure $configurator = null);

    /**
     * @param array $conditions
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return int
     */
    static public function count(array $conditions, \Closure $configurator = null, $removeNotInnerJoins = false);

    /**
     * @param bool $readOnly
     * @param null|string $transactionType
     * @return void
     */
    static public function beginTransaction($readOnly = false, $transactionType = null);

    /**
     * @return bool
     */
    static public function inTransaction();

    /**
     * @return void
     */
    static public function commitTransaction();

    /**
     * @return void
     */
    static public function rollBackTransaction();

    /**
     * @param array $data
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function insert(array $data, $returning = false);

    /**
     * @param array $columns - list of column names to insert data for
     * @param array $rows - data to insert
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function insertMany(array $columns, array $rows, $returning = false);

    /**
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $conditions - WHERE conditions
     * @return int - number of modified rows
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function update(array $data, array $conditions);

    /**
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return int|array - int: number of deleted records | array: returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function delete(array $conditions = [], $returning = false);

    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT')
     * @return DbExpr
     */
    static public function getExpressionToSetDefaultValueForAColumn();

}