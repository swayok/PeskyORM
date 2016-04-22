<?php

namespace PeskyORM\Core;

use Swayok\Utils\Utils;

abstract class DbAdapter implements DbAdapterInterface {

    const DEFAULT_DB_PORT_NUMBER = '';

    const VALUE_QUOTES = '';
    const NAME_QUOTES = '';

    // db-specific values for bool data type
    const BOOL_TRUE = '1';
    const BOOL_FALSE = '0';

    // db-specific value for unlimited amount of query results (ex: SELECT .. OFFSET 10 LIMIT 0)
    const NO_LIMIT = '0';

    /**
     * Traces of all transactions (required for debug)
     * @var array
     */
    protected static $transactionsTraces = [];

    /**
     * Enables/disables collecting of transactions traces
     * @var bool
     */
    protected static $isTransactionTracesEnabled = false;

    /**
     * @var DbConnectionConfigInterface
     */
    protected $connectionConfig;

    /**
     * @var \PDO $pdo
     */
    protected $pdo;

    /**
     * Class that wraps PDO connection. Used for debugging
     * function (DbAdapter $adapter, \PDO $pdo) { return $wrappedPdo; }
     *
     * @var null|callable
     */
    static protected $connectionWrapper = null;

    /**
     * Last executed query
     * @var null|string
     */
    protected $lastQuery;

    /**
     * Set a wrapper to PDO connection. Wrapper called on any new DB connection
     * @param callable $wrapper
     */
    static public function setConnectionWrapper(callable $wrapper) {
        static::$connectionWrapper = $wrapper;
    }

    /**
     * Remove PDO connection wrapper. This does not unwrap existing PDO objects
     */
    static public function unsetConnectionWrapper() {
        static::$connectionWrapper = null;
    }

