<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PDO;
use PDOStatement;
use PeskyORM\Core\Utils\ArgumentValidators;
use PeskyORM\Core\Utils\BacktraceUtils;
use PeskyORM\Core\Utils\DbAdapterMethodArgumentUtils;
use PeskyORM\Core\Utils\DbQuoter;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\Core\Utils\QueryBuilderUtils;
use PeskyORM\Exception\DbException;
use PeskyORM\Exception\DbInsertQueryException;
use PeskyORM\Exception\DbTransactionException;
use PeskyORM\Exception\DetailedPDOException;
use PeskyORM\ORM\RecordInterface;

abstract class DbAdapter implements DbAdapterInterface
{

    protected string $quoteForDbEntityName;
    protected string $trueValue = '1';
    protected string $falseValue = '0';

    public const FETCH_ALL = PdoUtils::FETCH_ALL;
    public const FETCH_FIRST = PdoUtils::FETCH_FIRST;
    public const FETCH_VALUE = PdoUtils::FETCH_VALUE;
    public const FETCH_COLUMN = PdoUtils::FETCH_COLUMN;
    public const FETCH_KEY_PAIR = PdoUtils::FETCH_KEY_PAIR;
    public const FETCH_STATEMENT = PdoUtils::FETCH_STATEMENT;
    public const FETCH_ROWS_COUNT = PdoUtils::FETCH_ROWS_COUNT;

    protected const OPERATION_INSERT_ONE = 'insert';
    protected const OPERATION_INSERT_MANY = 'insert_many';
    protected const OPERATION_UPDATE = 'update';
    protected const OPERATION_DELETE = 'delete';

    /**
     * Traces of all transactions (required for debug)
     */
    protected static array $transactionsTraces = [];

    /**
     * Enables/disables collecting of transactions traces
     */
    protected static bool $isTransactionTracesEnabled = false;

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

    abstract protected function getConditionAssembler(string $operator): ?\Closure;

