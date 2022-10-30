<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PDO;
use PDOStatement;
use PeskyORM\Core\Utils as OrmUtils;
use PeskyORM\Exception\DbException;
use PeskyORM\ORM\RecordInterface;
use Swayok\Utils\Utils;

abstract class DbAdapter implements DbAdapterInterface
{

    protected string $quoteForDbEntityName;
    protected string $trueValue = '1';
    protected string $falseValue = '0';

    public const FETCH_ALL = OrmUtils::FETCH_ALL;
    public const FETCH_FIRST = OrmUtils::FETCH_FIRST;
    public const FETCH_VALUE = OrmUtils::FETCH_VALUE;
    public const FETCH_COLUMN = OrmUtils::FETCH_COLUMN;
    public const FETCH_KEY_PAIR = OrmUtils::FETCH_KEY_PAIR;
    public const FETCH_STATEMENT = OrmUtils::FETCH_STATEMENT;
    public const FETCH_ROWS_COUNT = OrmUtils::FETCH_ROWS_COUNT;

    /**
     * Traces of all transactions (required for debug)
     */
    protected static array $transactionsTraces = [];

    /**
     * Enables/disables collecting of transactions traces
     */
    protected static bool $isTransactionTracesEnabled = false;

    protected DbConnectionConfigInterface $connectionConfig;

    protected ?PDO $pdo = null;

    protected array $onConnectCallbacks = [];

    /**
     * Class that wraps PDO connection. Used for debugging
     * function (DbAdapter $adapter, \PDO $pdo) { return $wrappedPdo; }
     */
    protected static ?\Closure $connectionWrapper = null;

    /**
     * Last executed query
     */
    protected ?string $lastQuery = null;

    protected array $conditionAssemblerForOperator = [];

    /**
     * Set a wrapper to PDO connection. Wrapper called on any new DB connection
     */
    public static function setConnectionWrapper(\Closure $wrapper): void
    {
        static::$connectionWrapper = $wrapper;
    }

