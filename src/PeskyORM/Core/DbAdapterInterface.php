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
     * @return \PDOStatement
     */
    public function query($query);

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
     */
    public function insert($table, array $data, array $dataTypes = [], $returning = false);
    
    /**
     * @return bool
     */
    public function inTransaction();
    
    /**
     * @return $this
     */
    public function begin();
    
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
     * @param string|DbExpr $expression
     * @return string
     */
    public function replaceDbExprQuotes($expression);
    
}