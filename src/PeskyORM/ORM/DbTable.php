<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\ORM\Exception\OrmException;
use Swayok\Utils\StringUtils;

abstract class DbTable implements DbTableInterface {

    /** @var DbTable */
    static protected $instance;
    /** @var string  */
    static protected $alias;

    /**
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws OrmException
     */
    static public function getInstance() {
        if (static::$instance === null) {
            if (!static::hasPkColumn()) {
                throw new OrmException(
                    'Table schema must contain primary key',
                    OrmException::CODE_INVALID_TABLE_SCHEMA
                );
            }
            static::$instance = new static();
            static::$alias = StringUtils::classify(static::getTableName());
        }
        return static::$instance;
    }

    /**
     * Shortcut for static::getInstance()
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws OrmException
     */
    static public function i() {
        return static::getInstance();
    }

    /**
     * @return DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    static public function getConnection() {
        return DbConnectionsManager::getConnection(static::getStructure()->getConnectionName());
    }

    /**
     * @return string
     */
    static public function getAlias() {
        return static::$alias;
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::getStructure()->hasPkColumn();
    }

    /**
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumn() {
        return static::getStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumnName() {
        return static::getStructure()->getPkColumnName();
    }

    /**
     * @param string $relationAlias - alias for relation defined in DbTableStructure
     * @return DbTableInterface
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTable($relationAlias) {
        return static::getStructure()->getRelation($relationAlias)->getForeignTable();
    }

    /**
     * @param string $relationAlias - alias for relation defined in DbTableStructure
     * @return bool
     * @throws \BadMethodCallException
     */
    static public function hasRelation($relationAlias) {
        return static::getStructure()->hasRelation($relationAlias);
    }

    /**
     * @return DbExpr
     * @throws \PeskyORM\Core\DbException
     */
    static public function getExpressionToSetDefaultValueForAColumn() {
        return static::getConnection()->getExpressionToSetDefaultValueForAColumn();
    }

    /**
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return DbRecordsSet
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchMany();
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditionsAndOptions
     * @return array
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectColumn($column, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->columns(['value' => $column])
            ->fetchColumn();
    }

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditionsAndOptions
     * @return array
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return array
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectOne($columns, array $conditionsAndOptions) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchOne();
    }

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditionsAndOptions
     * @return string
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->fetchValue($expression);
    }

    /**
     * Does table contain any record matching provided condition
     * @param array $conditionsAndOptions
     * @return bool
     * @throws \PDOException
     * @throws \LengthException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasMatchingRecord(array $conditionsAndOptions) {
        $conditionsAndOptions['LIMIT'] = 1;
        unset($conditionsAndOptions['OFFSET'], $conditionsAndOptions['ORDER']);
        return static::selectValue(DbExpr::create('1'), $conditionsAndOptions) === 1;
    }

    /**
     * @param array $conditionsAndOptions
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function count(array $conditionsAndOptions, $removeNotInnerJoins = false) {
        return OrmSelect::from(static::getTableName())
            ->fromConfigsArray($conditionsAndOptions)
            ->fetchCount($removeNotInnerJoins);
    }

    /**
     * @return null|string
     */
    static public function getLastQuery() {
        return static::getConnection()->getLastQuery();
    }
    
    static public function beginTransaction($readOnly = false, $transactionType = null) {
        static::getConnection()->begin($readOnly, $transactionType);
    }

    static public function inTransaction() {
        return static::getConnection()->inTransaction();
    }

    static public function commitTransaction() {
        static::getConnection()->commit();
    }

    static public function rollBackTransaction() {
        static::getConnection()->rollBack();
    }

    static public function quoteDbEntityName($name) {
        return static::getConnection()->quoteDbEntityName($name);
    }

    static public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return static::getConnection()->quoteValue($value, $fieldInfoOrType);
    }

    static public function query($query, $fetchData = null) {
        return static::getConnection()->query($query, $fetchData);
    }

    static public function exec($query) {
        return static::getConnection()->exec($query);
    }

    /**
     * @param array $data
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function insert(array $data, $returning = false) {
        return static::getConnection()->insert(
            static::getTableName(),
            $data,
            static::getPdoDataTypesForColumns(),
            $returning
        );
    }

    /**
     * @param array $columns - list of column names to insert data for
     * @param array $rows - data to insert
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function insertMany(array $columns, array $rows, $returning = false) {
        return static::getConnection()->insertMany(
            static::getTableName(),
            $columns,
            $rows,
            static::getPdoDataTypesForColumns($columns),
            $returning
        );
    }

    /**
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return int - number of modified rows
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function update(array $data, array $conditions, $returning = false) {
        return static::getConnection()->update(
            static::getTableName(),
            $data,
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
            static::getPdoDataTypesForColumns(),
            $returning
        );
    }

    /**
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return int|array - int: number of deleted records | array: returned only if $returning is not empty
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function delete(array $conditions = [], $returning = false) {
        return static::getConnection()->delete(
            static::getTableName(),
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
            $returning
        );
    }

    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static protected function getPdoDataTypesForColumns(array $columns = []) {
        $pdoDataTypes = [];
        if (empty($columns)) {
            $columns = array_keys(static::getStructure()->getColumns());
        }
        foreach ($columns as $columnName) {
            $columnInfo = static::getStructure()->getColumn($columnName);
            switch ($columnInfo->getType()) {
                case $columnInfo::TYPE_BOOL:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_BOOL;
                    break;
                case $columnInfo::TYPE_INT:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_INT;
                    break;
                case $columnInfo::TYPE_BLOB:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_LOB;
                    break;
                default:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_STR;
            }
        }
        return $pdoDataTypes;
    }

}