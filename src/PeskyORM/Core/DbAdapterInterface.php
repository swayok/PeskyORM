<?php

namespace PeskyORM\Core;

interface DbAdapterInterface {

    /**
     * Connect to DB once
     * @return $this
     */
    public function getConnection();

    /**
     * @return $this
     */
    public function disconnect();

    /**
     * Get last executed query
     * @return null|string
     */
    public function getLastQuery();

     /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     */
    public function exec($query);

    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|array
     */
    public function query($query, $fetchData = null);

    /**
     * @param string $table
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     *          Data type is one of \PDO::PARAM_* contants or null.
     *          If value is null or column not present - value quoter will autodetect column type (see quoteValue())
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @param string $pkName
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function insert($table, array $data, array $dataTypes = [], $returning = false, $pkName = 'id');

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
     *          - array: list of columns to return values for
     * @param string $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return bool|array - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function insertMany($table, array $columns, array $data, array $dataTypes = [], $returning = false, $pkName = 'id');

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
     *          - array: list of columns to return values for
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function update($table, array $data, $conditions, array $dataTypes = [], $returning = false);

    /**
     * @param string $table
     * @param string|DbExpr $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return int|array - int: number of deleted records | array: returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function delete($table, $conditions, $returning = false);
    
    /**
     * @return bool
     */
    public function inTransaction();

    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     * @return $this
     */
    public function begin($readOnly = false, $transactionType = null);
    
    /**
     * @return $this
     */
    public function commit();
    
    /**
     * @return $this
     */
    public function rollBack();

    /**
     * @param null|\PDOStatement|\PDO $pdoStatement $pdoStatement - if null: $this->getConnection() will be used
     * @return array
     */
    public function getPdoError($pdoStatement = null);
    
    /**
     * Quote DB entity name (column, table, alias, schema)
     * @param string|array $name - array: list of names to quote.
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     */
    public function quoteDbEntityName($name);

    /**
     * Test if $name matches a DB entity naming rules or it is a JSON selector
     * @param string $name
     * @param bool $canBeAJsonSelector - test if $name contains a JSON selector like 'col_name -> json_key'
     * @return bool
     */
    static public function isValidDbEntityName($name, $canBeAJsonSelector = true);

    /**
     * Quote passed value
     * @param mixed $value
     * @param int|null $valueDataType - one of \PDO::PARAM_* or null for autodetection (detects bool, null, string only)
     * @return string
     */
    public function quoteValue($value, $valueDataType = null);
    
    /**
     * @param DbExpr $expression
     * @return string
     */
    public function quoteDbExpr(DbExpr $expression);

    /**
     * Does DB supports table schemas?
     * Postgres - yes: "public"."table_name"; Mysql - no
     * @return bool
     */
    public function isDbSupportsTableSchemas();

    /**
     * Get default DB table schema
     * @return string|null - null: for DB Adapters that does not support table schemas
     */
    public function getDefaultTableSchema();

    /**
     * @param string $operator
     * @param string|array|int|float $value
     * @return string
     * @throws \InvalidArgumentException
     */
    public function convertConditionOperator($operator, $value);

    /**
     * @param string|array|int|float $value
     * @param string $operator
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function assembleConditionValue($value, $operator);

    /**
     * Assemble condition from prepared parts
     * @param string $quotedColumn
     * @param string $operator
     * @param mixed $rawValue
     * @param bool $valueAlreadyQuoted
     * @return string
     */
    public function assembleCondition($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted = false);

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
    public function addDataTypeCastToExpression($dataType, $expression);

    /**
     * Select many records form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     * @throws \InvalidArgumentException
     */
    public function select($table, array $columns = [], $conditionsAndOptions = null);

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an array with values for
     * specified $column.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $column
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     * @throws \InvalidArgumentException
     */
    public function selectColumn($table, $column, $conditionsAndOptions = null);

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an associative array.
     * The query is something like: "SELECT $keysColumn, $valuesColumn FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     * @throws \InvalidArgumentException
     */
    public function selectAssoc($table, $keysColumn, $valuesColumn, $conditionsAndOptions = null);

    /**
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     * @throws \InvalidArgumentException
     */
    public function selectOne($table, array $columns = [], $conditionsAndOptions = null);

    /**
     * Select a value form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $expression FROM $table $conditionsAndOptions"
     * @param string $table
     * @param DbExpr $expression - something like "COUNT(*)" or anything else
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     * @throws \InvalidArgumentException
     */
    public function selectValue($table, DbExpr $expression, $conditionsAndOptions = null);

    /**
     * Make a simple SELECT query from passed parameters
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return string - something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function makeSelectQuery($table, array $columns = [], $conditionsAndOptions = null);

    /**
     * Get table description from DB
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     * @return DbTableDescription
     */
    public function describeTable($table, $schema = null);

    /**
     * Return DbExpr to set default value for a column.
     * Example for MySQL and PostgreSQL: DbExpr::create('DEFAULT') and used for updates and inserts
     * Note: throw exception if adapter does not support this feature
     * @return DbExpr
     */
    static public function getExpressionToSetDefaultValueForAColumn();

}