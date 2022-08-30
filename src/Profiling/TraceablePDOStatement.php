<?php

declare(strict_types=1);

namespace PeskyORM\Profiling;

use PDO;
use PDOStatement;

/**
 * A traceable PDO statement to use with Traceablepdo
 */
class TraceablePDOStatement extends PDOStatement
{
    
    protected PDO $pdo;
    
    protected array $boundParameters = [];
    
    /**
     * TraceablePDOStatement constructor.
     *
     * @param TraceablePDO $pdo
     */
    protected function __construct(TraceablePDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Bind a column to a PHP variable
     *
     * @link   http://php.net/manual/en/pdostatement.bindcolumn.php
     * @param mixed $column Number of the column (1-indexed) or name of the column in the result set
     * @param mixed $var Name of the PHP variable to which the column will be bound.
     * @param int|null $type [optional] Data type of the parameter, specified by the PDO::PARAM_*
     * constants.
     * @param int|null $maxLength [optional] A hint for pre-allocation.
     * @param mixed|null $driverOptions [optional] Optional parameter(s) for the driver.
     * @return bool  TRUE on success or FALSE on failure.
     */
    public function bindColumn($column, mixed &$var, int $type = null, int $maxLength = null, mixed $driverOptions = null): bool
    {
        $this->boundParameters[$column] = $var;
        $args = array_merge([$column, &$var], array_slice(func_get_args(), 2));
        
        return call_user_func_array(["parent", 'bindColumn'], $args);
    }
    
    /**
     * Binds a parameter to the specified variable name
     *
     * @link   http://php.net/manual/en/pdostatement.bindparam.php
     * @param mixed $param Parameter identifier. For a prepared statement using named
     * placeholders, this will be a parameter name of the form :name. For a prepared statement using
     * question mark placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $var Name of the PHP variable to bind to the SQL statement parameter.
     * @param int $type [optional] Explicit data type for the parameter using the PDO::PARAM_*
     * constants.
     * @param int|null $maxLength [optional] Length of the data type. To indicate that a parameter is an OUT
     * parameter from a stored procedure, you must explicitly set the length.
     * @param mixed|null $driverOptions [optional]
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindParam($param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = null, mixed $driverOptions = null): bool
    {
        $this->boundParameters[$param] = $var;
        $args = array_merge([$param, &$var], array_slice(func_get_args(), 2));
        
        return call_user_func_array(["parent", 'bindParam'], $args);
    }
    
    /**
     * Binds a value to a parameter
     *
     * @link   http://php.net/manual/en/pdostatement.bindvalue.php
     * @param int|string $param Parameter identifier. For a prepared statement using named
     * placeholders, this will be a parameter name of the form :name. For a prepared statement using
     * question mark placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter.
     * @param int $type [optional] Explicit data type for the parameter using the PDO::PARAM_*
     * constants.
     * @return bool TRUE on success or FALSE on failure.
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundParameters[$param] = $value;
        
        return call_user_func_array(["parent", 'bindValue'], func_get_args());
    }
    
    /**
     * Executes a prepared statement
     *
     * @link   http://php.net/manual/en/pdostatement.execute.php
     * @param array|null $params [optional] An array of values with as many elements as there
     * are bound parameters in the SQL statement being executed. All values are treated as
     * PDO::PARAM_STR.
     * @return bool TRUE on success or FALSE on failure.
     * @throws \PDOException
     */
    public function execute(?array $params = null): bool
    {
        $preparedId = spl_object_hash($this);
        $boundParameters = $this->boundParameters;
        if (is_array($params)) {
            $boundParameters = array_merge($boundParameters, $params);
        }
        
        $trace = new TracedStatement($this->queryString, $boundParameters, $preparedId);
        $trace->start();
        
        $exc = $result = null;
        try {
            $result = parent::execute($params);
        } catch (\PDOException $e) {
            $exc = $e;
        }
        
        if ($result === false && $this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            $error = $this->errorInfo();
            $exc = new \PDOException($error[2], (int)$error[0]);
        }
        
        $trace->end($exc, $this->rowCount());
        $this->pdo->addExecutedStatement($trace);
        
        if ($exc !== null && $this->pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION) {
            throw $exc;
        }
        
        return $result;
    }
}