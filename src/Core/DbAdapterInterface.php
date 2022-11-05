<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PDO;
use PDOStatement;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\ORM\RecordInterface;

interface DbAdapterInterface
{

    /**
     * Connect to DB once
     * @return PDO or PDO wrapper
     */
    public function getConnection(): PDO;

    public function getConnectionConfig(): DbConnectionConfigInterface;

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
     * @see PdoUtils::getDataFromStatement()
     * @param string|DbExpr $query
     * @param string $fetchData - how to fetch data
     * @return mixed
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
     */
    public function listen(
        string $channel,
        \Closure $handler,
        int $sleepIfNoNotificationMs = 1000,
        int $sleepAfterNotificationMs = 0
    ): void;

    /**
     * Set DB timezone for current session
     */
    public function setTimezone(string $timezone): static;

    /**
     * @param string $newSearchPath - coma-separated list of DB schemas
     */
    public function setSearchPath(string $newSearchPath): static;

    /**
     * @param string $table
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
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
     * @param string $table
     * @param array $columns - list of columns to insert data to
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return array|null - array returned only if $returning is not empty
     */
    public function insertMany(
        string $table,
        array $columns,
        array $data,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): ?array;

    /**
     * @param string $table
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param string|DbExpr $conditions - WHERE conditions
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     */
    public function update(
        string $table,
        array $data,
        string|DbExpr $conditions,
        array $dataTypes = [],
        bool|array $returning = false
    ): array|int;

    /**
     * @param string $table
     * @param string|DbExpr $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @return array|int - int: number of deleted records | array: returned only if $returning is not empty
     */
    public function delete(string $table, string|DbExpr $conditions, bool|array $returning = false): array|int;

    public function inTransaction(): bool;

    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     */
    public function begin(bool $readOnly = false, ?string $transactionType = null): static;

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
     * Quote passed value
     * @param string|int|float|bool|array|AbstractSelect|DbExpr|RecordInterface|null $value
     * @param int|null $valueDataType - one of \PDO::PARAM_* or null for autodetection (detects bool, null, string only)
     */
    public function quoteValue(
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $value,
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
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $rawValue,
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
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     */
    public function select(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array;

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an array with values for
     * specified $column.
     * The query is something like: "SELECT $column FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $column
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     */
    public function selectColumn(string $table, string|DbExpr $column, ?DbExpr $conditionsAndOptions = null): array;

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an associative array.
     * The query is something like: "SELECT $keysColumn, $valuesColumn FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $keysColumn
     * @param string|DbExpr $valuesColumn
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     */
    public function selectAssoc(
        string $table,
        string|DbExpr $keysColumn,
        string|DbExpr $valuesColumn,
        ?DbExpr $conditionsAndOptions = null
    ): array;

    /**
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     */
    public function selectOne(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array;

    /**
     * Select a value form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $expression FROM $table $conditionsAndOptions"
     * @param string $table
     * @param DbExpr $expression - something like "COUNT(*)" or anything else
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     */
    public function selectValue(string $table, DbExpr $expression, ?DbExpr $conditionsAndOptions = null): mixed;

    /**
     * Make a simple SELECT query from passed parameters
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return string - something like: "SELECT $columns FROM $table $conditionsAndOptions"
     */
    public function makeSelectQuery(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): string;

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
