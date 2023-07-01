<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PDO;
use PDOStatement;
use PeskyORM\Config\Connection\DbConnectionConfigInterface;
use PeskyORM\DbExpr;
use PeskyORM\Exception\DbAdapterDoesNotSupportFeature;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ServiceContainer;

interface DbAdapterInterface
{
    public function __construct(DbConnectionConfigInterface $connectionConfig, string $name);

    /**
     * Name of DB adapter.
     * Used in service container.
     * Example: 'mysql', 'pgsql'
     * @return string
     * @see ServiceContainer
     */
    public function getName(): string;

    /**
     * Connect to DB once
     * @return PDO or PDO wrapper
     */
    public function getConnection(): PDO;

    /**
     * Check if connection to DB established
     */
    public function isConnected(): bool;

    public function getConnectionConfig(): DbConnectionConfigInterface;

    /**
     * Set/Remove a wrapper around PDO connection. Wrapper called on each DB connection.
     * Usuallay used to profile and monitor queries
     */
    public function setConnectionWrapper(?\Closure $wrapper): void;

    /**
     * Run $callback when DB connection created (or right now if connection already established)
     * @param \Closure $callback
     * @param null|string $code - callback code to prevent duplicate usage
     * @return static
     */
    public function onConnect(\Closure $callback, ?string $code = null): static;

    public function disconnect(): static;

    /**
     * Get last executed query
     */
    public function getLastQuery(): ?string;

    /**
     * @return int - affected rows count
     */
    public function exec(string|DbExpr $query): int;

    /**
     * @param string|DbExpr $query
     * @param array $options - see PDO::prepare()
     * @return PDOStatement
     */
    public function prepare(string|DbExpr $query, array $options = []): PDOStatement;

    /**
     * @param string|DbExpr $query
     * @param string $fetchData - what to return: one of \PeskyORM\Utils\PdoUtils::FETCH_*
     * @return mixed
     * @see \PeskyORM\Utils\PdoUtils::getDataFromStatement()
     */
    public function query(
        string|DbExpr $query,
        string $fetchData
    ): mixed;

    /**
     * Listen for DB notifications (mostly for PostgreSQL LISTEN...NOTIFY)
     * @param string $channel
     * @param \Closure $handler - payload handler:
     *      function(string $payload): boolean { return true; } - if it returns false: listener will stop
     * @param int $sleepIfNoNotificationMs - miliseconds to sleep if there were no notifications last time
     * @param int $sleepAfterNotificationMs - miliseconds to sleep after notification consumed
     * @throws DbAdapterDoesNotSupportFeature when functionality is not supported by adapter
     */
    public function listen(
        string $channel,
        \Closure $handler,
        int $sleepIfNoNotificationMs = 1000,
        int $sleepAfterNotificationMs = 0
    ): void;

    /**
     * Set DB charset
     * Example: UTF8
     */
    public function setCharacterSet(string $charset): static;

    /**
     * Set DB timezone for current session
     */
    public function setTimezone(string $timezone): static;

    /**
     * Set primary database or schema for all queries
     * For PostgreSQL: coma-separated list of DB schema names
     * @link https://www.postgresql.org/docs/current/ddl-schemas.html#DDL-SCHEMAS-PATH
     * For MySQL: database name
     * @link https://dev.mysql.com/doc/refman/5.7/en/use.html
     */
    public function setSearchPath(string $newSearchPath): static;

    /**
     * @param string $table - it is allowed to be like: 'table AS Alias'
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return array|null - array returned only if $returning is not empty
     */
    public function insert(
        string $table,
        array $data,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): ?array;

    /**
     * @param string $table - it is allowed to be like: 'table AS Alias'
     * @param array $columns - list of columns to insert data to
     * @param array $rows - key-value array where key = table column and value = value of associated column
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return array|null - array returned only if $returning is not empty
     */
    public function insertMany(
        string $table,
        array $columns,
        array $rows,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): ?array;

    /**
     * @param string $table - it is allowed to be like: 'table AS Alias'
     * @param array $data - ['column_name' => value, ...]
     * @param array|DbExpr $conditions - WHERE conditions
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     */
    public function update(
        string $table,
        array $data,
        array|DbExpr $conditions,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): array|int;

    /**
     * @param string $table - it is allowed to be like: 'table AS Alias'
     * @param array|DbExpr $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return array|int - int: number of deleted records | array: returned only if $returning is not empty
     */
    public function delete(
        string $table,
        array|DbExpr $conditions,
        bool|array $returning = false,
        string $pkName = 'id'
    ): array|int;

    public function inTransaction(): bool;

    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     */
    public function begin(
        bool $readOnly = false,
        ?string $transactionType = null
    ): static;

    public function commit(): static;

    public function rollBack(): static;

    /**
     * Quote DB entity name (column, table, alias, schema)
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     */
    public function quoteDbEntityName(string $name): string;

    /**
     * Quote a db entity name like 'table.col_name -> json_key1 ->> json_key2'
     * or 'table.col_name -> json_key1 ->> integer_as_index'
     * or 'table.col_name -> json_key1 ->> `integer_as_key`'
     * @param array $sequence -
     *      index 0: base entity name ('table.col_name' or 'col_name');
     *      indexes 1, 3, 5, ...: selection operator (->, ->>, #>, #>>);
     *      indexes 2, 4, 6, ...: json key name or other selector ('json_key1', 'json_key2')
     * @return string - quoted entity name and json selecor
     */
    public function quoteJsonSelectorExpression(array $sequence): string;

