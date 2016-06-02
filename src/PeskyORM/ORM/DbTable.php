<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\ORM\Exception\OrmException;
use Swayok\Utils\StringUtils;

abstract class DbTable implements DbTableInterface {

    /** @var $this */
    static protected $instance;
    /** @var string  */
    static protected $alias;
    /** @var DbTableStructure */
    static protected $tableStructure;

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
            static::$tableStructure = DbClassesManager::i()->getTableStructure(static::getTableName());
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
     * @return string
     */
    static public function getAlias() {
        return static::$alias;
    }

    /**
     * @return DbTableStructure
     * @throws \BadMethodCallException
     */
    static public function getStructure() {
        return static::$tableStructure;
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
     * @return bool
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
     * @return DbTable
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTable($relationAlias) {
        return static::getStructure()->getRelation($relationAlias)->getForeignTable();
    }

    /**
     * @param string $relationAlias - alias for relation defined in DbTableStructure
     * @return string
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTableName($relationAlias) {
        return static::getStructure()->getRelation($relationAlias)->getForeignTableName();
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
     * @return DbRecord
     * @throws \BadMethodCallException
     */
    static public function newRecord() {
        return DbClassesManager::i()->newRecord(static::getTableName());
    }

    /**
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return DbRecordsSet
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchMany();
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditionsAndOptions
     * @return array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectColumn($column, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectOne($columns, array $conditionsAndOptions) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchOne();
    }

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditionsAndOptions
     * @return string
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditionsAndOptions = []) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->fetchValue($expression);
    }

    /**
     * Does table contain any record matching provided condition
     * @param array $conditionsAndOptions
     * @return bool
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function count(array $conditionsAndOptions, $removeNotInnerJoins = false) {
        return OrmSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
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

    static public function quoteName($name) {
        return static::getConnection()->quoteName($name);
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
            static::assembleWhereConditionsFromArray($conditions),
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
     * @throws \BadMethodCallException
     * @throws OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function delete(array $conditions = [], $returning = false) {
        return static::getConnection()->delete(
            static::getTableName(),
            static::assembleWhereConditionsFromArray($conditions),
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

    /**
     * we have next cases:
     * 1. $value is instance of DbExpr (converted to quoted string), $column - index
     *
     * 2. $column is string
     *     (results in 'colname' <operator> 'value')
     * 2.1 $column contains equation sign (for example 'colname >', results in 'colname' > 'value')
     *     Short list of equations:
     *          >, <, =, !=, >=, <=, LIKE, ~, ~*, !~, !~*, SIMILAR TO, IN; NOT; NOT IN; IS; IS NOT
     * 2.2 $value === null, $column may contain: 'NOT', '!=', '=', 'IS', 'IS NOT'
     *     (results in 'colname' IS NULL or 'colname' IS NOT NULL)
     * 2.3 $column contains 'BETWEEN' or 'NOT BETWEEN' and $value is array or string like 'val1 and val2'
     *     (results in 'colname' BETWEEN a and b)
     * 2.4 $column contains no equation sign or contains '!=' or 'NOT', $value is array or DbExpr
     *     (results in 'colname' IN (a,b,c) or 'colname' IN (SELECT '*' FROM ...))
     *
     * 3. $value === array()
     * 3.1. $column !== 'OR' -> recursion: static::assembleWhereConditionsFromArray($value))
     * 3.2. $column === 'OR' -> recursion: static::assembleWhereConditionsFromArray($value, 'OR'))
     *
     * @param array $conditions
     * @param string $glue - 'AND' or 'OR'
     * @return string
     * @throws \BadMethodCallException
     * @throws OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function assembleWhereConditionsFromArray(array $conditions, $glue = 'AND') {
        return Utils::assembleWhereConditionsFromArray(
            static::getConnection(),
            $conditions,
            $glue,
            function ($columnName) {
                return static::quoteConditionColumn($columnName);
            }
        );
    }

    /**
     * @param string $column
     * @return string - quoted column
     * @throws \BadMethodCallException
     * @throws OrmException
     * @throws \InvalidArgumentException
     */
    static protected function quoteConditionColumn($column) {
        return static::parseColumnRepresentation($column)['quoted'];
    }

    /**
     * Parse $column and collect information about it
     * @param string $column - supported formats:
     *      - 'column'
     *      - 'column::data_type_convert'
     *      - 'table_alias.column'
     *      - 'table_alias.column::data_type_convert'
     * @return array = [
     * @throws \BadMethodCallException
     *      'raw' => string,
     *      'table_alias' => string,
     *      'column' => DbTableColumn,
     *      'table' => DbTable,
     *      'data_type_convert' => string,
     *      'quoted' => string
     *  ]
     * @throws \InvalidArgumentException
     * @throws OrmException
     */
    static public function parseColumnRepresentation($column) {
        if (!is_string($column)) {
            throw new \InvalidArgumentException('$column argument must be a string');
        }
        $ret = [
            'raw' => $column,
            'table_alias' => '',
            'column' => '',
            'table' => '',
            'data_type_convert' => '',
            'quoted' => ''
        ];
        if (preg_match('%^\s*(\w+)(?:\.(\w+))?(?:::([a-zA-Z0-9 _]+))?\s*$%i', $column, $columnParts)) {
            if (empty($columnParts[2])) {
                // $column = 'column'|'column::data_type_convert'
                $ret['table'] = static::getInstance();
                $ret['column'] = $columnParts[1];
                $ret['table_alias'] = static::getAlias();
            } else {
                // $column = 'table_alias.column'|'table_alias.column::data_type_convert'
                list(, $ret['table_alias'], $ret['column']) = $columnParts;
                if (static::hasRelation($ret['table_alias'])) {
                    $ret['table'] = static::getRelatedTable($ret['table_alias']);
                } else {
                    $ret['table'] = DbClassesManager::i()->getTableInstanceByAlias($ret['table_alias']);
                }
            }
            $ret['column'] = $ret['table']->getStructure()->getColumn($ret['column']);
            $ret['data_type_convert'] = !empty($columnParts[3]) ? $columnParts[3] : '';
            $ret['quoted'] = static::quoteName($ret['table_alias']) . '.' . static::quoteName($ret['column']);
            $ret['quoted'] = $ret['table']->getConnection()->addDataTypeCastToExpression(
                $ret['data_type_convert'],
                $ret['quoted']
            );
        } else {
            throw new \InvalidArgumentException(
                "Cannot parse $column argument. Maybe its format is wrong. \$column = '$column'"
            );
        }
        
        return $ret;
    }


}