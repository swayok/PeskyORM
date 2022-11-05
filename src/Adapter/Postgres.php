<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Exception\DbException;
use PeskyORM\Exception\DbInsertQueryException;
use PeskyORM\ORM\RecordInterface;

/**
 * @property PostgresConfig $connectionConfig
 * @method PostgresConfig getConnectionConfig()
 */
class Postgres extends DbAdapter
{
    protected string $quoteForDbEntityName = '"';
    protected string $trueValue = 'TRUE';
    protected string $falseValue = 'FALSE';

    public const TRANSACTION_TYPE_READ_COMMITTED = 'READ COMMITTED';
    public const TRANSACTION_TYPE_REPEATABLE_READ = 'REPEATABLE READ';
    public const TRANSACTION_TYPE_SERIALIZABLE = 'SERIALIZABLE';
    public const TRANSACTION_TYPE_DEFAULT = self::TRANSACTION_TYPE_READ_COMMITTED;

    public static array $transactionTypes = [
        self::TRANSACTION_TYPE_READ_COMMITTED,
        self::TRANSACTION_TYPE_REPEATABLE_READ,
        self::TRANSACTION_TYPE_SERIALIZABLE,
    ];

    /**
     * @var bool - false: transaction queries like BEGIN TRANSACTION, COMMIT and ROLLBACK will not be remembered
     * into $this->lastQuery
     */
    public bool $rememberTransactionQueries = false;

    protected bool $inTransaction = false;

    static private array $conditionAssemblerForOperator = [
        '?|' => 'assembleConditionValuesExistsInJson',
        '?&' => 'assembleConditionValuesExistsInJson',
        '@>' => 'assembleConditionJsonContainsJson',
        '<@' => 'assembleConditionJsonContainsJson',
    ];

    /**
     * $assembler = function(
     *     string $quotedColumn,
     *     string $operator,
     *     string|int|float|bool|array|DbExpr|RecordInterface|AbstractSelect|null $rawValue,
     *     bool $valueAlreadyQuoted = false
     * ): string
     * @param string $operator
     * @param \Closure $assembler
     * @return void
     */
    public static function addConditionAssemblerForOperator(
        string $operator,
        \Closure $assembler
    ): void {
        static::$conditionAssemblerForOperator[$operator] = $assembler;
    }

    protected function _isValidDbEntityName(string $name): bool
    {
        // $name can literally be anything when quoted, and it is always quoted unless developer skips quotes
        // https://www.postgresql.org/docs/10/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
        return preg_match('%^[a-zA-Z_].*$%', $name) > 0;
    }

    public function __construct(PostgresConfig $connectionConfig)
    {
        parent::__construct($connectionConfig);
    }

    public function disconnect(): static
    {
        try {
            $this->query('SELECT pg_terminate_backend(pg_backend_pid());');
        } catch (\PDOException $exception) {
            if (stripos($exception->getMessage(), 'terminating connection due to administrator command') === false) {
                throw $exception;
            }
        }
        return parent::disconnect();
    }

    public function isDbSupportsTableSchemas(): bool
    {
        return true;
    }

    public function getDefaultTableSchema(): ?string
    {
        return $this->getConnectionConfig()
            ->getDefaultSchemaName();
    }

    public function setTimezone(string $timezone): static
    {
        $this->exec(DbExpr::create("SET SESSION TIME ZONE ``$timezone``"));
        return $this;
    }

    public function setSearchPath(string $newSearchPath): static
    {
        $this->exec(DbExpr::create("SET search_path TO {$newSearchPath}"));
        return $this;
    }