    /**
     * {@inheritDoc}
     * @throws \PDOException
     */
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->makePdo();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->getConnectionConfig()->onConnect($this->pdo);
            $this->wrapConnection();
            $this->runOnConnectCallbacks($this->onConnectCallbacks);
        }
        return $this->pdo;
    }

    /**
     * Create \PDO object
     * @return PDO
     * @throws \PDOException
     */
    protected function makePdo(): PDO
    {
        try {
            $config = $this->getConnectionConfig();
            return new PDO(
                $config->getPdoConnectionString(),
                $config->getUserName(),
                $config->getUserPassword(),
                $config->getOptions()
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

    public function query(
        string|DbExpr $query,
        string $fetchData = self::FETCH_STATEMENT
    ): mixed {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $stmnt = $this->getConnection()->query($query);
            return $this->getDataFromStatement($stmnt, $fetchData);
        } catch (\PDOException $exc) {
            $this->handlePdoException($exc, $query);
        }
    }

    protected function getDataFromStatement(
        PDOStatement $statement,
        string $type = self::FETCH_ALL
    ): mixed {
        return PdoUtils::getDataFromStatement($statement, $type);
    }

    /**
     * @param string|DbExpr $query
     * @return int - affected rows count
     */
    public function exec(DbExpr|string $query): int
    {
        if ($query instanceof DbExpr) {
            $query = $this->quoteDbExpr($query->setWrapInBrackets(false));
        }
        $this->lastQuery = $query;
        try {
            $affectedRowsCount = $this->getConnection()->exec($query);
            if (
                empty($affectedRowsCount)
                && $this->getConnection()->errorCode() !== PDO::ERR_NONE
            ) {
                // error happened when performing query
                throw new DetailedPDOException($this->getConnection(), $query);
            }
            return $affectedRowsCount;
        } catch (\PDOException $exc) {
            $this->handlePdoException($exc, $query);
        }
    }

    protected function handlePdoException(\PDOException $exc, string $query): void
    {
        $exc = new DetailedPDOException($this->getConnection(), $query, $exc);
        if ($this->inTransaction() && stripos($query, 'ROLLBACK') !== 0) {
            // Error within transactions makes it broken in postgresql.
            // To prevent problems in later code we need to rollback transaction.
            $this->rollBack();
        }
        throw $exc;
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

        $query = 'INSERT INTO ' . $this->assembleTableNameAndAlias($table)
            . ' ' . $this->buildColumnsList($columns)
            . ' VALUES ' . $this->buildValuesList($columns, $data, $dataTypes);

        if (empty($returning)) {
            $rowsAffected = $this->exec($query);
            if (!$rowsAffected) {
                throw new DbInsertQueryException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: " . $this->getLastQuery(),
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
            $returning === true ? [] : $returning,
            $pkName,
            static::OPERATION_INSERT_ONE
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
        $this->guardColumnsListArg($columns, false);
        $this->guardDataArg($data);
        $this->guardReturningArg($returning);
        if ($returning) {
            $this->guardPkNameArg($pkName);
        }

        $query = 'INSERT INTO ' . $this->assembleTableNameAndAlias($table)
            . ' ' . $this->buildColumnsList($columns) . ' VALUES ';

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
                throw new DbInsertQueryException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: " . $this->getLastQuery(),
                );
            }

            if (count($data) !== $rowsAffected) {
                throw new DbInsertQueryException(
                    "Inserting data into table {$table} resulted in modification of $rowsAffected rows while "
                    . count($data) . ' rows should be inserted. Query: ' . $this->getLastQuery(),
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
            $returning === true ? [] : $returning,
            $pkName,
            static::OPERATION_INSERT_MANY
        );
    }

    public function update(
        string $table,
        array $data,
        array|DbExpr $conditions,
        array $dataTypes = [],
        bool|array $returning = false
    ): array|int {
        $this->guardTableNameArg($table);
        $this->guardDataArg($data);
        $this->guardConditionsArg($conditions);

        $query = 'UPDATE ' . $this->assembleTableNameAndAlias($table)
            . ' SET ' . $this->buildValuesListForUpdate($data, $dataTypes)
            . ' WHERE ' . $this->assembleConditions($conditions);

        if (empty($returning)) {
            return $this->exec($query);
        }

        return $this->resolveQueryWithReturningColumns(
            $query,
            $table,
            array_keys($data),
            $data,
            $dataTypes,
            $returning === true ? [] : $returning,
            null,
            static::OPERATION_UPDATE
        );
    }

    public function delete(
        string $table,
        array|DbExpr $conditions,
        bool|array $returning = false
    ): array|int {
        $this->guardTableNameArg($table);
        $this->guardConditionsArg($conditions);
        $this->guardReturningArg($returning);

        $query = 'DELETE FROM ' . $this->assembleTableNameAndAlias($table)
            . ' WHERE ' . $this->assembleConditions($conditions);

        if (empty($returning)) {
            return $this->exec($query);
        }

        return $this->resolveQueryWithReturningColumns(
            $query,
            $table,
            [],
            [],
            [],
            $returning === true ? [] : $returning,
            null,
            static::OPERATION_DELETE
        );
    }

    protected function assembleTableNameAndAlias(string $tableNameWithAlias): string
    {
        $parts = preg_split('%\s+AS\s+%i', $tableNameWithAlias, 2);
        $tableName = $this->quoteDbEntityName(trim($parts[0]));
        if (isset($parts[1]) && !empty(trim($parts[1]))) {
            $tableName .= ' AS ' . $this->quoteDbEntityName(trim($parts[1]));
        }
        return $tableName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardTableNameArg(string $table): void
    {
        DbAdapterMethodArgumentUtils::guardTableNameArg($this, $table);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardConditionsArg(DbExpr|array $conditions): void
    {
        DbAdapterMethodArgumentUtils::guardConditionsArg($conditions);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardReturningArg(bool|array $returning): void
    {
        DbAdapterMethodArgumentUtils::guardReturningArg($returning);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardPkNameArg(string $pkName): void
    {
        DbAdapterMethodArgumentUtils::guardPkNameArg($this, $pkName);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardDataArg(array $data): void
    {
        DbAdapterMethodArgumentUtils::guardDataArg($data);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function guardColumnsListArg(
        array $columns,
        bool $allowDbExpr = true,
        bool $canBeEmpty = false
    ): void {
        DbAdapterMethodArgumentUtils::guardColumnsListArg($columns, $allowDbExpr, $canBeEmpty);
    }

    /**
     * @param array $columns - should contain only strings and DbExpr objects
     * @param bool $withBraces - add "()" around columns list
     * @return string - "(`column1','column2',...)"
     */
    protected function buildColumnsList(array $columns, bool $withBraces = true): string
    {
        return QueryBuilderUtils::buildColumnsList($this, $columns, $withBraces);
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
        return QueryBuilderUtils::buildValuesList($this, $columns, $valuesAssoc, $dataTypes, $recordIdx);
    }

    /**
     * @param array $valuesAssoc - key-value array where keys are columns names
     * @param array $dataTypes - key-value array where keys are columns names and values are data type for associated column (\PDO::PARAM_*)
     * @return string - "col1" = 'val1', "col2" = 'val2'
     */
    protected function buildValuesListForUpdate(array $valuesAssoc, array $dataTypes = []): string
    {
        return QueryBuilderUtils::buildValuesListForUpdate($this, $valuesAssoc, $dataTypes);
    }

    /**
     * This method should resolve RETURNING functionality and return requested data
     * @param string $query - DB query to execute
     * @param string $tableNameWithPossibleAlias
     * @param array $columns
     * @param array $data
     * @param array $dataTypes
     * @param array $returning - return some data back after $data inserted to $table
     *          - empty array: return values for all columns of inserted/update/delete table record
     *          - array: list of columns names to return values for
     * @param string|null $pkName - Name of primary key for $returning in DB drivers that support only getLastInsertId()
     * @param string $operation - Name of operation to perform: static::OPERATION_
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function resolveQueryWithReturningColumns(
        string $query,
        string $tableNameWithPossibleAlias,
        array $columns,
        array $data,
        array $dataTypes,
        array $returning,
        ?string $pkName,
        string $operation
    ): array {
        throw new \InvalidArgumentException('DB Adapter [' . get_class($this) . '] does not support RETURNING functionality');
    }

    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * {@inheritDoc}
     * @throws \PDOException
     */
    public function begin(bool $readOnly = false, ?string $transactionType = null): static
    {
        $this->guardBeginTransaction();
        try {
            $this->getConnection()->beginTransaction();
            static::rememberTransactionTrace();
        } catch (\PDOException $exc) {
            static::rememberTransactionTrace('failed');
            throw $exc;
        }
        return $this;
    }

    public function commit(): static
    {
        $this->guardCommitTransaction();
        $this->getConnection()->commit();
        return $this;
    }

    public function rollBack(): static
    {
        $this->guardRollbackTransaction();
        $this->getConnection()->rollBack();
        return $this;
    }

    protected function guardBeginTransaction(): void
    {
        if ($this->inTransaction()) {
            static::rememberTransactionTrace('failed');
            throw new DbTransactionException(
                'Already in transaction',
                DbException::CODE_TRANSACTION_BEGIN_FAIL,
                static::$transactionsTraces
            );
        }
    }

    protected function guardCommitTransaction(): void
    {
        if (!$this->inTransaction()) {
            static::rememberTransactionTrace('failed');
            throw new DbTransactionException(
                'Attempt to commit not started transaction',
                DbException::CODE_TRANSACTION_COMMIT_FAIL,
                static::$transactionsTraces
            );
        }
    }

    protected function guardRollbackTransaction(): void
    {
        if (!$this->inTransaction()) {
            static::rememberTransactionTrace('failed');
            throw new DbTransactionException(
                'Attempt to rollback not started transaction',
                DbException::CODE_TRANSACTION_ROLLBACK_FAIL,
                static::$transactionsTraces
            );
        }
    }

    /**
     * Remember transaction trace
     * @param null|string $key - array key for this trace
     */
    protected static function rememberTransactionTrace(?string $key = null): void
    {
        if (static::$isTransactionTracesEnabled) {
            $trace = BacktraceUtils::getBackTrace(false, 2);
            if ($key) {
                static::$transactionsTraces[$key] = $trace;
            } else {
                static::$transactionsTraces[] = $trace;
            }
        }
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

    public function getMaxLengthForDbEntityName(): int
    {
        return 63;
    }

    protected function _isValidDbEntityName(string $name): bool
    {
        return PdoUtils::isValidDbEntityName($name);
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
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value,
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
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value
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
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value,
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
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        if ($rawValue instanceof RecordInterface) {
            $rawValue = $rawValue->getPrimaryKeyValue();
        }
        $operator = $this->normalizeConditionOperator($operator, $rawValue);
        $closure = $this->getConditionAssembler($operator);
        if ($closure) {
            return $closure($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted);
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
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $quotedValue,
    ): string {
        $convertedOperator = $this->convertNormalizedConditionOperatorForDbQuery($normalizedOperator);
        return "{$quotedColumn} {$convertedOperator} {$quotedValue}";
    }

    protected function assembleConditions(DbExpr|array $conditions): string
    {
        if ($conditions instanceof DbExpr) {
            return $this->quoteDbExpr($conditions);
        }
        return QueryBuilderUtils::assembleWhereConditionsFromArray($this, $conditions);
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function select(
        string $table,
        array $columns = ['*'],
        DbExpr|array|null $conditionsAndOptions = null
    ): array {
        $select = $this->makeSelectQuery($table, $conditionsAndOptions);
        if (!empty($columns)) {
            $select->columns($columns);
        }
        return $select->fetchMany();
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function selectOne(
        string $table,
        array $columns = ['*'],
        DbExpr|array|null $conditionsAndOptions = null
    ): array {
        $select = $this->makeSelectQuery($table, $conditionsAndOptions);
        if (!empty($columns)) {
            $select->columns($columns);
        }
        return $select->fetchOne();
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function selectColumn(
        string $table,
        string|DbExpr $column,
        DbExpr|array|null $conditionsAndOptions = null
    ): array {
        ArgumentValidators::assertNotEmpty('$column', $column);
        return $this->makeSelectQuery($table, $conditionsAndOptions)
            ->fetchColumn($column);
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function selectAssoc(
        string $table,
        string|DbExpr $keysColumn,
        string|DbExpr $valuesColumn,
        DbExpr|array|null $conditionsAndOptions = null
    ): array {
        ArgumentValidators::assertNotEmpty('$keysColumn', $keysColumn);
        ArgumentValidators::assertNotEmpty('$valuesColumn', $valuesColumn);
        return $this->makeSelectQuery($table, $conditionsAndOptions)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    public function selectValue(
        string $table,
        DbExpr $expression,
        DbExpr|array|null $conditionsAndOptions = null
    ): mixed {
        return $this->makeSelectQuery($table, $conditionsAndOptions)
            ->fetchValue($expression);
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function makeSelectQuery(
        string $table,
        DbExpr|array|null $conditionsAndOptions = null
    ): SelectQueryBuilderInterface {
        $this->guardTableNameArg($table);
        $select = Select::from($table, $this);
        if (is_array($conditionsAndOptions)) {
            $select->fromConfigsArray($conditionsAndOptions);
        } else {
            if ($conditionsAndOptions instanceof DbExpr) {
                $select->appendDbExpr($conditionsAndOptions);
            }
        }
        return $select;
    }

}
