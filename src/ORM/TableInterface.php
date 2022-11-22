<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Join\OrmJoinConfig;

interface TableInterface
{
    
    /**
     * Table Name
     */
    public static function getName(): string;
    
    /**
     * Table alias.
     * For example: if table name is 'user_actions' the alias might be 'UserActions'
     */
    public static function getAlias(): string;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     */
    public static function getConnection(bool $writable = false): DbAdapterInterface;
    
    public static function getInstance(): TableInterface;
    
    /**
     * Table schema description
     */
    public static function getStructure(): TableStructureInterface;
    
    /**
     * Table schema description
     */
    public function getTableStructure(): TableStructureInterface;
    
    /**
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinConfig
     */
    public static function getJoinConfigForRelation(
        string $relationName,
        string $alterLocalTableAlias = null,
        string $joinName = null
    ): OrmJoinConfig;
    
    public static function hasPkColumn(): bool;
    
    public static function getPkColumn(): Column;
    
    public static function getPkColumnName(): string;
    
    public function newRecord(): RecordInterface;
    
    public static function getLastQuery(bool $useWritableConnection): ?string;
    
    public static function exec(string|DbExpr $query): int;
    
    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     */
    public static function query(string|DbExpr $query, ?string $fetchData = null): mixed;
    
    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @return array|RecordsSet
     * @throws \InvalidArgumentException
     */
    public static function select(string|array $columns = '*', array $conditions = [], ?\Closure $configurator = null): RecordsSet|array;
    
    /**
     * Selects only 1 column
     * @param string|DbExpr $column
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @return array
     */
    public static function selectColumn(string|DbExpr $column, array $conditions = [], ?\Closure $configurator = null): array;
    
    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string|DbExpr|null $keysColumn
     * @param string|DbExpr|null $valuesColumn
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     */
    public static function selectAssoc(
        string|DbExpr|null $keysColumn,
        string|DbExpr|null $valuesColumn,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array;
    
    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - \Closure to configure OrmSelect. function (OrmSelect $select): void {}
     */
    public static function selectOne(string|array $columns, array $conditions, ?\Closure $configurator = null): array;
    
    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @return RecordInterface
     */
    public static function selectOneAsDbRecord(string|array $columns, array $conditions, ?\Closure $configurator = null): RecordInterface;
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @throws \InvalidArgumentException
     */
    public static function selectValue(DbExpr $expression, array $conditions = [], ?\Closure $configurator = null): mixed;
    
    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool;
    
    /**
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public static function count(array $conditions = [], ?\Closure $configurator = null, bool $removeNotInnerJoins = false): int;
    
    public static function beginTransaction(bool $readOnly = false, ?string $transactionType = null): void;
    
    public static function inTransaction(): bool;
    
    public static function commitTransaction(): void;
    
    public static function rollBackTransaction(): void;
    
    /**
     * @param array $data
     * @param array|bool $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|null - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function insert(array $data, array|bool $returning = false): ?array;
    
    /**
     * @param array $columns - list of column names to insert data for
     * @param array $rows - data to insert
     * @param array|bool $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|null - array returned only if $returning is not empty
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public static function insertMany(array $columns, array $rows, array|bool $returning = false): ?array;
    
    /**
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $conditions - WHERE conditions
     * @param array|bool $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function update(array $data, array $conditions, array|bool $returning = false): array|int;
    
    /**
     * @param array $conditions - WHERE conditions
     * @param array|bool $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|int - int: number of deleted records | array: returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function delete(array $conditions = [], array|bool $returning = false): array|int;
    
    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT')
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr;
    
}