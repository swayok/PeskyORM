<?php

declare(strict_types=1);

namespace PeskyORM\Profiling;

use PDO;

/**
 * A PDO proxy which traces statements
 */
class TraceablePDO extends PDO
{
    
    protected PDO $pdo;
    
    /** @var TracedStatement[] */
    protected array $executedStatements = [];
    
    /**
     * @param PDO $pdo
     * @param null|string $databaseName
     * @noinspection PhpMissingParentConstructorInspection
     * @noinspection MagicMethodsValidityInspection
     */
    public function __construct(PDO $pdo, ?string $databaseName = null)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [TraceablePDOStatement::class, [$this]]);
        PeskyOrmPdoProfiler::addConnection($this, $databaseName);
    }
    
    /**
     * Initiates a transaction
     *
     * @link   http://php.net/manual/en/pdo.begintransaction.php
     * @return bool TRUE on success or FALSE on failure.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commits a transaction
     *
     * @link   http://php.net/manual/en/pdo.commit.php
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Fetch extended error information associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return array PDO::errorInfo returns an array of error information
     */
    public function errorCode(): array
    {
        return $this->pdo->errorCode();
    }
    
    /**
     * Fetch extended error information associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return array PDO::errorInfo returns an array of error information
     */
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }
    
    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @link   http://php.net/manual/en/pdo.exec.php
     * @param string $statement
     * @return int|bool PDO::exec returns the number of rows that were modified or deleted by the
     * SQL statement you issued. If no rows were affected, PDO::exec returns 0. This function may
     * return Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE.
     * Please read the section on Booleans for more information
     */
    public function exec($statement)
    {
        return $this->profileCall('exec', $statement, func_get_args());
    }
    
    /**
     * Retrieve a database connection attribute
     *
     * @link   http://php.net/manual/en/pdo.getattribute.php
     * @param int $attribute One of the PDO::ATTR_* constants
     * @return mixed A successful call returns the value of the requested PDO attribute.
     * An unsuccessful call returns null.
     */
    public function getAttribute($attribute)
    {
        return $this->pdo->getAttribute($attribute);
    }
    
    /**
     * Checks if inside a transaction
     *
     * @link   http://php.net/manual/en/pdo.intransaction.php
     * @return bool TRUE if a transaction is currently active, and FALSE if not.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @link   http://php.net/manual/en/pdo.lastinsertid.php
     * @param string $name [optional]
     * @return string|false If a sequence name was not specified for the name parameter, PDO::lastInsertId
     * returns a string representing the row ID of the last row that was inserted into the database.
     */
    public function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }
    
    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @link   http://php.net/manual/en/pdo.prepare.php
     * @param string $query This must be a valid SQL statement template for the target DB server.
     * @param array|null $options [optional] This array holds one or more key=&gt;value pairs to
     * set attribute values for the PDOStatement object that this method returns.
     * @return TraceablePDOStatement|false If the database server successfully prepares the statement,
     * PDO::prepare returns a PDOStatement object. If the database server cannot successfully prepare
     * the statement, PDO::prepare returns FALSE or emits PDOException (depending on error handling).
     */
    public function prepare($query, array $options = null)
    {
        /** @var TraceablePDOStatement|false $statement */
        $statement = $this->pdo->prepare($query, $options);
        return $statement;
    }
    
    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     *
     * @link http://php.net/manual/en/pdo.query.php
     * @param string $statement
     * @param int $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return TraceablePDOStatement|bool PDO::query returns a PDOStatement object, or FALSE on
     * failure.
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
    {
        return $this->profileCall('query', $statement, func_get_args());
    }
    
    /**
     * Quotes a string for use in a query.
     *
     * @link   http://php.net/manual/en/pdo.quote.php
     * @param string $string The string to be quoted.
     * @param int $type [optional] Provides a data type hint for drivers that have
     * alternate quoting styles.
     * @return string|bool A quoted string that is theoretically safe to pass into an SQL statement.
     * Returns FALSE if the driver does not support quoting in this way.
     */
    public function quote($string, $type = PDO::PARAM_STR)
    {
        return $this->pdo->quote($string, $type);
    }
    
    /**
     * Rolls back a transaction
     *
     * @link   http://php.net/manual/en/pdo.rollback.php
     * @return bool TRUE on success or FALSE on failure.
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Set an attribute
     *
     * @link   http://php.net/manual/en/pdo.setattribute.php
     * @param int $attribute
     * @param mixed $value
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setAttribute($attribute, $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }
    
    /**
     * Profiles a call to a PDO method
     *
     * @param string $method
     * @param string $sql
     * @param array $args
     * @return mixed  The result of the call
     * @throws \PDOException
     */
    protected function profileCall(string $method, string $sql, array $args)
    {
        $trace = new TracedStatement($sql);
        $trace->start();
        
        $ex = $result = null;
        try {
            $result = call_user_func_array([$this->pdo, $method], $args);
        } catch (\PDOException $e) {
            $ex = $e;
        }
        
        if ($result === false && $this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            $error = $this->pdo->errorInfo();
            $ex = new \PDOException($error[2], $error[0]);
        }
        
        $trace->end($ex, $result instanceof \PDOStatement ? $result->rowCount() : 0);
        $this->addExecutedStatement($trace);
        
        if ($ex !== null && $this->pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION) {
            throw $ex;
        }
        
        return $result;
    }
    
    /**
     * Adds an executed TracedStatement
     */
    public function addExecutedStatement(TracedStatement $stmt): void
    {
        $this->executedStatements[] = $stmt;
    }
    
    /**
     * Returns the accumulated execution time of statements
     */
    public function getAccumulatedStatementsDuration(): int
    {
        return array_reduce($this->executedStatements, function ($duration, $statement) {
            /** @var $statement TracedStatement */
            return $duration + $statement->getDuration();
        });
    }
    
    /**
     * Returns overall memory usage after performing all statements
     */
    public function getMemoryUsage(): int
    {
        return array_reduce($this->executedStatements, function ($memoryUsed, $statement) {
            /** @var $statement TracedStatement */
            return $memoryUsed + $statement->getMemoryUsage();
        });
    }
    
    /**
     * Returns the peak memory usage while performing statements
     */
    public function getPeakMemoryUsage(): int
    {
        return array_reduce($this->executedStatements, function ($maxMemoryUsed, $statement) {
            /** @var $statement TracedStatement */
            $memoryUsed = $statement->getEndMemory();
            return max($memoryUsed, $maxMemoryUsed);
        });
    }
    
    /**
     * Returns the list of executed statements as TracedStatement objects
     */
    public function getExecutedStatements(): array
    {
        return $this->executedStatements;
    }
    
    /**
     * Returns the list of failed statements
     */
    public function getFailedExecutedStatements(): array
    {
        return array_filter($this->executedStatements, function ($statement) {
            return !$statement->isSuccess();
        });
    }
    
    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->pdo->$name;
    }
    
    /** @noinspection MagicMethodsValidityInspection */
    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value)
    {
        $this->pdo->$name = $value;
    }
    
    /**
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        return call_user_func_array([$this->pdo, $name], $args);
    }
}