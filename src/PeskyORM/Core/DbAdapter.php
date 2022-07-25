<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PDOStatement;
use PeskyORM\Core\Utils as OrmUtils;
use PeskyORM\Exception\DbException;
use Swayok\Utils\Utils;

abstract class DbAdapter implements DbAdapterInterface
{
    
    public const ENTITY_NAME_QUOTES = '';
    
    // db-specific values for bool data type
    public const BOOL_TRUE = '1';
    public const BOOL_FALSE = '0';
    
    // db-specific value for unlimited amount of query results (ex: SELECT .. OFFSET 10 LIMIT 0)
    public const NO_LIMIT = '0';
    
    public const FETCH_ALL = OrmUtils::FETCH_ALL;
    public const FETCH_FIRST = OrmUtils::FETCH_FIRST;
    public const FETCH_VALUE = OrmUtils::FETCH_VALUE;
    public const FETCH_COLUMN = OrmUtils::FETCH_COLUMN;
    public const FETCH_KEY_PAIR = OrmUtils::FETCH_KEY_PAIR;
    public const FETCH_STATEMENT = OrmUtils::FETCH_STATEMENT;
    public const FETCH_ROWS_COUNT = OrmUtils::FETCH_ROWS_COUNT;
    
    /**
     * @var array
     */
    protected static $conditionOperatorsMap = [];
    
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
     * @var null|\Closure
     */
    static protected $connectionWrapper = null;
    
    /**
     * Last executed query
     * @var null|string
     */
    protected $lastQuery;
    
    /**
     * Set a wrapper to PDO connection. Wrapper called on any new DB connection
     */
    static public function setConnectionWrapper(\Closure $wrapper)
    {
        static::$connectionWrapper = $wrapper;
    }
    
    /**
     * Remove PDO connection wrapper. This does not unwrap existing PDO objects
     */
    static public function unsetConnectionWrapper()
    {
        static::$connectionWrapper = null;
    }
    
    public function __construct(DbConnectionConfigInterface $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
    }
    
    /**
     * {@inheritDoc}
     * @throws \PDOException
     */
    public function getConnection()
    {
        if ($this->pdo === null) {
            $this->pdo = $this->makePdo();
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connectionConfig->onConnect($this->pdo);
            $this->wrapConnection();
            $this->runOnConnectCallbacks($this->onConnectCallbacks);
        }
        return $this->pdo;
    }
    
    public function getConnectionConfig(): DbConnectionConfigInterface
    {
        return $this->connectionConfig;
    }
    
    /**
     * Create \PDO object
     * @return \PDO
     * @throws \PDOException
     */
    protected function makePdo()
    {
        try {
            return new \PDO(
                $this->connectionConfig->getPdoConnectionString(),
                $this->connectionConfig->getUserName(),
                $this->connectionConfig->getUserPassword(),
                $this->connectionConfig->getOptions()
            );
        } catch (\Exception $exc) {
            // hide connection settings
            throw new \PDOException($exc->getMessage(), $exc->getCode());
        }
    }
    
    public function disconnect()
    {
        $this->pdo = null;
        return $this;
    }
    
    /**
     * Wrap PDO connection if wrapper is provided
     * @throws \PDOException
     */
    private function wrapConnection()
    {
        if (static::$connectionWrapper instanceof \Closure) {
            $this->pdo = call_user_func(static::$connectionWrapper, $this, $this->getConnection());
        }
    }
    
