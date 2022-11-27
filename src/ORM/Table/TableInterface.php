<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\PdoUtils;

interface TableInterface
{
    public static function getInstance(): TableInterface;

    /**
     * Get table name
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

    /**
     * Table schema description
     */
    public static function getStructure(): TableStructureInterface;
    
    /**
     * Table schema description
     */
    public function getTableStructure(): TableStructureInterface;
    
    /**
     * @param string|RelationInterface $relation
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     */
    public static function getJoinConfigForRelation(
        string|RelationInterface $relation,
        string $alterLocalTableAlias = null,
        string $joinName = null
    ): NormalJoinConfigInterface;
    
    public static function getPkColumn(): TableColumnInterface;
    
    public static function getPkColumnName(): string;
    
    public function newRecord(): RecordInterface;

    /**
     * @see DbAdapterInterface::getLastQuery()
     */
    public static function getLastQuery(bool $useWritableConnection): ?string;

    /**
     * @see DbAdapterInterface::exec()
     */
    public static function exec(string|DbExpr $query): int;
    
    /**
     * @see DbAdapterInterface::query()
     */
    public static function query(
        string|DbExpr $query,
        string $fetchData = PdoUtils::FETCH_STATEMENT
    ): mixed;
    
    /**
     * @see self::makeQueryBuilder()
     */
    public static function select(
        string|array $columns = '*',
        array $conditions = [],
        ?\Closure $configurator = null
    ): RecordsSet|array;
    
    /**
     * Selects values from specified column
     * @see self::makeQueryBuilder()
     */
    public static function selectColumn(
        string|DbExpr $column,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array;
    
    /**
     * Select associative array
     * @see self::makeQueryBuilder()
     */
    public static function selectAssoc(
        string|DbExpr|null $keysColumn,
        string|DbExpr|null $valuesColumn,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array;
    
    /**
     * Get 1 record from DB as array
     * @see self::makeQueryBuilder()
     */
    public static function selectOne(
        string|array $columns,
        array $conditions,
        ?\Closure $configurator = null
    ): array;
    
    /**
     * Get 1 row from DB and convert its data to RecordInterface
     * @see self::makeQueryBuilder()
     * @see self::newRecord()
     */
    public static function selectOneAsDbRecord(
        string|array $columns,
        array $conditions,
        ?\Closure $configurator = null
    ): RecordInterface;
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * Examples for $expression: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @see self::makeQueryBuilder()
     */
    public static function selectValue(
        DbExpr $expression,
        array $conditions = [],
        ?\Closure $configurator = null
    ): mixed;

    /**
     * Make a query that returns only 1 value for specific column
     * @see self::makeQueryBuilder()
     */
    public static function selectColumnValue(
        string|TableColumnInterface $column,
        array $conditions = [],
        ?\Closure $configurator = null
    ): mixed;
    
    /**
     * Check if table contains a record matching provided conditions
     * @see self::makeQueryBuilder()
     */
    public static function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool;
    
    /**
     * Using $removeNotInnerJoins = true will ignore all not INNER JOINs to build a count query.
     * This will improve query performance. But in some cases it may cause errors,
     * so you need to decide if your query will perform normally without LEFT/RIGHT/FULL JOINs.
     * When $conditions depend on Relations/Joins - use $removeNotInnerJoins = false to be safe
     * @see self::makeQueryBuilder()
     */
    public static function count(
        array $conditions = [],
        ?\Closure $configurator = null,
        bool $removeNotInnerJoins = false
    ): int;

    /**
     * @param array|string $columns - columns to select
     * @param array $conditions - Where conditions and options
     * @param \Closure|null $configurator - closure to configure Select instance:
     *      function (SelectQueryBuilderInterface $select): void {}
     * @return SelectQueryBuilderInterface
     * @see SelectQueryBuilderInterface::fromConfigsArray()
     * @see SelectQueryBuilderInterface::columns()
     * @see SelectQueryBuilderInterface::where()
     */
    public static function makeQueryBuilder(
        array|string $columns,
        array $conditions = [],
        ?\Closure $configurator = null
    ): SelectQueryBuilderInterface;

    /**
     * @see DbAdapterInterface::begin()
     */
    public static function beginTransaction(
        bool $readOnly = false,
        ?string $transactionType = null
    ): void;

    /**
     * @see DbAdapterInterface::inTransaction()
     */
    public static function inTransaction(): bool;

    /**
     * @see DbAdapterInterface::commit()
     */
    public static function commitTransaction(): void;

    /**
     * @see DbAdapterInterface::rollBack()
     */
    public static function rollBackTransaction(bool $onlyIfExists = false): void;
    
    /**
     * @param bool $valuesAreProcessed - should values be processed via
     *      - true: values are ready to be inserted;
     *      - false - values must be processed according to TableColumnInterface options
     * @see RecordInterface::getValuesForInsertQuery()
     * @see DbAdapterInterface::insert()
     */
    public static function insert(
        array $data,
        array|bool $returning = false,
        bool $valuesAreProcessed = true
    ): ?array;
    
    /**
     * @param bool $valuesAreProcessed - should values be processed via
     *      - true: values are ready to be inserted;
     *      - false - values must be processed according to TableColumnInterface options
     * @see RecordInterface::getValuesForInsertQuery()
     * @see DbAdapterInterface::insertMany()
     */
    public static function insertMany(
        array $columns,
        array $rows,
        array|bool $returning = false,
        bool $valuesAreProcessed = true
    ): ?array;

    /**
     * Insert new record or update existing one if duplicate value found for $columnName
     * @param array $data - must contain values for all columns in $uniqueColumnNames
     * @param array $uniqueColumnNames - list of columns used to detect if $data already exists in table
     * @return RecordInterface
     * @throws \InvalidArgumentException
     * @see self::newRecord()
     */
    public static function upsert(array $data, array $uniqueColumnNames): RecordInterface;
    
    /**
     * @see DbAdapterInterface::update()
     */
    public static function update(array $data, array $conditions, array|bool $returning = false): array|int;
    
    /**
     * @see DbAdapterInterface::delete()
     */
    public static function delete(array $conditions = [], array|bool $returning = false): array|int;
    
    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT')
     * @see DbAdapterInterface::getExpressionToSetDefaultValueForAColumn()
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr;
    
}