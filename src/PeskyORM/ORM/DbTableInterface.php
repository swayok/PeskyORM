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
    static public function newRecord();

    /**
     * @return null|string
     */
    static public function getLastQuery();

    /**
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return DbRecordsSet
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditionsAndOptions = []);

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectColumn($column, array $conditionsAndOptions = []);

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditionsAndOptions = []);

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectOne($columns, array $conditionsAndOptions);

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditionsAndOptions
     * @return string
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditionsAndOptions = []);

    /**
     * Does table contain any record matching provided condition
     * @param array $conditionsAndOptions
     * @return bool
     * @throws \InvalidArgumentException
     */
    static public function hasMatchingRecord(array $conditionsAndOptions);

    /**
     * @param array $conditionsAndOptions
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    static public function count(array $conditionsAndOptions, $removeNotInnerJoins = false);

    static public function beginTransaction($readOnly = false, $transactionType = null);

    static public function inTransaction();

    static public function commitTransaction();

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