    /**
     * @param DbConnectionConfigInterface $connectionConfig
     */
    public function __construct(DbConnectionConfigInterface $connectionConfig) {
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * Connect to DB once
     * @return \PDO
     * @throws \PDOException
     */
    public function getConnection() {
        if ($this->pdo === null) {
            $this->pdo = $this->makePdo();
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->wrapConnection();
        }
        return $this->pdo;
    }

    /**
     * @return DbConnectionConfigInterface
     */
    public function getConnectionConfig() {
        return $this->connectionConfig;
    }

    /**
     * Create \PDO object
     * @return \PDO
     * @throws \PDOException
     */
    protected function makePdo() {
        return new \PDO(
            $this->connectionConfig->getPdoConnectionString(),
            $this->connectionConfig->getUserName(),
            $this->connectionConfig->getUserPassword(),
            $this->connectionConfig->getOptions()
        );
    }

    public function disconnect() {
        $this->pdo = null;
        return $this;
    }

    /**
     * Wrap PDO connection if wrapper is provided
     * @throws \PDOException
     */
    private function wrapConnection() {
        if (is_callable(static::$connectionWrapper)) {
            $this->pdo = call_user_func(static::$connectionWrapper, $this, $this->getConnection());
        }
    }

    /**
     * Get last executed query
     * @return null|string
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }

    /**
     * Enable/disable tracing of transactions
     * Use when you have problems related to transactions
     * @param bool $enable = true: enable; false: disable
     * @return $this
     */
    static public function enableTransactionTraces($enable = true) {
        static::$isTransactionTracesEnabled = $enable;
    }

    /**
     * @param string|DbExpr $query
     * @return \PDOStatement
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function query($query) {
        if ($query instanceof DbExpr) {
            $query = $this->replaceDbExprQuotes($query->get());
        }
        $this->lastQuery = $query;
        try {
            return $this->getConnection()->query($query);
        } catch (\PDOException $exc) {
            throw $this->getDetailedException($query);
        }
    }

    /**
     * @param string|DbExpr $query
     * @param array $returning - list of QUOTED DB fields to return from statement. Must be resolved by
     * @return int|array = array: returned if $returning argument is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function exec($query) {
        if ($query instanceof DbExpr) {
            $query = $this->replaceDbExprQuotes($query->get());
        }
        $this->lastQuery = $query;
        try {
            $affectedRowsCount = $this->getConnection()->exec($query);
            if (!$affectedRowsCount || !is_int($affectedRowsCount)) {
                $exc = $this->getDetailedException($query);
                if ($exc !== null) {
                    throw $exc;
                }
            }
            return $affectedRowsCount;
        } catch (\PDOException $exc) {
            throw $this->getDetailedException($query);
        }
    }

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
     * @throws \PeskyORM\Core\DbException
     */
    public function insert($table, array $data, array $dataTypes = [], $returning = false) {
        if (empty($table) || !is_string($table)) {
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a string');
        }
        if (empty($data)) {
            throw new \InvalidArgumentException('$data argument cannot be empty');
        }
        if (!is_array($returning) && !is_bool($returning)) {
            throw new \InvalidArgumentException('$returning argument must be array or boolean');
        }
        $columns = array_keys($data);
        $query = 'INSERT INTO ' . $this->quoteName($table) . ' ' . $this->buildColumnsList($columns) 
                 . ' VALUES ' . $this->buildValuesList($columns, $data, $dataTypes);
        if (empty($returning)) {
            $rowsAffected = $this->exec($query);
            if (!$rowsAffected) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: " . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            }
            return true;
        } else {
            $data = $this->resolveQueryWithReturningColumns($query, $returning);
            if (!is_array($data)) {
                throw new DbException(
                    'DB Adapter [' . get_class($this) . '] returned non-array from resolveQueryWithReturningColumns()',
                    DbException::CODE_ADAPTER_IMPLEMENTATION_PROBLEM
                );
            }
            return $data;
        }
    }

    /**
     * @param array $columns - expected set of columns
     * @param array $valuesAssoc - key-value array where keys = columns
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     * @return string - "('value1','value2',...)"
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function buildValuesList(array $columns, array $valuesAssoc, array $dataTypes = []) {
        $ret = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $valuesAssoc)) {
                throw new \InvalidArgumentException(
                    "\$valuesAssoc array does not contain key [$column]. Data: " . print_r($valuesAssoc, true)
                );
            }
            $ret[] = $this->quoteValue($valuesAssoc[$column], empty($dataTypes[$column]) ? null : $dataTypes[$column]);
        }
        return '(' . implode(',', $ret) . ')';
    }

    /**
     * @param array $columns
     * @return string - "(`column1','column2',...)"
     * @throws \InvalidArgumentException
     */
    protected function buildColumnsList(array $columns) {
        $quoted = array_map(function ($column) {
            return $this->quoteName($column);
        }, $columns);
        return '(' . implode(',', $quoted) . ')';
    }

    /**
     * This method should resolve RETURNING functionality and return requested data
     * @param string $query - DB query to execute
     * @param array|bool $returning - @see insert()
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function resolveQueryWithReturningColumns($query, $returning) {
        throw new \InvalidArgumentException('DB Adapter [' . get_class($this) . '] does not support RETURNING functionality');
    }

    /**
     * @return bool
     * @throws \PDOException
     */
    public function inTransaction() {
        return $this->getConnection()->inTransaction();
    }

    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \PeskyORM\Core\DbException
     */
    public function begin($readOnly = false, $transactionType = null) {
        $this->guardTransaction('begin');
        try {
            $this->getConnection()->beginTransaction();
            static::rememberTransactionTrace();
        } catch (\PDOException $exc) {
            static::rememberTransactionTrace('failed');
            throw $exc;
        }
        return $this;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Core\DbException
     * @throws \PDOException
     */
    public function commit() {
        $this->guardTransaction('commit');
        $this->getConnection()->commit();
        return $this;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Core\DbException
     * @throws \PDOException
     */
    public function rollBack() {
        $this->guardTransaction('rollback');
        $this->getConnection()->rollBack();
        return $this;
    }

    /**
     * @param string $action = begin|commit|rollback
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Core\DbException
     * @throws \PDOException
     */
    protected function guardTransaction($action) {
        switch ($action) {
            case 'begin':
                if ($this->inTransaction()) {
                    static::rememberTransactionTrace('failed');
                    throw new DbException(
                        'Already in transaction: ' . Utils::printToStr(static::$transactionsTraces),
                        DbException::CODE_TRANSACTION_BEGIN_FAIL
                    );
                }
                break;
            case 'commit':
                if (!$this->inTransaction()) {
                    static::rememberTransactionTrace('failed');
                    throw new DbException(
                        'Attempt to commit not started transaction: ' . Utils::printToStr(static::$transactionsTraces),
                        DbException::CODE_TRANSACTION_COMMIT_FAIL
                    );
                }
                break;
            case 'rollback':
                if (!$this->inTransaction()) {
                    static::rememberTransactionTrace('failed');
                    throw new DbException(
                        'Attempt to rollback not started transaction: ' . Utils::printToStr(static::$transactionsTraces),
                        DbException::CODE_TRANSACTION_ROLLBACK_FAIL
                    );
                }
                break;
            default:
                throw new \InvalidArgumentException('$action argument must be one of: "begin", "commit", "rollback"');
        }
    }

    /**
     * Remember transaction trace
     * @param null|string $key - array key for this trace
     */
    static protected function rememberTransactionTrace($key = null) {
        if (static::$isTransactionTracesEnabled) {
            $trace = Utils::getBackTrace(true, false, true, 2);
            if ($key) {
                static::$transactionsTraces[$key] = $trace;
            } else {
                static::$transactionsTraces[] = $trace;
            }
        }
    }

    /**
     * Make detailed exception from last pdo error
     * @param string $query - failed query
     * @param null|\PDOStatement|\PDO $pdoStatement
     * @return \PDOException or null if no error
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    private function getDetailedException($query, $pdoStatement = null) {
        $errorInfo = $this->getPdoError($pdoStatement);
        if ($errorInfo['message'] === null) {
            return null;
        }
        if (preg_match('%syntax error at or near "\$\d+"%i', $errorInfo[2])) {
            $errorInfo['message'] .= "\n NOTE: PeskyORM do not use prepared statements. You possibly used one of Postgresql jsonb opertaors - '?', '?|' or '?&'."
                . ' You should use alternative functions: jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text) or jsonb_exists_all(jsonb, text) respectively';
        }
        return new \PDOException($errorInfo['message'] . ". \nQuery: " . $query, $errorInfo['code']);
    }

    /**
     * @param null|\PDOStatement|\PDO $pdoStatement
     * @return array
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function getPdoError($pdoStatement = null) {
        $ret = [];
        if (empty($pdoStatement)) {
            $pdoStatement = $this->getConnection();
        } else if (!($pdoStatement instanceof \PDOStatement) && !($pdoStatement instanceof \PDO)) {
            throw new \InvalidArgumentException('$pdoStatement argument should be instance of \PDOStatement or \PDO');
        }
        list($ret['sql_code'], $ret['code'], $ret['message']) = $pdoStatement->errorInfo();
        return $ret;
    }

    /**
     * Quote DB entity name (column, table, alias, schema)
     * @param string|array $name - array: list of names to quote.
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     * @throws \InvalidArgumentException
     */
    public function quoteName($name) {
        if (!preg_match('%^[a-zA-Z_]+(\.a-zA-Z_)?%i', $name)) {
            throw new \InvalidArgumentException("Invalid db entity name [$name]");
        }
        return static::NAME_QUOTES
               . str_replace('.', static::NAME_QUOTES . '.' . static::NAME_QUOTES, $name)
               . static::NAME_QUOTES;
    }

    /**
     * Quote passed value
     * @param mixed $value
     * @param int|null $valueDataType - one of \PDO::PARAM_* or null for autodetection (detects bool, null, string only)
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function quoteValue($value, $valueDataType = null) {
        if ($value instanceof DbExpr) {
            return $this->replaceDbExprQuotes($value->get());
        } else {
            if ($value === null || $valueDataType === \PDO::PARAM_NULL) {
                return 'NULL';
            } else if ((empty($valueDataType) && is_bool($value)) || $valueDataType === \PDO::PARAM_BOOL) {
                return $value ? static::BOOL_TRUE : static::BOOL_FALSE;
            }
            if (empty($valueDataType)) {
                if (is_int($value)) {
                    $valueDataType = \PDO::PARAM_INT;
                } else {
                    $valueDataType = \PDO::PARAM_STR;
                }
            } else if ($valueDataType === \PDO::PARAM_INT) {
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $value = (int) $value;
                } else {
                    if (is_string($value)) {
                        $realType = "String [$value]";
                    } else if (is_array($value)) {
                        $realType = 'Array';
                    } else if (is_object($value)) {
                        $realType = 'Object fo class [\\' . get_class($value) . ']';
                    } else if (is_bool($value)) {
                        $realType = 'Boolean [' . ($value ? 'true' : 'false') . ']';
                    } else if (is_resource($value)) {
                        $realType = 'Resource';
                    } else if (is_callable($value)) {
                        $realType = 'Callable';
                    } else {
                        $realType = 'Value of unknown type';
                    }
                    throw new \InvalidArgumentException("\$value expected to be integer or numeric string. $realType received");
                }
            }
            if (!in_array($valueDataType, [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_LOB], true)) {
                throw new \InvalidArgumentException('Value in $fieldType argument must be a constant like \PDO::PARAM_*');
            }
            if (is_array($value)) {
                $value = static::serializeArray($value);
            }
            return $this->getConnection()->quote($value, $valueDataType);
        }
    }

    /**
     * Convert passed $array to string compatible with sql query
     * @param mixed $array
     * @return string
     */
    static public function serializeArray($array) {
        return json_encode($array);
    }

    /**
     * @param string $expression
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function replaceDbExprQuotes($expression) {
        $expression = preg_replace_callback('%``(.*?)``%s', function ($matches) {
            return $this->quoteValue($matches[1]);
        }, $expression);
        $expression = preg_replace_callback('%`(.*?)`%s', function ($matches) {
            return $this->quoteName($matches[1]);
        }, $expression);
        return $expression;
    }

}