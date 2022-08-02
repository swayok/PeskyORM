<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\ColumnDescription;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\TableDescription;
use PeskyORM\Core\Utils;
use PeskyORM\Exception\DbException;
use PeskyORM\ORM\Column;
use Swayok\Utils\ValidateValue;

/**
 * @property PostgresConfig $connectionConfig
 * @method PostgresConfig getConnectionConfig()
 */
class Postgres extends DbAdapter
{
    
    public const TRANSACTION_TYPE_READ_COMMITTED = 'READ COMMITTED';
    public const TRANSACTION_TYPE_REPEATABLE_READ = 'REPEATABLE READ';
    public const TRANSACTION_TYPE_SERIALIZABLE = 'SERIALIZABLE';
    public const TRANSACTION_TYPE_DEFAULT = self::TRANSACTION_TYPE_READ_COMMITTED;
    
    static public $transactionTypes = [
        self::TRANSACTION_TYPE_READ_COMMITTED,
        self::TRANSACTION_TYPE_REPEATABLE_READ,
        self::TRANSACTION_TYPE_SERIALIZABLE,
    ];
    
    public const ENTITY_NAME_QUOTES = '"';
    
    public const BOOL_TRUE = 'TRUE';
    public const BOOL_FALSE = 'FALSE';
    
    public const NO_LIMIT = 'ALL';
    
    /**
     * @var array
     */
    protected static $dbTypeToOrmType = [
        'bool' => Column::TYPE_BOOL,
        'bytea' => Column::TYPE_BLOB,
        'bit' => Column::TYPE_BLOB,
        'varbit' => Column::TYPE_BLOB,
        'int8' => Column::TYPE_INT,
        'int2' => Column::TYPE_INT,
        'int4' => Column::TYPE_INT,
        'float4' => Column::TYPE_FLOAT,
        'float8' => Column::TYPE_FLOAT,
        'numeric' => Column::TYPE_FLOAT,
        'money' => Column::TYPE_FLOAT,
        'macaddr' => Column::TYPE_STRING,
        'inet' => Column::TYPE_STRING,       //< 192.168.0.0 or 192.168.0.0/24
        'cidr' => Column::TYPE_STRING,       //< 192.168.0.0/24 only
        'char' => Column::TYPE_STRING,
        'name' => Column::TYPE_STRING,
        'bpchar' => Column::TYPE_STRING,     //< blank-padded char == char, internal use but may happen
        'varchar' => Column::TYPE_STRING,
        'text' => Column::TYPE_TEXT,
        'xml' => Column::TYPE_STRING,
        'json' => Column::TYPE_JSON,
        'jsonb' => Column::TYPE_JSONB,
        'uuid' => Column::TYPE_STRING,
        'date' => Column::TYPE_DATE,
        'time' => Column::TYPE_TIME,
        'timestamp' => Column::TYPE_TIMESTAMP,
        'timestamptz' => Column::TYPE_TIMESTAMP_WITH_TZ,
        'interval' => Column::TYPE_STRING,
        'timetz' => Column::TYPE_TIME,
    ];
    
    // types
    /*
        bool
        bytea
        char
        name
        int8
        int2
        int4
        text
        json
        xml
        float4
        float8
        money
        macaddr
        inet
        cidr
        bpchar
        varchar
        date
        time
        timestamp
        timestamptz
        interval
        timetz
        bit
        varbit
        numeric
        uuid
        jsonb
     */
    
    /**
     * @var bool - false: transaction queries like BEGIN TRANSACTION, COMMIT and ROLLBACK will not be remembered
     * into $this->lastQuery
     */
    public $rememberTransactionQueries = false;
    
    /**
     * @var bool
     */
    protected $inTransaction = false;
    
    static protected $conditionOperatorsMap = [
        'REGEXP' => '~*',
        'NOT REGEXP' => '!~*',
        'REGEX' => '~*',
        'NOT REGEX' => '!~*',
    ];
    
    static public function getConnectionConfigClass(): string
    {
        return PostgresConfig::class;
    }
    
    static protected function _isValidDbEntityName(string $name): bool
    {
        // $name can literally be anything when quoted and it is always quoted unless developer skips quotes
        // https://www.postgresql.org/docs/10/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
        return preg_match('%^[a-zA-Z_].*$%', $name) > 0;
    }
    
    public function __construct(PostgresConfig $connectionConfig)
    {
        parent::__construct($connectionConfig);
    }
    
    public function disconnect()
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
    
