<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PDOStatement;

interface DbAdapterInterface
{
    
    /**
     * Class name that implements DbConnectionConfigInterface
     * @return string
     */
    public static function getConnectionConfigClass(): string;
    
    /**
     * Connect to DB once
     * @return \PDO or PDO wrapper
     */
    public function getConnection(): \PDO;
    
    public function getConnectionConfig(): DbConnectionConfigInterface;
    
    /**
     * Run $callback when DB connection created (or right now if connection already established)
     * @param \Closure $callback
     * @param null|string $code - callback code to prevent duplicate usage
     * @return static
     */
    public function onConnect(\Closure $callback, ?string $code = null);
    
    /**
     * @return static
     */
    public function disconnect();
    
    /**
     * Get last executed query
     * @return null|string
     */
    public function getLastQuery(): ?string;
    
    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     */
    public function exec($query);
    
    /**
     * @param string|DbExpr $query
     * @param array $options - see PDO::prepare()
     * @return int|array = array: returned if $returning argument is not empty
     */
    public function prepare($query, array $options = []): PDOStatement;
    
    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - how to fetch data (one of DbAdapter::FETCH_*)
     * @return \PDOStatement|array|string|null|int|bool|float
     */
    public function query($query, string $fetchData = DbAdapter::FETCH_STATEMENT);
    
    /**
     * Listen for DB notifications (mostly for PostgreSQL LISTEN...NOTIFY)
     * @param string $channel
     * @param \Closure $handler - payload handler:
     *      function(string $payload): boolean { return true; } - if it returns false: listener will stop
     * @param int $sleepIfNoNotificationMs - miliseconds to sleep if there were no notifications last time
     * @param int $sleepAfterNotificationMs - miliseconds to sleep after notification consumed
     * @return void
     */
    public function listen(string $channel, \Closure $handler, int $sleepIfNoNotificationMs = 1000, int $sleepAfterNotificationMs = 0): void;
    
    /**
     * Set DB timezone for current session
     * @param string $timezone
     * @return static
     */
    public function setTimezone(string $timezone);
    
    /**
     * @param string $newSearchPath - coma-separated list of DB schemas
     * @return static
     */
    public function setSearchPath(string $newSearchPath);
    
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
    public function insert(string $table, array $data, array $dataTypes = [], $returning = false, string $pkName = 'id'): ?array;
    
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
    public function insertMany(string $table, array $columns, array $data, array $dataTypes = [], $returning = false, string $pkName = 'id'): ?array;
    
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
    public function update(string $table, array $data, $conditions, array $dataTypes = [], $returning = false);
    
    /**
     * @param string $table
     * @param string|DbExpr $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @return array|int - int: number of deleted records | array: returned only if $returning is not empty
     */
    public function delete(string $table, $conditions, $returning = false);
    
    public function inTransaction(): bool;
    
    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     * @return static
     */
    public function begin(bool $readOnly = false, ?string $transactionType = null);
    
    /**
     * @return static
     */
    public function commit();
    
    /**
     * @return static
     */
    public function rollBack();
    
    /**
     * @param null|\PDOStatement|\PDO $pdoStatement $pdoStatement - if null: static->getConnection() will be used
     * @return array
     */
    public function getPdoError($pdoStatement = null): array;
    
    /**
     * Quote DB entity name (column, table, alias, schema)
     * @param string $name
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     */
    public function quoteDbEntityName(string $name): string;
    
    /**
     * Test if $name matches a DB entity naming rules or it is a JSON selector
     * @param string $name
     * @param bool $canBeAJsonSelector - test if $name contains a JSON selector like 'col_name -> json_key'
     * @return bool
     */
    public static function isValidDbEntityName(string $name, bool $canBeAJsonSelector = true): bool;
    
    /**
     * Quote passed value
     * @param string|int|float|bool|array|DbExpr|AbstractSelect|null $value
     * @param int|null $valueDataType - one of \PDO::PARAM_* or null for autodetection (detects bool, null, string only)
     * @return string
     */
    public function quoteValue($value, ?int $valueDataType = null): string;
    
    public function quoteDbExpr(DbExpr $expression): string;
    
    /**
     * Does DB supports table schemas?
     * Postgres - yes: "public"."table_name"; Mysql - no
     * @return bool
     */
    public function isDbSupportsTableSchemas(): bool;
    
    /**
     * Get default DB table schema
     * @return string|null - null: for DB Adapters that does not support table schemas
     */
    public function getDefaultTableSchema(): ?string;
    
    /**
     * @param string $operator
     * @param array|float|int|string|bool|null|DbExpr $value
     * @return string
     */
    public function convertConditionOperator(string $operator, $value): string;
    
    /**
     * Assemble value for condition
     * @param string|array|int|float|bool|DbExpr|null $value
     * @param string $operator
     * @param bool $valueAlreadyQuoted
     * @return string
     */
    public function assembleConditionValue($value, string $operator, bool $valueAlreadyQuoted = false): string;
    
    /**
     * Assemble condition from prepared parts
     * @param string $quotedColumn
     * @param string $operator
     * @param string|array|int|float|bool|DbExpr|null $rawValue
     * @param bool $valueAlreadyQuoted
     * @return string
     */
    public function assembleCondition(string $quotedColumn, string $operator, $rawValue, bool $valueAlreadyQuoted = false): string;
    
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
     * @return array
     */
    public function select(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array;
    
    /**
     * Select many records form DB by compiling simple query from passed parameters returning an array with values for
     * specified $column.
     * The query is something like: "SELECT $column FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $column
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     */
    public function selectColumn(string $table, $column, ?DbExpr $conditionsAndOptions = null): array;
    
    /**
     * Select many records form DB by compiling simple query from passed parameters returning an associative array.
     * The query is something like: "SELECT $keysColumn, $valuesColumn FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $keysColumn
     * @param string|DbExpr $valuesColumn
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     */
    public function selectAssoc(string $table, $keysColumn, $valuesColumn, ?DbExpr $conditionsAndOptions = null): array;
    
    /**
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     */
    public function selectOne(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array;
    
    /**
     * Select a value form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $expression FROM $table $conditionsAndOptions"
     * @param string $table
     * @param DbExpr $expression - something like "COUNT(*)" or anything else
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return string|null|int|bool|float
     */
    public function selectValue(string $table, DbExpr $expression, ?DbExpr $conditionsAndOptions = null);
    
    /**
     * Make a simple SELECT query from passed parameters
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return string - something like: "SELECT $columns FROM $table $conditionsAndOptions"
     */
    public function makeSelectQuery(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): string;
    
    /**
     * Get table description from DB
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     * @return TableDescription
     */
    public function describeTable(string $table, ?string $schema = null): TableDescription;
    
    /**
     * Search for $table in $schema
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     * @return bool
     */
    public function hasTable(string $table, ?string $schema = null): bool;
    
    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT') and used for updates and inserts
     * Note: throw exception if adapter does not support this feature
     * @return DbExpr
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr;
    
}
