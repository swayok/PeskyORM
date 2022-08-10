<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;

interface TableInterface
{
    
    /**
     * Table Name
     * @return string
     */
    public static function getName(): string;
    
    /**
     * Table alias.
     * For example: if table name is 'user_actions' the alias might be 'UserActions'
     * @return string
     */
    public static function getAlias(): string;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    public static function getConnection($writable = false): DbAdapterInterface;
    
    /**
     * @return TableInterface
     */
    public static function getInstance(): TableInterface;
    
    /**
     * Table schema description
     * @return TableStructure
     */
    public static function getStructure(): TableStructureInterface;
    
    /**
     * Table schema description
     * @return TableStructure
     */
    public function getTableStructure();
    
    /**
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinInfo
     */
    public static function getJoinConfigForRelation($relationName, $alterLocalTableAlias = null, $joinName = null): OrmJoinInfo;
    
    /**
     * @return bool
     */
    public static function hasPkColumn(): bool;
    
    /**
     * @return Column
     */
    public static function getPkColumn(): Column;
    
    /**
     * @return mixed
     */
    public static function getPkColumnName(): string;
    
    /**
     * @return Record
     */
    public function newRecord();
    
    /**
     * @param bool $useWritableConnection
     * @return null|string
     */
    public static function getLastQuery(bool $useWritableConnection): ?string;
    
    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     */
    public static function exec($query);
    
    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|array
     */
    public static function query($query, ?string $fetchData = null);
    
    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordsSet
     * @throws \InvalidArgumentException
     */
    public static function select($columns = '*', array $conditions = [], ?\Closure $configurator = null);
    
    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    public static function selectColumn($column, array $conditions = [], ?\Closure $configurator = null): array;
    
    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    public static function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], ?\Closure $configurator = null): array;
    
    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    public static function selectOne($columns, array $conditions, ?\Closure $configurator = null): array;
    
    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordInterface
     */
    public static function selectOneAsDbRecord($columns, array $conditions, ?\Closure $configurator = null): RecordInterface;
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return
     * @throws \InvalidArgumentException
     */
    public static function selectValue(DbExpr $expression, array $conditions = [], ?\Closure $configurator = null);
    
    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool;
    
    /**
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public static function count(array $conditions = [], ?\Closure $configurator = null, bool $removeNotInnerJoins = false): int;
    
    /**
     * @param bool $readOnly
     * @param null|string $transactionType
     * @return void
     */
    public static function beginTransaction(bool $readOnly = false, ?string $transactionType = null);
    
    /**
     * @return bool
     */
    public static function inTransaction(): bool;
    
    /**
     * @return void
     */
    public static function commitTransaction();
    
    /**
     * @return void
     */
    public static function rollBackTransaction();
    
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
    public static function insert(array $data, $returning = false);
    
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
    public static function insertMany(array $columns, array $rows, $returning = false);
    
    /**
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function update(array $data, array $conditions, $returning = false);
    
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
    public static function delete(array $conditions = [], $returning = false);
    
    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT')
     * @return DbExpr
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr;
    
}