    /**
     * Remove PDO connection wrapper. This does not unwrap existing PDO objects
     */
    public static function unsetConnectionWrapper(): void
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
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->makePdo();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
     * @return PDO
     * @throws \PDOException
     */
    protected function makePdo(): PDO
    {
        try {
            return new PDO(
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

    public function disconnect(): static
    {
        $this->pdo = null;
        return $this;
    }

    /**
     * Wrap PDO connection if wrapper is provided
     * @throws \PDOException
     */
    private function wrapConnection(): void
    {
        if (static::$connectionWrapper instanceof \Closure) {
            $this->pdo = call_user_func(static::$connectionWrapper, $this, $this->getConnection());
        }
    }

    public function onConnect(\Closure $callback, ?string $code = null): static
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

    protected function runOnConnectCallbacks(array $callbacks): void
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
    public static function enableTransactionTraces(bool $enable = true): void
    {
        static::$isTransactionTracesEnabled = $enable;
    }

    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr
    {
        return DbExpr::create('DEFAULT', false);
    }

    public function query(string|DbExpr $query, string $fetchData = self::FETCH_STATEMENT): mixed
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
     * @return int - affected rows count
     */
    protected function _exec(DbExpr|string $query, bool $ignoreZeroModifiedRows = false): int
    {
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

    public function exec(string|DbExpr $query): int
    {
        return $this->_exec($query);
    }

    public function prepare(string|DbExpr $query, array $options = []): PDOStatement
    {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        return $this->getConnection()->prepare($query, $options);
    }

    public function insert(
        string $table,
        array $data,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): ?array {
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
            return null;
        }

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

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function insertMany(
        string $table,
        array $columns,
        array $data,
        array $dataTypes = [],
        bool|array $returning = false,
        string $pkName = 'id'
    ): ?array {
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
            }

            if (count($data) !== $rowsAffected) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of $rowsAffected rows while "
                    . count($data) . ' rows should be inserted. Query: ' . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            }
            return null;
        }

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

    public function update(
        string $table,
        array $data,
        string|DbExpr $conditions,
        array $dataTypes = [],
        bool|array $returning = false
    ): array|int {
        $this->guardTableNameArg($table);
        $this->guardDataArg($data);
        $this->guardConditionsArg($conditions);
        [$tableName, $tableAlias] = $this->extractTableNameAndAlias($table);
        if (empty($tableAlias) || trim($tableAlias) === '') {
            $tableAlias = '';
        } else {
            $tableAlias = ' AS ' . $this->quoteDbEntityName($tableAlias);
        }
        $query = 'UPDATE ' . $this->quoteDbEntityName($tableName) . $tableAlias . ' SET ' . $this->buildValuesListForUpdate($data, $dataTypes)
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        }

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

    public function delete(string $table, string|DbExpr $conditions, bool|array $returning = false): array|int
    {
        $this->guardTableNameArg($table);
        $this->guardConditionsArg($conditions);
        $this->guardReturningArg($returning);
        [$tableName, $tableAlias] = $this->extractTableNameAndAlias($table);
        if (empty($tableAlias) || trim($tableAlias) === '') {
            $tableAlias = '';
        } else {
            $tableAlias = ' AS ' . $this->quoteDbEntityName($tableAlias);
        }
        $query = 'DELETE FROM ' . $this->quoteDbEntityName($tableName) . $tableAlias
            . ' WHERE ' . ($conditions instanceof DbExpr ? $this->quoteDbExpr($conditions) : $conditions);
        if (empty($returning)) {
            return $this->exec($query);
        }

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

    protected function extractTableNameAndAlias(string $tableNameWithAlias): array
    {
        $parts = preg_split('%\s+AS\s+%i', $tableNameWithAlias, 2);
        $tableName = $parts[0];
        $tableAlias = $parts[1] ?? null;
        return [$tableName, $tableAlias];
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardTableNameArg(string $table): void
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('$table argument cannot be empty and must be a non-numeric string');
        }

        if (!$this->isValidDbEntityName($table)) {
            throw new \InvalidArgumentException(
                '$table must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardConditionsArg(DbExpr|string $conditions): void
    {
        if (empty($conditions)) {
            throw new \InvalidArgumentException(
                '$conditions argument is not allowed to be empty. Use "true" or "1 = 1" if you want to update all.'
            );
        }
    }

    /**
     * @param bool|array $returning
     * @throws \InvalidArgumentException
     */
    protected function guardReturningArg(bool|array $returning): void
    {
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardPkNameArg(string $pkName): void
    {
        if (empty($pkName)) {
            throw new \InvalidArgumentException('$pkName argument cannot be empty');
        }

        if (!$this->isValidDbEntityName($pkName)) {
            throw new \InvalidArgumentException(
                '$pkName must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardDataArg(array $data): void
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('$data argument cannot be empty');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardColumnsArg(array $columns, bool $allowDbExpr = true): void
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
    protected function buildValuesList(
        array $columns,
        array $valuesAssoc,
        array $dataTypes = [],
        int $recordIdx = 0
    ): string {
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
     * @param bool|array $returning - return some data back after $data inserted to $table
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
        bool|array $returning,
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
    public function begin(bool $readOnly = false, ?string $transactionType = null): static
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

    public function commit(): static
    {
        $this->guardTransaction('commit');
        $this->getConnection()
            ->commit();
        return $this;
    }

    public function rollBack(): static
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
    protected function guardTransaction(string $action): void
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
    protected static function rememberTransactionTrace(?string $key = null): void
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
     * @param PDO|PDOStatement|null $pdoStatement
     * @param null|\PDOException $originalException
     * @return \PDOException|null
     */
    protected function getDetailedException(
        string $query,
        PDO|PDOStatement|null $pdoStatement = null,
        ?\PDOException $originalException = null
    ): ?\PDOException {
        $errorInfo = $this->getPdoError($pdoStatement);
        if ($errorInfo['message'] === null) {
            return $originalException;
        }
        if (preg_match('%syntax error at or near "\$\d+"%i', $errorInfo['message'])) {
            $errorInfo['message'] .= "\n NOTE: PeskyORM do not use prepared statements. You possibly used one of PostgreSQL jsonb opertaors - '?', '?|' or '?&'."
                . " You should use escaped operators ('??', '??|' or '??&') or functions: jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text) or jsonb_exists_all(jsonb, text) respectively";
        }
        return new \PDOException($errorInfo['message'] . ". \nQuery: " . $query, $errorInfo['code']);
    }

    /**
     * @param PDOStatement|PDO|null $pdoStatement
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getPdoError(null|PDOStatement|PDO $pdoStatement = null): array
    {
        $ret = [];
        if (!$pdoStatement) {
            $pdoStatement = $this->getConnection();
        } elseif (!($pdoStatement instanceof PDOStatement) && !($pdoStatement instanceof PDO)) {
            throw new \InvalidArgumentException('$pdoStatement argument should be instance of \PDOStatement or \PDO');
        }
        [$ret['sql_code'], $ret['code'], $ret['message']] = $pdoStatement->errorInfo();
        return $ret;
    }

    /**
     * @{inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function quoteDbEntityName(string $name): string
    {
        return DbQuoter::quoteDbEntityName($this, $this->quoteForDbEntityName, $name);
    }

    public function isValidDbEntityName(string $name, bool $canBeAJsonSelector = true): bool
    {
        return (
            $name === '*'
            || $this->_isValidDbEntityName($name)
            || ($canBeAJsonSelector && $this->isValidJsonSelector($name))
        );
    }

    protected function _isValidDbEntityName(string $name): bool
    {
        return OrmUtils::isValidDbEntityName($name);
    }

    protected function isValidJsonSelector(string $name): bool
    {
        $parts = preg_split('%\s*[-#]>>?\s*%', $name);
        if (count($parts) < 2) {
            return false;
        }
        if (!$this->_isValidDbEntityName($parts[0])) {
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
    public function quoteValue(
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $value,
        ?int $valueDataType = null
    ): string {
        return DbQuoter::quoteValue(
            $this,
            $this->quoteForDbEntityName,
            $value,
            $valueDataType,
            $this->trueValue,
            $this->falseValue
        );
    }

    public function quoteDbExpr(DbExpr $expression): string
    {
        return DbQuoter::quoteDbExpr(
            $this,
            $this->quoteForDbEntityName,
            $expression,
            $this->trueValue,
            $this->falseValue
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function normalizeConditionOperator(
        string $operator,
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $value
    ): string {
        /** @var string $operator */
        $operator = mb_strtoupper($operator);

        // convert some commonly used operators to postgresql-like operators
        $operator = match ($operator) {
            'REGEXP', 'REGEX' => '~*',
            'NOT REGEXP', 'NOT REGEX' => '!~*',
            '??' => '?',
            '??|' => '?|',
            '??&' => '?&',
            default => $operator
        };

        if ($value === null) {
            // operators that can work with nulls are: IS and IS NOT
            return in_array($operator, ['!=', 'NOT', 'IS NOT'], true) ? 'IS NOT' : 'IS';
        }

        if (is_array($value)) {
            // operators that can work with arrays are:
            // IN, NOT IN, BETWEEN, NOT BETWEEN and json intersection operators.

            // Here we convert '=' to 'IN' and '!=' to 'NOT IN'
            if ($operator === '=') {
                return 'IN';
            }

            if ($operator === '!=') {
                return 'NOT IN';
            }

            if (!$this->isConditionsOperatorSupportsArrayAsValue($operator)) {
                throw new \InvalidArgumentException(
                    "Condition operator [$operator] does not support list of values"
                );
            }
            return $operator;
        }

        if (!is_object($value) && in_array($operator, ['IN', 'NOT IN'], true)) {
            // value is not an array and not an object (DbExpr or AbstractSelect) - convert to single-value operator
            return $operator === 'IN' ? '=' : '!=';
        }

        if (in_array($operator, ['NOT', 'IS NOT'], true)) {
            // NOT and IS NOT operators cannot be used for non-null values and for comparison of single value
            return '!=';
        }

        if ($operator === 'IS') {
            // IS operator cannot be used for non-null values and for comparison of single value
            return '=';
        }

        return $operator;
    }

    public function isConditionsOperatorSupportsArrayAsValue(string $operator): bool
    {
        return in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', '?|', '?&', '@>', '<@']);
    }

    protected function convertNormalizedConditionOperatorForDbQuery(string $normalizedOperator): string
    {
        return $normalizedOperator;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function assembleConditionValue(
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $value,
        string $normalizedOperator,
        bool $valueAlreadyQuoted = false
    ): string {
        if ($value instanceof DbExpr) {
            return $this->quoteDbExpr($value);
        }

        if (in_array($normalizedOperator, ['BETWEEN', 'NOT BETWEEN'], true)) {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(
                    'Condition value for BETWEEN and NOT BETWEEN operators must be an array with 2 values: [min, max]'
                );
            }

            if (count($value) !== 2) {
                throw new \InvalidArgumentException(
                    'BETWEEN and NOT BETWEEN conditions require value to be an array with 2 values: [min, max]'
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
        }

        if (is_array($value)) {
            // list of values (used for IN and NOT IN conditions)
            if (empty($value)) {
                throw new \InvalidArgumentException('Empty array is not allowed as condition value');
            }

            if ($valueAlreadyQuoted) {
                $quotedValues = $value;
            } else {
                $quotedValues = [];
                foreach ($value as $val) {
                    $quotedValues[] = $this->quoteValue($val);
                }
            }
            return '(' . implode(', ', $quotedValues) . ')';
        }

        return $valueAlreadyQuoted ? $value : $this->quoteValue($value);
    }

    public function assembleCondition(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        if ($rawValue instanceof RecordInterface) {
            $rawValue = $rawValue->getPrimaryKeyValue();
        }
        $operator = $this->normalizeConditionOperator($operator, $rawValue);
        if (isset($this->conditionAssemblerForOperator[$operator])) {
            $methodName = $this->conditionAssemblerForOperator[$operator];
            return $this->$methodName($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted);
        }
        return $this->assembleConditionFromPreparedParts(
            $quotedColumn,
            $operator,
            $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted)
        );
    }

    protected function assembleConditionFromPreparedParts(
        string $quotedColumn,
        string $normalizedOperator,
        string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $quotedValue,
    ): string {
        $convertedOperator = $this->convertNormalizedConditionOperatorForDbQuery($normalizedOperator);
        return "{$quotedColumn} {$convertedOperator} {$quotedValue}";
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
    public function selectColumn(string $table, string|DbExpr $column, ?DbExpr $conditionsAndOptions = null): array
    {
        if (empty($column)) {
            throw new \InvalidArgumentException('$column argument cannot be empty');
        }

        if (!is_string($column) && !($column instanceof DbExpr)) {
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
    public function selectAssoc(
        string $table,
        string|DbExpr $keysColumn,
        string|DbExpr $valuesColumn,
        ?DbExpr $conditionsAndOptions = null
    ): array {
        if (empty($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument cannot be empty');
        }

        if (!is_string($keysColumn)) {
            throw new \InvalidArgumentException('$keysColumn argument must be a string');
        }

        if (empty($valuesColumn)) {
            throw new \InvalidArgumentException('$valuesColumn argument cannot be empty');
        }

        if (!is_string($valuesColumn)) {
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

    public function selectValue(string $table, DbExpr $expression, ?DbExpr $conditionsAndOptions = null): mixed
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
        $suffix = $conditionsAndOptions ? ' ' . $this->quoteDbExpr($conditionsAndOptions) : '';
        return 'SELECT ' . $this->buildColumnsList($columns, false) . ' FROM ' . $this->quoteDbEntityName($table) . $suffix;
    }

}