    public function setTimezone(string $timezone)
    {
        $this->exec(DbExpr::create("SET SESSION TIME ZONE ``$timezone``"));
        return $this;
    }
    
    public function setSearchPath(string $newSearchPath)
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
    public function begin(bool $readOnly = false, ?string $transactionType = null)
    {
        $this->guardTransaction('begin');
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
    
    public function commit()
    {
        $this->guardTransaction('commit');
        $lastQuery = $this->getLastQuery();
        $this->exec('COMMIT');
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
        $this->inTransaction = false;
    }
    
    public function rollBack()
    {
        $this->guardTransaction('rollback');
        $lastQuery = $this->getLastQuery();
        $this->inTransaction = false;
        $this->_exec('ROLLBACK', true);
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
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
        $returning,
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
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of 0 rows. Query: " . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            } elseif ($operation === 'insert_many' && count($data) !== $statement->rowCount()) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of {$statement->rowCount()} rows while "
                    . count($data) . ' rows should be inserted. Query: ' . $this->getLastQuery(),
                    DbException::CODE_INSERT_FAILED
                );
            }
        }
        if ($operation === 'insert') {
            return Utils::getDataFromStatement($statement, Utils::FETCH_FIRST);
        } else {
            return Utils::getDataFromStatement($statement, Utils::FETCH_ALL);
        }
    }
    
    
    /**
     * Get table description from DB
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table
     * @return TableDescription
     * @throws \PDOException
     */
    public function describeTable(string $table, ?string $schema = null): TableDescription
    {
        if (empty($schema)) {
            $schema = $this->getDefaultTableSchema();
        }
        $description = new TableDescription($table, $schema);
        $query = "
            SELECT
                `f`.`attname` AS `name`,
                `f`.`attnotnull` AS `notnull`,
                `t`.`typname` AS `type`,
                `pg_catalog`.format_type(`f`.`atttypid`,`f`.`atttypmod`) as `type_description`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `pk`
                        WHERE `pk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`pk`.`conkey`) AND `pk`.`contype` = ``p``
                        LIMIT 1
                    ),
                    FALSE
                ) as `primarykey`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `uk`
                        WHERE `uk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`uk`.`conkey`) AND `uk`.`contype` = ``u``
                        LIMIT 1
                    ),
                    FALSE
                ) as `uniquekey`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `fk`
                        WHERE `fk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`fk`.`conkey`) AND `fk`.`contype` = ``f``
                        LIMIT 1
                    ),
                    FALSE
                ) as `foreignkey`,
                CASE
                    WHEN `f`.`atthasdef` = true THEN `d`.`adsrc`
                END AS `default`
            FROM pg_attribute f
                JOIN `pg_class` `c` ON `c`.`oid` = `f`.`attrelid`
                JOIN `pg_type` `t` ON `t`.`oid` = `f`.`atttypid`
                LEFT JOIN `pg_attrdef` `d` ON `d`.adrelid = `c`.`oid` AND `d`.`adnum` = `f`.`attnum`
                LEFT JOIN `pg_namespace` `n` ON `n`.`oid` = `c`.`relnamespace`
            WHERE `c`.`relkind` = ``r``::char
                AND `n`.`nspname` = ``{$schema}``
                AND `c`.`relname` = ``{$table}``
                AND `f`.`attnum` > 0
            ORDER BY `f`.`attnum`
        ";
        /** @var array $columns */
        $columns = $this->query(DbExpr::create($query), Utils::FETCH_ALL);
        foreach ($columns as $columnInfo) {
            $columnDescription = new ColumnDescription(
                $columnInfo['name'],
                $columnInfo['type'],
                $this->convertDbTypeToOrmType($columnInfo['type'])
            );
            [$limit, $precision] = $this->extractLimitAndPrecisionForColumnDescription($columnInfo['type_description']);
            $columnDescription
                ->setLimitAndPrecision($limit, $precision)
                ->setIsNullable(!$columnInfo['notnull'])
                ->setIsPrimaryKey($columnInfo['primarykey'])
                ->setIsForeignKey($columnInfo['foreignkey'])
                ->setIsUnique($columnInfo['uniquekey'])
                ->setDefault($this->cleanDefaultValueForColumnDescription($columnInfo['default']));
            $description->addColumn($columnDescription);
        }
        return $description;
    }
    
    /**
     * @param $dbType
     * @return string
     */
    protected function convertDbTypeToOrmType($dbType)
    {
        return array_key_exists($dbType, static::$dbTypeToOrmType)
            ? static::$dbTypeToOrmType[$dbType]
            : Column::TYPE_STRING;
    }
    
    /**
     * @param string $default
     * @return array|bool|DbExpr|float|int|string|string[]|null
     */
    protected function cleanDefaultValueForColumnDescription($default)
    {
        if ($default === null || $default === '' || preg_match('%^NULL::%i', $default)) {
            return null;
        } elseif (preg_match(
            "%^'((?:[^']|'')*?)'(?:::(bpchar|character varying|char|jsonb?|xml|macaddr|varchar|inet|cidr|text|uuid))?$%",
            $default,
            $matches
        )) {
            return str_replace("''", "'", $matches[1]);
        } elseif (preg_match("%^'(\d+(?:\.\d*)?)'(?:::(numeric|decimal|(?:small|medium|big)?int(?:eger)?[248]?))?$%", $default, $matches)) {
            return (float)$matches[1];
        } elseif ($default === 'true') {
            return true;
        } elseif ($default === 'false') {
            return false;
        } elseif (ValidateValue::isInteger($default)) {
            return (int)$default;
        } elseif (($tmp = trim($default, "'")) !== '' && ValidateValue::isFloat($tmp)) {
            return (float)$tmp;
        } else {
            return DbExpr::create($default);
        }
    }
    
    /**
     * @param string $typeDescription
     * @return array - index 0: limit; index 1: precision
     */
    protected function extractLimitAndPrecisionForColumnDescription($typeDescription)
    {
        if (preg_match('%\((\d+)(?:,(\d+))?\)$%', $typeDescription, $matches)) {
            return [(int)$matches[1], !isset($matches[2]) ? null : (int)$matches[2]];
        } else {
            return [null, null];
        }
    }
    
    protected function quoteJsonSelectorExpression(array $sequence): string
    {
        $sequence[0] = $this->quoteDbEntityName($sequence[0]);
        for ($i = 2, $max = count($sequence); $i < $max; $i += 2) {
            if (!ctype_digit($sequence[$i])) {
                // quote as string unless it is integer
                $sequence[$i] = $this->quoteValue(trim($sequence[$i], '\'"` '), \PDO::PARAM_STR);
            }
        }
        return implode('', $sequence);
    }
    
    public function assembleConditionValue($value, string $operator, bool $valueAlreadyQuoted = false): string
    {
        if (in_array($operator, ['@>', '<@'], true)) {
            if ($valueAlreadyQuoted) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(
                        'Condition value with $valueAlreadyQuoted === true must be a string. '
                        . gettype($value) . ' received'
                    );
                }
                return $value . '::jsonb';
            } else {
                $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                return $this->quoteValue($value) . '::jsonb';
            }
        } else {
            return parent::assembleConditionValue($value, $operator, $valueAlreadyQuoted);
        }
    }
    
    public function assembleCondition(string $quotedColumn, string $operator, $rawValue, bool $valueAlreadyQuoted = false): string
    {
        // jsonb opertaors - '?', '?|' or '?&' interfere with prepared PDO statements that use '?' to insert values
        // so it is impossible to use this operators directly. We need to use workarounds
        if (in_array($operator, ['?', '?|', '?&'], true)) {
            if (PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION >= 4) {
                // escape operators to be ??, ??|, ??& (available since php 7.4.0)
                return parent::assembleCondition($quotedColumn, '?' . $operator, $rawValue, $valueAlreadyQuoted);
            } elseif ($operator === '?') {
                $value = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
                return "jsonb_exists($quotedColumn, $value)";
            } else {
                if (!is_array($rawValue)) {
                    $rawValue = [$valueAlreadyQuoted ? $rawValue : $this->quoteValue($rawValue)];
                } else {
                    foreach ($rawValue as &$localValue) {
                        $localValue = $valueAlreadyQuoted ? $localValue : $this->quoteValue($localValue);
                    }
                    unset($localValue);
                }
                $values = implode(', ', $rawValue);
                if ($operator === '?|') {
                    return "jsonb_exists_any($quotedColumn, array[$values])";
                } else {
                    return "jsonb_exists_all($quotedColumn, array[$values])";
                }
            }
        } else {
            return parent::assembleCondition($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted);
        }
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
        $exists = $this->query(
            DbExpr::create("SELECT true FROM `information_schema`.`tables` WHERE `table_schema` = ``$schema`` and `table_name` = ``$table``"),
            static::FETCH_VALUE
        );
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
    public function listen(string $channel, \Closure $handler, int $sleepIfNoNotificationMs = 1000, int $sleepAfterNotificationMs = 0)
    {
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