    public function addDataTypeCastToExpression(string $dataType, string $expression): string
    {
        return '(' . $expression . ')::' . $dataType;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function begin(bool $readOnly = false, ?string $transactionType = null): static
    {
        $this->guardBeginTransaction();
        if (empty($transactionType)) {
            $transactionType = static::TRANSACTION_TYPE_DEFAULT;
        }
        if (!in_array($transactionType, self::$transactionTypes, true)) {
            throw new \InvalidArgumentException("Unknown transaction type '{$transactionType}' for PostgreSQL");
        }
        try {
            $lastQuery = $this->getLastQuery();
            $this->exec('BEGIN ISOLATION LEVEL ' . $transactionType . ' ' . ($readOnly ? 'READ ONLY' : ''));
            $this->inTransaction = true;
            if (!$this->rememberTransactionQueries) {
                $this->lastQuery = $lastQuery;
            }
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
        $lastQuery = $this->getLastQuery();
        $this->exec('COMMIT');
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
        $this->inTransaction = false;
        return $this;
    }

    public function rollBack(): static
    {
        $this->guardRollbackTransaction();
        $lastQuery = $this->getLastQuery();
        $this->inTransaction = false;
        $this->exec('ROLLBACK');
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @throws DbException
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
        if ($returning === true) {
            $query .= ' RETURNING *';
        } else {
            $query .= ' RETURNING ' . $this->buildColumnsList($returning, false);
        }
        $statement = $this->query($query);
        if (in_array($operation, ['insert', 'insert_many'], true)) {
            if (!$statement->rowCount()) {
                throw new DbInsertQueryException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: "
                    . $this->getLastQuery(),
                );
            }

            if ($operation === 'insert_many' && count($data) !== $statement->rowCount()) {
                throw new DbInsertQueryException(
                    "Inserting data into table {$table} resulted in modification of {$statement->rowCount()} rows while "
                    . count($data) . ' rows should be inserted. Query: ' . $this->getLastQuery(),
                );
            }
        }

        if ($operation === 'insert') {
            return $this->getDataFromStatement($statement, static::FETCH_FIRST);
        }

        return $this->getDataFromStatement($statement, static::FETCH_ALL);
    }

    public function quoteJsonSelectorExpression(array $sequence): string
    {
        $sequence[0] = $this->quoteDbEntityName($sequence[0]);
        for ($i = 2, $max = count($sequence); $i < $max; $i += 2) {
            if (!is_numeric($sequence[$i])) {
                // quote as string unless it is integer
                $sequence[$i] = $this->quoteValue(trim($sequence[$i], '\'"` '), \PDO::PARAM_STR);
            }
        }
        return implode('', $sequence);
    }

    protected function getConditionAssembler(string $operator): ?\Closure
    {
        if (isset(static::$conditionAssemblerForOperator[$operator])) {
            if (is_string(static::$conditionAssemblerForOperator[$operator])) {
                return \Closure::fromCallable([$this, static::$conditionAssemblerForOperator[$operator]]);
            }

            return static::$conditionAssemblerForOperator[$operator];
        }

        return null;
    }

    protected function assembleConditionValuesExistsInJson(
        string $quotedColumn,
        string $normalizedOperator,
        string|int|float|bool|array|DbExpr|AbstractSelect|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        if (is_object($rawValue)) {
            // DbExpr, AbstractSelect - use default value assembler
            $value = $this->assembleConditionValue($rawValue, $normalizedOperator, $valueAlreadyQuoted);
        } else {
            // operators: '?|', '?&'
            // value must be converted to 'array[value]' or 'array[subvalue1, subvalue2]'
            $array = is_array($rawValue) ? $rawValue : [$rawValue];
            if ($valueAlreadyQuoted) {
                $quoted = $array;
            } else {
                $quoted = [];
                foreach ($array as $subValue) {
                    $quoted[] = $this->quoteValue($subValue);
                }
            }
            $value = 'array[' . implode(', ', $quoted) . ']';
        }
        return $this->assembleConditionFromPreparedParts($quotedColumn, $normalizedOperator, $value);
    }

    protected function assembleConditionJsonContainsJson(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|AbstractSelect|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        if (is_object($rawValue)) {
            // DbExpr, AbstractSelect - use default value assembler
            $value = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
        } else {
            if ($valueAlreadyQuoted) {
                if (!is_string($rawValue)) {
                    throw new \InvalidArgumentException(
                        'Condition value with $valueAlreadyQuoted === true must be a string but it is ' . gettype($rawValue)
                    );
                }
                $value = $rawValue;
            } else {
                $value = $rawValue;
                if (is_array($rawValue)) {
                    $value = json_encode($rawValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
                $value = $this->quoteValue($value, \PDO::PARAM_STR);
            }
            $value .= '::jsonb';
        }

        return $this->assembleConditionFromPreparedParts($quotedColumn, $operator, $value);
    }

    protected function convertNormalizedConditionOperatorForDbQuery(string $normalizedOperator): string
    {
        return match ($normalizedOperator) {
            // In PDO all statements use '?' to insert values even when you do not use prepared statements.
            // That's why jsonb operators - '?', '?|' and '?&' are in conflict with any PDO statements.
            // We need to escape them to work correctly.
            // Since php 7.4.0 we can escape these operators to look like this: ??, ??|, ??&
            '?' => '??',
            '?|' => '??|',
            '?&' => '??&',
            default => parent::convertNormalizedConditionOperatorForDbQuery($normalizedOperator)
        };
    }

    /**
     * Search for $table in $schema
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     * @return bool
     * @throws \PDOException
     */
    public function hasTable(string $table, ?string $schema = null): bool
    {
        if (empty($schema)) {
            $schema = $this->getDefaultTableSchema();
        }
        $query = "SELECT true FROM `information_schema`.`tables` WHERE `table_schema` = ``$schema`` and `table_name` = ``$table``";
        $exists = $this->query(DbExpr::create($query), static::FETCH_VALUE);
        return !empty($exists);
    }

    /**
     * Listen for DB notifications (mostly for PostgreSQL LISTEN...NOTIFY)
     * @param string $channel
     * @param \Closure $handler - payload handler:
     *      function(?string $payload, ?int $pid): boolean { return true; } - if it returns false: listener will stop
     * @param int $sleepIfNoNotificationMs - miliseconds to sleep if there were no notifications last time
     * @param int $sleepAfterNotificationMs - miliseconds to sleep after notification consumed
     * @return void
     */
    public function listen(
        string $channel,
        \Closure $handler,
        int $sleepIfNoNotificationMs = 1000,
        int $sleepAfterNotificationMs = 0
    ): void {
        $this->exec(DbExpr::create("LISTEN `$channel`"));
        while (1) {
            $result = $this->getConnection()
                ->pgsqlGetNotify(\PDO::FETCH_ASSOC, $sleepIfNoNotificationMs);
            if ($result) {
                $continue = $handler($result['payload'] ?: null, $result['pid'] ?: null);
                if ($continue === false) {
                    $this->exec(DbExpr::create("UNLISTEN `$channel`"));
                    return;
                }
                if ($sleepAfterNotificationMs > 0) {
                    sleep($sleepAfterNotificationMs / 1000);
                }
            }
        }
    }

}