    public function onConnect(\Closure $callback, ?string $code = null)
    {
        $run = $this->pdo !== null;
        if (!$code) {
            $this->onConnectCallbacks[] = $callback;
        } elseif (!isset($this->onConnectCallbacks[$code])) {
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
    protected function runOnConnectCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }
    
    public function getLastQuery(): ?string
    {
        return $this->lastQuery;
    }
    
    /**
     * Enable/disable tracing of transactions
     * Use when you have problems related to transactions
     * @param bool $enable = true: enable; false: disable
     */
    static public function enableTransactionTraces(bool $enable = true)
    {
        static::$isTransactionTracesEnabled = $enable;
    }
    
    static public function getExpressionToSetDefaultValueForAColumn(): DbExpr
    {
        return DbExpr::create('DEFAULT', false);
    }
    
    public function query($query, string $fetchData = self::FETCH_STATEMENT)
    {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $stmnt = $this->getConnection()
                ->query($query);
            
            return OrmUtils::getDataFromStatement($stmnt, $fetchData);
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
     */
    protected function _exec($query, bool $ignoreZeroModifiedRows = false)
    {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $affectedRowsCount = $this->getConnection()
                ->exec($query);
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
    
    public function exec($query)
    {
        return $this->_exec($query);
    }
    
    public function prepare($query, array $options = []): PDOStatement
    {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        return $this->getConnection()->prepare($query, $options);
    }
    
    public function execPrepared(PDOStatement $statement, array $inserts = [], string $fetchData = self::FETCH_STATEMENT)
    {
        try {
            if (!$statement->execute($inserts)) {
                $exc = $this->getDetailedException($statement->queryString, $statement);
                if ($exc !== null) {
                    throw $exc;
                }
            }
            return OrmUtils::getDataFromStatement($statement, $fetchData);
        } catch (\PDOException $exc) {
            $exc = $this->getDetailedException($statement->queryString, $statement, $exc);
            if ($this->inTransaction() && stripos($statement->queryString, 'ROLLBACK') !== 0) {
                $this->rollBack(); //< error within transactions makes it broken in postgresql
            }
            throw $exc;
        }
    }
    
    public function insert(string $table, array $data, array $dataTypes = [], $returning = false, string $pkName = 'id')
    {
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
            return $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                $columns,
                $data,
                $dataTypes,
                $returning,
                $pkName,
                'insert'
            );
        }
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function insertMany(string $table, array $columns, array $data, array $dataTypes = [], $returning = false, string $pkName = 'id')
    {
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
            } elseif (count($data) !== $rowsAffected) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of $rowsAffected rows while "
                    . count($data) . ' rows should be inserted. Query: ' . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            }
            return true;
        } else {
            return $this->resolveQueryWithReturningColumns(
                $query,
                $table,
                $columns,
                $data,
                $dataTypes,
                $returning,
                $pkName,
                'insert_many'
            );
        }
    }
    
    public function update(string $table, array $data, $conditions, array $dataTypes = [], $returning = false)
    {
        $this->guardTableNameArg($table);
        $this->guardDataArg($data);
        $this->guardConditionsArg($conditions);
        [$tableName, $tableAlias] = preg_split('%\s+AS\s+%i', $table, 2);
        if (empty($tableAlias) || trim($tableAlias) === '') {
            $tableAlias = '';
        } else {
            $tableAlias = ' AS ' . $this->quoteDbEntityName($tableAlias);
        }
        $query = 'UPDATE ' . $this->quoteDbEntityName($tableName) . $tableAlias . ' SET ' . $this->buildValuesListForUpdate($data, $dataTypes)
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        } else {
            return $this->resolveQueryWithReturningColumns(
                $query,
                $tableName,
                array_keys($data),
                $data,
                $dataTypes,
                $returning,
                null,
                'update'
            );
        }
    }
    
    public function delete(string $table, $conditions, $returning = false)
    {
        $this->guardTableNameArg($table);
        $this->guardConditionsArg($conditions);
        $this->guardReturningArg($returning);
        [$tableName, $tableAlias] = preg_split('%\s+AS\s+%i', $table, 2);
        if (empty($tableAlias) || trim($tableAlias) === '') {
            $tableAlias = '';
        } else {
            $tableAlias = ' AS ' . $this->quoteDbEntityName($tableAlias);
        }
        $query = 'DELETE FROM ' . $this->quoteDbEntityName($tableName) . $tableAlias
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        } else {
            return $this->resolveQueryWithReturningColumns(
                $query,
                $tableName,
                [],
                [],
                [],
                $returning,
                null,
                'delete'
            );
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardTableNameArg($table)
    {
        if (empty($table) || !is_string($table) || is_numeric($table)) {
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a non-numeric string');
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardConditionsArg($conditions)
    {
        if (!is_string($conditions) && !($conditions instanceof DbExpr)) {
            throw new \InvalidArgumentException('$conditions argument must be a string or DbExpr object');
        } elseif (empty($conditions)) {
            throw new \InvalidArgumentException(
                '$conditions argument is not allowed to be empty. Use "true" or "1 = 1" if you want to update all.'
            );
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardConditionsAndOptionsArg($conditionsAndOptions)
    {
        if (!empty($conditionsAndOptions) && !($conditionsAndOptions instanceof DbExpr)) {
            throw new \InvalidArgumentException('$conditionsAndOptions argument must be an instance of DbExpr class');
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardReturningArg($returning)
    {
        if (!is_array($returning) && !is_bool($returning)) {
            throw new \InvalidArgumentException('$returning argument must be array or boolean');
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardPkNameArg($pkName)
    {
        if (empty($pkName)) {
            throw new \InvalidArgumentException('$pkName argument cannot be empty');
        } elseif (!is_string($pkName)) {
            throw new \InvalidArgumentException('$pkName argument must be a string');
        }
        $this->quoteDbEntityName($pkName);
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardDataArg(array $data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('$data argument cannot be empty');
        }
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    private function guardColumnsArg(array $columns, $allowDbExpr = true)
    {
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
     */
    protected function buildColumnsList(array $columns, bool $withBraces = true): string
    {
        $quoted = implode(
            ', ',
            array_map(function ($column) {
                return ($column instanceof DbExpr) ? $this->quoteDbExpr($column) : $this->quoteDbEntityName($column);
            }, $columns)
        );
        return $withBraces ? '(' . $quoted . ')' : $quoted;
    }
    
    /**
     * @param array $columns - expected set of columns
     * @param array $valuesAssoc - key-value array where keys = columns
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     * @param int $recordIdx - index of record (needed to make exception message more useful)
     * @return string - "('value1','value2',...)"
     * @throws \InvalidArgumentException
     */
    protected function buildValuesList(array $columns, array $valuesAssoc, array $dataTypes = [], int $recordIdx = 0): string
    {
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
        return '(' . implode(', ', $ret) . ')';
    }
    
    /**
     * @param array $valuesAssoc - key-value array where keys are columns names
     * @param array $dataTypes - key-value array where keys are columns names and values are data type for associated column (\PDO::PARAM_*)
     * @return string - "col1" = 'val1', "col2" = 'val2'
     */
    protected function buildValuesListForUpdate(array $valuesAssoc, array $dataTypes = []): string
    {
        $ret = [];
        foreach ($valuesAssoc as $column => $value) {
            $quotedValue = $this->quoteValue(
                $value,
                empty($dataTypes[$column]) ? null : $dataTypes[$column]
            );
            $ret[] = $this->quoteDbEntityName($column) . '=' . $quotedValue;
        }
        return implode(', ', $ret);
    }
    
    /**
     * This method should resolve RETURNING functionality and return requested data
     * @param string $query - DB query to execute
     * @param string $table
     * @param array $columns
     * @param array $data
     * @param array $dataTypes
     * @param array|bool|string $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns names to return values for
     * @param string|null $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @param string $operation - Name of operation to perform: 'insert', 'insert_many', 'update', 'delete'
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function resolveQueryWithReturningColumns(
        string $query,
        string $table,
        array $columns,
        array $data,
        array $dataTypes,
        $returning,
        ?string $pkName,
        string $operation
    ): array {
        throw new \InvalidArgumentException('DB Adapter [' . get_class($this) . '] does not support RETURNING functionality');
    }
    
    public function inTransaction(): bool
    {
        return $this->getConnection()
            ->inTransaction();
    }
    
    /**
     * {@inheritDoc}
     * @throws \PDOException
     */
    public function begin(bool $readOnly = false, ?string $transactionType = null)
    {
        $this->guardTransaction('begin');
        try {
            $this->getConnection()
                ->beginTransaction();
            static::rememberTransactionTrace();
        } catch (\PDOException $exc) {
            static::rememberTransactionTrace('failed');
            throw $exc;
        }
        return $this;
    }
    
    public function commit()
    {
        $this->guardTransaction('commit');
        $this->getConnection()
            ->commit();
        return $this;
    }
    
    public function rollBack()
    {
        $this->guardTransaction('rollback');
        $this->getConnection()
            ->rollBack();
        return $this;
    }
    
    /**
     * @param string $action = begin|commit|rollback
     * @return void
     * @throws \InvalidArgumentException
     * @throws DbException
     */
    protected function guardTransaction(string $action)
    {
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
    static protected function rememberTransactionTrace(?string $key = null)
    {
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
     * @return \PDOException|null
     */
    protected function getDetailedException(string $query, $pdoStatement = null, ?\PDOException $originalException = null): ?\PDOException
    {
        $errorInfo = $this->getPdoError($pdoStatement);
        if ($errorInfo['message'] === null) {
            return $originalException;
        }
        if (preg_match('%syntax error at or near "\$\d+"%i', $errorInfo['message'])) {
            $errorInfo['message'] .= "\n NOTE: PeskyORM do not use prepared statements. You possibly used one of Postgresql jsonb opertaors - '?', '?|' or '?&'."
                . ' You should use alternative functions: jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text) or jsonb_exists_all(jsonb, text) respectively';
        }
        return new \PDOException($errorInfo['message'] . ". \nQuery: " . $query, $errorInfo['code']);
    }
    
    /**
     * @param null|\PDOStatement|\PDO $pdoStatement
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getPdoError($pdoStatement = null): array
    {
        $ret = [];
        if (empty($pdoStatement)) {
            $pdoStatement = $this->getConnection();
        } elseif (!($pdoStatement instanceof \PDOStatement) && !($pdoStatement instanceof \PDO)) {
            throw new \InvalidArgumentException('$pdoStatement argument should be instance of \PDOStatement or \PDO');
        }
        [$ret['sql_code'], $ret['code'], $ret['message']] = $pdoStatement->errorInfo();
        return $ret;
    }
    
    /**
     * Quote DB entity name (column, table, alias, schema)
     * @param string $name - DB entity name to quote
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     * @throws \InvalidArgumentException
     */
    public function quoteDbEntityName(string $name): string
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Db entity name must be a not empty string');
        }
        if ($name === '*') {
            return '*';
        }
        if (!static::isValidDbEntityName($name)) {
            throw new \InvalidArgumentException("Invalid db entity name: [$name]");
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
     * or 'table.col_name -> json_key1 ->> integer_as_index'
     * or 'table.col_name -> json_key1 ->> `integer_as_key`'
     * @param array $sequence -
     *      index 0: base entity name ('table.col_name' or 'col_name');
     *      indexes 1, 3, 5, ...: selection operator (->, ->>, #>, #>>);
     *      indexes 2, 4, 6, ...: json key name or other selector ('json_key1', 'json_key2')
     * @return string - quoted entity name and json selecor
     */
    abstract protected function quoteJsonSelectorExpression(array $sequence): string;
    
    static public function isValidDbEntityName(string $name, bool $canBeAJsonSelector = true): bool
    {
        return (
            $name === '*'
            || static::_isValidDbEntityName($name)
            || ($canBeAJsonSelector && static::isValidJsonSelector($name))
        );
    }
    
    static protected function _isValidDbEntityName(string $name): bool
    {
        return preg_match('%^[a-zA-Z_][a-zA-Z_0-9]*(\.[a-zA-Z_0-9]+|\.\*)?$%i', $name) > 0;
    }
    
    static public function isValidJsonSelector(string $name): bool
    {
        $parts = preg_split('%\s*[-#]>>?\s*%', $name);
        if (count($parts) < 2) {
            return false;
        }
        if (!static::_isValidDbEntityName($parts[0])) {
            // 1st part of expression is not a valid column name
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
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function quoteValue($value, ?int $valueDataType = null): string
    {
        if ($value instanceof DbExpr) {
            return $this->quoteDbExpr($value);
        } elseif (is_object($value) && is_subclass_of($value, AbstractSelect::class)) {
            return '(' . $value->getQuery() . ')';
        } else {
            if ($value === null || $valueDataType === \PDO::PARAM_NULL) {
                return 'NULL';
            } elseif ((empty($valueDataType) && is_bool($value)) || $valueDataType === \PDO::PARAM_BOOL) {
                return $value ? static::BOOL_TRUE : static::BOOL_FALSE;
            }
            if (empty($valueDataType)) {
                if (is_int($value)) {
                    $valueDataType = \PDO::PARAM_INT;
                } else {
                    $valueDataType = \PDO::PARAM_STR;
                }
            } elseif ($valueDataType === \PDO::PARAM_INT) {
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $value = (int)$value;
                } else {
                    if (is_string($value)) {
                        $realType = "String [$value]";
                    } elseif (is_array($value)) {
                        $realType = 'Array';
                    } elseif (is_object($value)) {
                        $realType = 'Object fo class [\\' . get_class($value) . ']';
                    } elseif (is_bool($value)) {
                        $realType = 'Boolean [' . ($value ? 'true' : 'false') . ']';
                    } elseif (is_resource($value)) { //< todo: remove after upgrade to php 8
                        $realType = 'Resource';
                    } elseif ($value instanceof \Closure) { //< todo: remove after upgrade to php 8
                        $realType = '\Closure';
                    } else {
                        $realType = 'Value of unknown type';
                    }
                    throw new \InvalidArgumentException("\$value expected to be integer or numeric string. $realType received");
                }
            }
            if ($valueDataType === \PDO::PARAM_STR && is_string($value)) {
                // prevent "\" at the end of a string by duplicating slashes
                $value = preg_replace('%([\\\]+)$%', '$1$1', $value);
            }
            if (!in_array($valueDataType, [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_LOB], true)) {
                throw new \InvalidArgumentException('Value in $fieldType argument must be a constant like \PDO::PARAM_*');
            }
            if (is_array($value)) {
                $value = static::serializeArray($value);
            }
            return $this->getConnection()
                ->quote((string)$value, $valueDataType);
        }
    }
    
    /**
     * Convert passed $array to string compatible with sql query
     */
    static public function serializeArray(array $array): string
    {
        return json_encode($array);
    }
    
    public function quoteDbExpr(DbExpr $expression): string
    {
        $quoted = preg_replace_callback(
            '%``(.*?)``%s',
            function ($matches) {
                return $this->quoteValue($matches[1]);
            },
            $expression->get()
        );
        return preg_replace_callback(
            '%`(.*?)`%s',
            function ($matches) {
                return $this->quoteDbEntityName($matches[1]);
            },
            $quoted
        );
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function convertConditionOperator(string $operator, $value): string
    {
        if ($value === null) {
            // 2.2
            return in_array($operator, ['!=', 'NOT', 'IS NOT'], true) ? 'IS NOT' : 'IS';
        } elseif (is_array($value)) {
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
        } elseif (!is_object($value) && in_array($operator, ['IN', 'NOT IN'], true)) {
            // value is not an array and not an object (DbExpr or AbstractSelect) - convert to single-value operator
            return $operator === 'IN' ? '=' : '!=';
        } elseif (in_array($operator, ['NOT', 'IS NOT'], true)) {
            // NOT and IS NOT cannot be used for non-null values and for comparison of single value
            return '!=';
        } elseif ($operator === 'IS') {
            return '=';
        } else {
            $map = $this->getConditionOperatorsMap();
            return $map[$operator] ?? $operator;
        }
    }
    
    /**
     * @return array - key-value array where keys = general operators and values = driver-specific operators
     */
    public function getConditionOperatorsMap(): array
    {
        return static::$conditionOperatorsMap;
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function assembleConditionValue($value, string $operator, bool $valueAlreadyQuoted = false): string
    {
        $operator = mb_strtoupper($operator);
        if ($value instanceof DbExpr) {
            return $this->quoteDbExpr($value);
        } elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
            // 2.3
            if (!is_array($value)) {
                throw new \InvalidArgumentException(
                    'Condition value for BETWEEN and NOT BETWEEN operator must be an array with 2 values: [min, max]'
                );
            } elseif (count($value) !== 2) {
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
        } elseif (is_array($value)) {
            // 2.4
            if (empty($value)) {
                throw new \InvalidArgumentException('Empty array is not allowed as condition value');
            } else {
                $quotedValues = [];
                foreach ($value as $val) {
                    $quotedValues[] = $valueAlreadyQuoted ? $value : $this->quoteValue($val);
                }
                return '(' . implode(', ', $quotedValues) . ')';
            }
        } else {
            // 2.1, 2.2
            return $valueAlreadyQuoted ? $value : $this->quoteValue($value);
        }
    }
    
    public function assembleCondition(string $quotedColumn, string $operator, $rawValue, bool $valueAlreadyQuoted = false): string
    {
        return "{$quotedColumn} {$operator} " . $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
    }
    
    public function select(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array
    {
        return $this->query(
            $this->makeSelectQuery($table, $columns, $conditionsAndOptions),
            OrmUtils::FETCH_ALL
        );
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function selectColumn(string $table, $column, ?DbExpr $conditionsAndOptions = null): array
    {
        if (empty($column)) {
            throw new \InvalidArgumentException('$column argument cannot be empty');
        } elseif (!is_string($column) && !($column instanceof DbExpr)) {
            throw new \InvalidArgumentException('$column argument must be a string or DbExpr object');
        }
        return $this->query(
            $this->makeSelectQuery($table, [$column], $conditionsAndOptions),
            OrmUtils::FETCH_COLUMN
        );
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function selectAssoc(string $table, $keysColumn, $valuesColumn, ?DbExpr $conditionsAndOptions = null): array
    {
        if (empty($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument cannot be empty');
        } elseif (!is_string($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument must be a string');
        }
        if (empty($valuesColumn)) {
            throw new \InvalidArgumentException('$valuesColumn argument cannot be empty');
        } elseif (!is_string($valuesColumn)) {
            throw new \InvalidArgumentException('$valuesColumn argument must be a string');
        }
        $records = $this->query(
            $this->makeSelectQuery($table, [$keysColumn, $valuesColumn], $conditionsAndOptions),
            OrmUtils::FETCH_ALL
        );
        $assoc = [];
        foreach ($records as $record) {
            $assoc[$record[$keysColumn]] = $record[$valuesColumn];
        }
        return $assoc;
    }
    
    /**
     * {@inheritDoc}
     * Select first matching record form DB by compiling simple query from passed parameters.
     * The query is something like: "SELECT $columns FROM $table $conditionsAndOptions"
     * @param string $table
     * @param array $columns - empty array means "all columns" (SELECT *), must contain only strings and DbExpr objects
     * @param DbExpr|null $conditionsAndOptions - Anything to add to query after "FROM $table"
     * @return array
     */
    public function selectOne(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): array
    {
        return $this->query(
            $this->makeSelectQuery($table, $columns, $conditionsAndOptions),
            OrmUtils::FETCH_FIRST
        );
    }
    
    public function selectValue(string $table, DbExpr $expression, ?DbExpr $conditionsAndOptions = null)
    {
        return $this->query(
            $this->makeSelectQuery($table, [$expression], $conditionsAndOptions),
            OrmUtils::FETCH_VALUE
        );
    }
    
    public function makeSelectQuery(string $table, array $columns = [], ?DbExpr $conditionsAndOptions = null): string
    {
        $this->guardTableNameArg($table);
        if (empty($columns)) {
            $columns = ['*'];
        } else {
            $this->guardColumnsArg($columns);
        }
        $this->guardConditionsAndOptionsArg($conditionsAndOptions);
        $suffix = $conditionsAndOptions ? ' ' . $this->quoteDbExpr($conditionsAndOptions) : '';
        return 'SELECT ' . $this->buildColumnsList($columns, false) . ' FROM ' . $this->quoteDbEntityName($table) . $suffix;
    }
    
}
