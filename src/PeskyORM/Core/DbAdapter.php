<?php

namespace PeskyORM\Core;

use PeskyORM\Exception\DbException;
use Swayok\Utils\Utils;

abstract class DbAdapter implements DbAdapterInterface {

    const DEFAULT_DB_PORT_NUMBER = '';

    const ENTITY_NAME_QUOTES = '';

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
     * @var array
     */
    protected $onConnectCallbacks = [];

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
            $this->runOnConnectCallbacks($this->onConnectCallbacks);
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
     * Run $callback when DB connection created (or right now if connection already established)
     * @param \Closure $callback
     * @param null|string $code - callback code to prevent duplicate usage
     * @return $this
     */
    public function onConnect(\Closure $callback, $code = null) {
        $run = $this->pdo !== null;
        if (!$code) {
            $this->onConnectCallbacks[] = $callback;
        } else if (!isset($this->onConnectCallbacks[$code])) {
            $this->onConnectCallbacks[$code] = $callback;
        } else {
            $run = false;
        }
        if ($run) {
            $this->runOnConnectCallbacks([$callback]);
        }
        return $this;
    }

    /**
     * @param array $callbacks
     */
    protected function runOnConnectCallbacks(array $callbacks) {
        foreach ($callbacks as $callback) {
            $callback($this);
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
     */
    static public function enableTransactionTraces($enable = true) {
        static::$isTransactionTracesEnabled = $enable;
    }

    /**
     * @return DbExpr
     */
    static public function getExpressionToSetDefaultValueForAColumn() {
        return DbExpr::create('DEFAULT', false);
    }

    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|mixed
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function query($query, $fetchData = null) {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $stmnt = $this->getConnection()->query($query);
            if (!$fetchData) {
                return $stmnt;
            } else {
                return \PeskyORM\Core\Utils::getDataFromStatement($stmnt, $fetchData);
            }
        } catch (\PDOException $exc) {
            $exc = $this->getDetailedException($query, null, $exc);
            if ($this->inTransaction()) {
                $this->rollBack(); //< error within transactions makes it broken in postgresql
            }
            throw $exc;
        }
    }

    /**
     * @param string|DbExpr $query
     * @param bool $ignoreZeroModifiedRows - true: will not try to additionally validate if query failed
     * @return array|int = array: returned if $returning argument is not empty
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    protected function _exec($query, $ignoreZeroModifiedRows = false) {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $affectedRowsCount = $this->getConnection()->exec($query);
            if (!$ignoreZeroModifiedRows && !$affectedRowsCount && !is_int($affectedRowsCount)) {
                $exc = $this->getDetailedException($query);
                if ($exc !== null) {
                    throw $exc;
                }
            }
            return $affectedRowsCount;
        } catch (\PDOException $exc) {
            $exc = $this->getDetailedException($query, null, $exc);
            if ($this->inTransaction() && stripos($query, 'ROLLBACK') !== 0) {
                $this->rollBack(); //< error within transactions makes it broken in postgresql
            }
            throw $exc;
        }
    }

    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function exec($query) {
        return $this->_exec($query);
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
     * @param string $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @return bool|array - array returned only if $returning is not empty
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\DbException
     */
    public function insert($table, array $data, array $dataTypes = [], $returning = false, $pkName = 'id') {
        $this->guardTableNameArg($table);
        $this->guardDataArg($data);
        $this->guardReturningArg($returning);
        if ($returning) {
            $this->guardPkNameArg($pkName);
        }
        $columns = array_keys($data);
        $query = 'INSERT INTO ' . $this->quoteDbEntityName($table) . ' ' . $this->buildColumnsList($columns)
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
            $record = $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                $columns,
                $data,
                $dataTypes,
                $returning,
                $pkName,
                'insert'
            );
            if (!is_array($record)) {
                throw new DbException(
                    'DB Adapter [' . get_class($this) . '] returned non-array from resolveQueryWithReturningColumns()',
                    DbException::CODE_ADAPTER_IMPLEMENTATION_PROBLEM
                );
            }
            return $record;
        }
    }

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
     * @throws \PeskyORM\Exception\DbException
     */
    public function insertMany($table, array $columns, array $data, array $dataTypes = [], $returning = false, $pkName = 'id') {
        $this->guardTableNameArg($table);
        $this->guardColumnsArg($columns, false);
        $this->guardDataArg($data);
        $this->guardReturningArg($returning);
        if ($returning) {
            $this->guardPkNameArg($pkName);
        }
        $query = 'INSERT INTO ' . $this->quoteDbEntityName($table) . ' ' . $this->buildColumnsList($columns) . ' VALUES ';
        foreach ($data as $key => $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException(
                    "\$data argument must contain only arrays. Non-array received at index [$key]"
                );
            }
            $query .= $this->buildValuesList($columns, $record, $dataTypes, $key) . ',';
        }
        $query = rtrim($query, ', ');
        if (empty($returning)) {
            $rowsAffected = $this->exec($query);
            if (!$rowsAffected) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: " . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            } else if (count($data) !== $rowsAffected) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of $rowsAffected rows while "
                        . count($data). ' rows should be inserted. Query: ' . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            }
            return true;
        } else {
            $records = $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                $columns,
                $data,
                $dataTypes,
                $returning,
                $pkName,
                'insert_many'
            );
            if (!is_array($records)) {
                throw new DbException(
                    'DB Adapter [' . get_class($this) . '] returned non-array from resolveQueryWithReturningColumns()',
                    DbException::CODE_ADAPTER_IMPLEMENTATION_PROBLEM
                );
            }
            return $records;
        }
    }

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
     * @throws \PeskyORM\Exception\DbException
     */
    public function update($table, array $data, $conditions, array $dataTypes = [], $returning = false) {
        $this->guardTableNameArg($table);
        $this->guardDataArg($data);
        $this->guardConditionsArg($conditions);
        $query = 'UPDATE ' . $this->quoteDbEntityName($table)  . ' SET ' . $this->buildValuesListForUpdate($data, $dataTypes)
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        } else {
            $records = $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                array_keys($data),
                $data,
                $dataTypes,
                $returning,
                null,
                'update'
            );
            if (!is_array($records)) {
                throw new DbException(
                    'DB Adapter [' . get_class($this) . '] returned non-array from resolveQueryWithReturningColumns()',
                    DbException::CODE_ADAPTER_IMPLEMENTATION_PROBLEM
                );
            }
            return $records;
        }
    }

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
     * @throws \PeskyORM\Exception\DbException
     */
    public function delete($table, $conditions, $returning = false) {
        $this->guardTableNameArg($table);
        $this->guardConditionsArg($conditions);
        $this->guardReturningArg($returning);
        $query = 'DELETE FROM ' . $this->quoteDbEntityName($table)
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        } else {
            $records = $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                [],
                [],
                [],
                $returning,
                null,
                'delete'
            );
            if (!is_array($records)) {
                throw new DbException(
                    'DB Adapter [' . get_class($this) . '] returned non-array from resolveQueryWithReturningColumns()',
                    DbException::CODE_ADAPTER_IMPLEMENTATION_PROBLEM
                );
            }
            return $records;
        }
    }

    private function guardTableNameArg($table) {
        if (empty($table) || !is_string($table) || is_numeric($table)) {
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a non-numeric string');
        }
    }

    private function guardConditionsArg($conditions) {
        if (!is_string($conditions) && !($conditions instanceof DbExpr)) {
            throw new \InvalidArgumentException('$conditions argument must be a string or DbExpr object');
        } else if (empty($conditions)) {
            throw new \InvalidArgumentException(
                '$conditions argument is not allowed to be empty. Use "true" or "1 = 1" if you want to update all.'
            );
        }
    }

    private function guardConditionsAndOptionsArg($conditionsAndOptions) {
        if (!empty($conditionsAndOptions) && !($conditionsAndOptions instanceof DbExpr)) {
            throw new \InvalidArgumentException('$conditionsAndOptions argument must be an instance of DbExpr class');
        }
    }

    private function guardReturningArg($returning) {
        if (!is_array($returning) && !is_bool($returning)) {
            throw new \InvalidArgumentException('$returning argument must be array or boolean');
        }
    }

    private function guardPkNameArg($pkName) {
        if (empty($pkName)) {
            throw new \InvalidArgumentException('$pkName argument cannot be empty');
        } else if (!is_string($pkName)) {
            throw new \InvalidArgumentException('$pkName argument must be a string');
        }
        $this->quoteDbEntityName($pkName);
    }

    private function guardDataArg(array $data) {
        if (empty($data)) {
            throw new \InvalidArgumentException('$data argument cannot be empty');
        }
    }

    private function guardColumnsArg(array $columns, $allowDbExpr = true) {
        if (empty($columns)) {
            throw new \InvalidArgumentException('$columns argument cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) && (!$allowDbExpr || !($column instanceof DbExpr))) {
                throw new \InvalidArgumentException(
                    '$columns argument must contain only strings' . ($allowDbExpr ? ' and DbExpr objects' : '')
                );
            }
        }
    }

    /**
     * @param array $columns - should contain only strings and DbExpr objects
     * @param bool $withBraces - add "()" around columns list
     * @return string - "(`column1','column2',...)"
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function buildColumnsList(array $columns, $withBraces = true) {
        $quoted = implode(',', array_map(function ($column) {
            return ($column instanceof DbExpr) ? $this->quoteDbExpr($column) : $this->quoteDbEntityName($column);
        }, $columns));
        return $withBraces ? '(' . $quoted . ')' : $quoted;
    }

    /**
     * @param array $columns - expected set of columns
     * @param array $valuesAssoc - key-value array where keys = columns
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     * @param int $recordIdx - index of record (needed to make exception message more useful)
     * @return string - "('value1','value2',...)"
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function buildValuesList(array $columns, array $valuesAssoc, array $dataTypes = [], $recordIdx = 0) {
        $ret = [];
        if (empty($columns)) {
            throw new \InvalidArgumentException('$columns argument cannot be empty');
        }
        foreach ($columns as $column) {
            if (!array_key_exists($column, $valuesAssoc)) {
                throw new \InvalidArgumentException(
                    "\$valuesAssoc array does not contain key [$column]. Record index: $recordIdx. "
                        . 'Data: ' . print_r($valuesAssoc, true)
                );
            }
            $ret[] = $this->quoteValue(
                $valuesAssoc[$column],
                empty($dataTypes[$column]) ? null : $dataTypes[$column]
            );
        }
        return '(' . implode(',', $ret) . ')';
    }

    /**
     * @param array $valuesAssoc - key-value array where keys = columns
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     * @return string - "col1" = 'val1', "col2" = 'val2'
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function buildValuesListForUpdate($valuesAssoc, array $dataTypes = []) {
        $ret = [];
        foreach ($valuesAssoc as $column => $value) {
            $quotedValue = $this->quoteValue(
                $valuesAssoc[$column],
                empty($dataTypes[$column]) ? null : $dataTypes[$column]
            );
            $ret[] = $this->quoteDbEntityName($column) . '=' . $quotedValue;
        }
        return implode(',', $ret);
    }

    /**
     * This method should resolve RETURNING functionality and return requested data
     * @param string $query - DB query to execute
     * @param $table
     * @param array $columns
     * @param array $data
     * @param array $dataTypes
     * @param array|bool $returning - @see insert()
     * @param $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @param string $operation - Name of operation to perform: 'insert', 'insert_many', 'update', 'delete'
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function resolveQueryWithReturningColumns(
        $query,
        $table,
        array $columns,
        array $data,
        array $dataTypes,
        $returning,
        $pkName,
        $operation
    ) {
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
     * @throws \PeskyORM\Exception\DbException
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
     * @throws \PeskyORM\Exception\DbException
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
     * @throws \PeskyORM\Exception\DbException
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
     * @throws \PeskyORM\Exception\DbException
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
     * @param null|\PDOException $originalException
     * @return \PDOException or null if no error
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    protected function getDetailedException($query, $pdoStatement = null, $originalException = null) {
        $errorInfo = $this->getPdoError($pdoStatement);
        if ($errorInfo['message'] === null) {
            return $originalException;
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
    public function quoteDbEntityName($name) {
        if (!is_string($name) || trim($name) === '') {
            throw new \InvalidArgumentException('Db entity name must be a not empty string');
        }
        if ($name === '*') {
            return '*';
        }
        if (!static::isValidDbEntityName($name)) {
            throw new \InvalidArgumentException("Invalid db entity name [$name]");
        }
        if (preg_match('%[-#]>%', $name)) {
            // we've got a json selector like 'Alias.col_name->json_key1' 'Alias.col_name ->>json_key1',
            // 'Alias.col_name #> json_key1', 'Alias.col_name#>> json_key1', 'Alias.col_name->json_key1->>json_key2'
            $parts = preg_split('%\s*([-#]>>?)\s*%', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
            return $this->quoteJsonSelectorExpression($parts);
        } else {
            return static::ENTITY_NAME_QUOTES
                . str_replace('.', static::ENTITY_NAME_QUOTES . '.' . static::ENTITY_NAME_QUOTES, $name)
                . static::ENTITY_NAME_QUOTES;
        }
    }

    /**
     * Quote a db entity name like 'table.col_name -> json_key1 ->> json_key2'
     * @param array $sequence -
     *      index 0: base entity name ('table.col_name' or 'col_name');
     *      indexes 1, 3, 5, ...: selection operator (->, ->>, #>, #>>);
     *      indexes 2, 4, 6, ...: json key name or other selector ('json_key1', 'json_key2')
     * @return string - quoted entity name and json selecor
     */
    abstract protected function quoteJsonSelectorExpression(array $sequence);

    /**
     * @param string $name
     * @param bool $canBeAJsonSelector
     * @return bool
     */
    static public function isValidDbEntityName($name, $canBeAJsonSelector = true) {
        return (
            $name === '*'
            || preg_match('%^[a-zA-Z_][a-zA-Z_0-9]*(\.[a-zA-Z_0-9]+|\.\*)?$%i', $name) > 0
            || ($canBeAJsonSelector && static::isValidJsonSelector($name))
        );
    }

    /**
     * @param string $name
     * @return bool
     */
    static public function isValidJsonSelector($name) {
        $parts = preg_split('%\s*[-#]>>?\s*%', $name);
        if (count($parts) < 2) {
            return false;
        }
        if (!static::isValidDbEntityName($parts[0], false)) {
            return false;
        }
        for ($i = 1, $max = count($parts); $i < $max; $i++) {
            if (trim($parts[$i], ' "`\'') === '') {
                return false;
            }
        }
        return true;
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
            return $this->quoteDbExpr($value);
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
     * @param DbExpr $expression
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function quoteDbExpr(DbExpr $expression) {
        $quoted = preg_replace_callback(
            '%``(.*?)``%s',
            function ($matches) {
                return $this->quoteValue($matches[1]);
            },
            $expression->get()
        );
        $quoted = preg_replace_callback(
            '%`(.*?)`%s',
            function ($matches) {
                return $this->quoteDbEntityName($matches[1]);
            },
            $quoted
        );
        return $quoted;
    }

    /**
     * @param string $operator
     * @param array|float|int|string $value
     * @return string
     * @throws \InvalidArgumentException
     */
    public function convertConditionOperator($operator, $value) {
        if ($value === null) {
            // 2.2
            return in_array($operator, ['!=', 'NOT', 'IS NOT'], true) ? 'IS NOT' : 'IS';
        } else if (is_array($value)) {
            // 2.4
            switch ($operator) {
                case '=':
                case 'IN':
                    return 'IN';
                case '!=':
                case 'NOT':
                case 'NOT IN':
                    return 'NOT IN';
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    return $operator;
                case '?|':
                case '?&':
                case '@>':
                case '<@':
                    return $operator;
                default:
                    throw new \InvalidArgumentException(
                        "Condition operator [$operator] does not support list of values"
                    );
            }
        } else if (!($value instanceof DbExpr) && in_array($operator, ['IN', 'NOT IN'], true)) {
            // value is not an array and not DbExpr - convert to single-value operator
            return $operator === 'IN' ? '=' : '!=';
        } else if (in_array($operator, ['NOT', 'IS NOT'], true)) {
            // NOT and IS NOT cannot be used for non-null values and for comparison of single value
            return '!=';
        } else if ($operator === 'IS') {
            return '=';
        } else {
            $map = $this->getConditionOperatorsMap();
            return isset($map[$operator]) ? $map[$operator] : $operator;
        }
    }

    /**
     * @return array - key-value array where keys = general operators and values = driver-specific operators
     */
    public function getConditionOperatorsMap() {
        return [];
    }

    /**
     * @param mixed $value
     * @param string $operator
     * @param bool $valueAlreadyQuoted
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function assembleConditionValue($value, $operator, $valueAlreadyQuoted = false) {
        $operator = mb_strtoupper($operator);
        if ($value instanceof DbExpr) {
            return $this->quoteDbExpr($value);
        } else if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
            // 2.3
            if (!is_array($value)) {
                throw new \InvalidArgumentException(
                    'Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]'
                );
            } else if (count($value) !== 2) {
                throw new \InvalidArgumentException(
                    'BETWEEN and NOT BETWEEN conditions require value to an array with 2 values: [min, max]'
                );
            }
            /** @var array $value */
            $value = array_values($value);
            if ($value[0] === null || $value[1] === null || is_bool($value[0]) || is_bool($value[1])) {
                throw new \InvalidArgumentException(
                    'BETWEEN and NOT BETWEEN conditions does not allow min or max values to be null or boolean'
                );
            }
            $fromValue = $valueAlreadyQuoted ? $value[0] : $this->quoteValue($value[0]);
            $toValue = $valueAlreadyQuoted ? $value[1] : $this->quoteValue($value[1]);
            return $fromValue . ' AND ' . $toValue;
        } else if (is_array($value)) {
            // 2.4
            if (empty($value)) {
                throw new \InvalidArgumentException('Empty array is not allowed as condition value');
            } else {
                $quotedValues = [];
                foreach ($value as $val) {
                    $quotedValues[] = $valueAlreadyQuoted ? $value : $this->quoteValue($val);
                }
                return '(' . implode(',', $quotedValues) . ')';
            }
        } else {
            // 2.1, 2.2
            return $valueAlreadyQuoted ? $value : $this->quoteValue($value);
        }
    }

    /**
     * Assemble condition from prepared parts
     * @param string $quotedColumn
     * @param string $operator
     * @param mixed $rawValue
     * @param bool $valueAlreadyQuoted
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function assembleCondition($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted = false) {
        $rawValue = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
        return "{$quotedColumn} {$operator} {$rawValue}";
    }

    /**
     * Select many records form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     */
    public function select($table, array $columns = [], $conditionsAndOptions = null) {
        return $this->query(
            $this->makeSelectQuery($table, $columns, $conditionsAndOptions),
            \PeskyORM\Core\Utils::FETCH_ALL
        );
    }

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an array with values for
     * specified $column.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string|DbExpr $column
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     */
    public function selectColumn($table, $column, $conditionsAndOptions = null) {
        if (empty($column)) {
            throw new \InvalidArgumentException('$column argument cannot be empty');
        } else if (!is_string($column) && !($column instanceof DbExpr)) {
            throw new \InvalidArgumentException('$column argument must be a string or DbExpr object');
        }
        return $this->query(
            $this->makeSelectQuery($table, [$column], $conditionsAndOptions),
            \PeskyORM\Core\Utils::FETCH_COLUMN
        );
    }

    /**
     * Select many records form DB by compiling simple query from passed parameters returning an associative array.
     * The query is something like: "SELECT $keysColumn, $valuesColumn FROM $table $conditionsAndOptions"
     * @param string $table
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     */
    public function selectAssoc($table, $keysColumn, $valuesColumn, $conditionsAndOptions = null) {
        if (empty($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument cannot be empty');
        } else if (!is_string($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument must be a string');
        }
        if (empty($valuesColumn)) {
            throw new \InvalidArgumentException('$valuesColumn argument cannot be empty');
        } else if (!is_string($valuesColumn)) {
            throw new \InvalidArgumentException('$valuesColumn argument must be a string');
        }
        $records = $this->query(
            $this->makeSelectQuery($table, [$keysColumn, $valuesColumn], $conditionsAndOptions),
            \PeskyORM\Core\Utils::FETCH_ALL
        );
        $assoc = [];
        foreach ($records as $record) {
            $assoc[$record[$keysColumn]] = $record[$valuesColumn];
        }
        return $assoc;
    }

    /**
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     */
    public function selectOne($table, array $columns = [], $conditionsAndOptions = null) {
        return $this->query(
            $this->makeSelectQuery($table, $columns, $conditionsAndOptions),
            \PeskyORM\Core\Utils::FETCH_FIRST
        );
    }

    /**
     * Select a value form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $expression FROM $table $conditionsAndOptions"
     * @param string $table
     * @param DbExpr $expression - something like "COUNT(*)" or anything else
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \InvalidArgumentException
     */
    public function selectValue($table, DbExpr $expression, $conditionsAndOptions = null) {
        return $this->query(
            $this->makeSelectQuery($table, [$expression], $conditionsAndOptions),
            \PeskyORM\Core\Utils::FETCH_VALUE
        );
    }

    /**
     * Make a simple SELECT query from passed parameters
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return string - something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function makeSelectQuery($table, array $columns = [], $conditionsAndOptions = null) {
        $this->guardTableNameArg($table);
        if (empty($columns)) {
            $columns = ['*'];
        } else {
            $this->guardColumnsArg($columns);
        }
        $this->guardConditionsAndOptionsArg($conditionsAndOptions);
        $suffix = empty($conditionsAndOptions) ? '' : ' ' . $this->quoteDbExpr($conditionsAndOptions);
        return 'SELECT ' . $this->buildColumnsList($columns, false) . ' FROM ' . $this->quoteDbEntityName($table) . $suffix;
    }

}