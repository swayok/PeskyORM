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
     * @return \PDOStatement
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
     * @return bool|array - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function insert($table, array $data, array $dataTypes = [], $returning = false);

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
     * @return int - number of modified rows
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function update($table, array $data, $conditions, array $dataTypes = []);

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
    public function quoteName($name);
    
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
    public function replaceDbExprQuotes(DbExpr $expression);

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
    
}