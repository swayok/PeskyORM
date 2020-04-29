<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;

interface TableInterface {
    
    /**
     * Table Name
     * @return string
     */
    static public function getName(): string;
    
    /**
     * Table alias.
     * For example: if table name is 'user_actions' the alias might be 'UserActions'
     * @return string
     */
    static public function getAlias(): string;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    static public function getConnection($writable = false): DbAdapterInterface;
    
    /**
     * @return TableInterface
     */
    static public function getInstance(): TableInterface;
    
    /**
     * Table schema description
     * @return TableStructure
     */
    static public function getStructure(): TableStructureInterface;

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
    static public function getJoinConfigForRelation($relationName, $alterLocalTableAlias = null, $joinName = null): OrmJoinInfo;
    
    /**
     * @return bool
     */
    static public function hasPkColumn(): bool;
    
    /**
     * @return Column
     */
    static public function getPkColumn(): Column;
    
    /**
     * @return mixed
     */
    static public function getPkColumnName(): string;

    /**
     * @return Record
     */
    public function newRecord();
    
    /**
     * @param bool $useWritableConnection
     * @return null|string
     */
    static public function getLastQuery(bool $useWritableConnection): ?string ;

    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     */
    static public function exec($query);
    
    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|array
     */
    static public function query($query, ?string $fetchData = null);

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordsSet
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditions = [], ?\Closure $configurator = null);
    
    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectColumn($column, array $conditions = [], ?\Closure $configurator = null): array;
    
    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectAssoc(string $keysColumn, string $valuesColumn, array $conditions = [], ?\Closure $configurator = null): array;
    
    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     */
    static public function selectOne($columns, array $conditions, ?\Closure $configurator = null): array;
    
    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordInterface
     */
    static public function selectOneAsDbRecord($columns, array $conditions, ?\Closure $configurator = null): RecordInterface;
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return string|null
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditions = [], ?\Closure $configurator = null): ?string;

    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \InvalidArgumentException
     */
    static public function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool;
    
    /**
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    static public function count(array $conditions = [], ?\Closure $configurator = null, bool $removeNotInnerJoins = false): int;
    
    /**
     * @param bool $readOnly
     * @param null|string $transactionType
     * @return void
     */
    static public function beginTransaction(bool $readOnly = false, ?string $transactionType = null);
    
    /**
     * @return bool
     */
    static public function inTransaction(): bool;

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
    static public function update(array $data, array $conditions, $returning = false);

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
    static public function getExpressionToSetDefaultValueForAColumn(): DbExpr;

}