    /**
     * Test if $name matches a DB entity naming rules, or it is a JSON selector
     * @param string $name
     * @param bool $canBeAJsonSelector - test if $name contains a JSON selector like 'col_name -> json_key'
     */
    public function isValidDbEntityName(string $name, bool $canBeAJsonSelector = true): bool;

    /**
     * Maximum length for DB entity name.
     * Usually 63 characters works (MySQL: 64, PostgreSQL: 63).
     * This limitation will be used to detect if table alias or join alias should be shortened.
     */
    public function getMaxLengthForDbEntityName(): int;

    /**
     * Quote passed value
     * @param string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value
     * @param int|null $valueDataType - one of \PDO::PARAM_* or null for autodetection (detects bool, null, string only)
     */
    public function quoteValue(
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value,
        ?int $valueDataType = null
    ): string;

    public function quoteDbExpr(DbExpr $expression): string;

    /**
     * Does DB support table schemas?
     * Postgres - yes: "public"."table_name"; Mysql - no
     */
    public function isDbSupportsTableSchemas(): bool;

    /**
     * Get default DB table schema
     * @return string|null - null: for DB Adapters that does not support table schemas
     */
    public function getDefaultTableSchema(): ?string;

    /**
     * Assemble condition from prepared parts
     */
    public function assembleCondition(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string;

    /**
     * Converts general representation of data type conversion to adapter's specific one
     * General representation is: '::datatype'. Example: '::date', '::timestamp'.
     * General representation is same as data type conversion for Postgres when '::' is used:
     *      http://www.postgresql.org/docs/9.5/static/sql-expressions.html#SQL-SYNTAX-TYPE-CASTS
     * List of types: http://www.postgresql.org/docs/9.5/static/datatype.html
     * @param string $dataType - data type to convert to. Example: 'int', 'date', 'numeric'.
     * @param string $expression - expression to cast type for
     * @return string - something like 'expression::datatype' or 'CAST(expression AS datatype)'
     */
    public function addDataTypeCastToExpression(string $dataType, string $expression): string;

    /**
     * Select many records form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns
     * @param DbExpr|array|null $conditionsAndOptions - see makeSelectQuery() for details
     * @see SelectQueryBuilderInterface::columns() for $columns arg value possibilities
     * @see DbAdapterInterface::makeSelectQuery() for $conditionsAndOptions arg value explanation
     */
    public function select(
        string $table,
        array $columns = [],
        DbExpr|array|null $conditionsAndOptions = null
    ): array;

    /**
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns
     * @param DbExpr|array|null $conditionsAndOptions - see makeSelectQuery() for details
     * @see SelectQueryBuilderInterface::columns() for $columns arg value possibilities
     * @see DbAdapterInterface::makeSelectQuery() for $conditionsAndOptions arg value explanation
     */
    public function selectOne(
        string $table,
        array $columns = [],
        DbExpr|array|null $conditionsAndOptions = null
    ): array;

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an array with values for
     * specified $column.
     * The query is something like: "SELECT $column FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $column
     * @param DbExpr|array|null $conditionsAndOptions
     * @see DbAdapterInterface::makeSelectQuery() for $conditionsAndOptions arg value explanation
     */
    public function selectColumn(
        string $table,
        string|DbExpr $column,
        DbExpr|array|null $conditionsAndOptions = null
    ): array;

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an associative array.
     * The query is something like: "SELECT $keysColumn, $valuesColumn FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $keysColumn
     * @param string|DbExpr $valuesColumn
     * @param DbExpr|array|null $conditionsAndOptions
     * @see DbAdapterInterface::makeSelectQuery() for $conditionsAndOptions arg value explanation
     */
    public function selectAssoc(
        string $table,
        string|DbExpr $keysColumn,
        string|DbExpr $valuesColumn,
        DbExpr|array|null $conditionsAndOptions = null
    ): array;

    /**
     * Select a value form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $expression FROM $table $conditionsAndOptions"
     * @param string $table
     * @param DbExpr $expression - something like "COUNT(*)" or anything else
     * @param DbExpr|array|null $conditionsAndOptions
     * @see DbAdapterInterface::makeSelectQuery() for $conditionsAndOptions arg value explanation
     */
    public function selectValue(
        string $table,
        DbExpr $expression,
        DbExpr|array|null $conditionsAndOptions = null
    ): mixed;

    /**
     * Make a simple SELECT query from passed parameters
     * @param string $table
     * @param DbExpr|array|null $conditionsAndOptions
     *  - DbExpr: anything to add to query after "FROM $table",
     *  - array: conditions and options for query builder
     * @see SelectQueryBuilderInterface::fromConfigsArray() for $conditionsAndOptions arg value explanation when array
     */
    public function makeSelectQuery(
        string $table,
        DbExpr|array|null $conditionsAndOptions = null
    ): SelectQueryBuilderInterface;

    /**
     * Search for $table in $schema
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     */
    public function hasTable(string $table, ?string $schema = null): bool;

    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT') and used for updates and inserts
     * Note: throw exception if adapter does not support this feature
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr;
}
