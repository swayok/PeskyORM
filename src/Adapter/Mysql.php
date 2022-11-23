<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PDOStatement;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\DbExpr;
use PeskyORM\Exception\DbAdapterDoesNotSupportFeature;
use PeskyORM\Exception\DbQueryReturningValuesException;
use PeskyORM\Select\SelectQueryBuilderInterface;

class Mysql extends DbAdapterAbstract
{

    protected string $quoteForDbEntityName = '`';
    protected MysqlConfig $connectionConfig;

    protected static array $dataTypesMap = [
        'bytea' => 'BINARY',
        'date' => 'DATE',
        'time' => 'TIME',
        'timestamp' => 'DATETIME',
        'timestamptz' => 'DATETIME',
        'timestamp with time zone' => 'DATETIME',
        'timestamp without time zone' => 'DATETIME',
        'decimal' => 'DECIMAL',
        'numeric' => 'DECIMAL',
        'real' => 'DECIMAL',
        'double precision' => 'DECIMAL',
        'int2' => 'SIGNED INTEGER',
        'smallint' => 'SIGNED INTEGER',
        'int4' => 'SIGNED INTEGER',
        'integer' => 'SIGNED INTEGER',
        'int8' => 'SIGNED INTEGER',
        'bigint' => 'SIGNED INTEGER',
    ];

    static private array $conditionAssemblerForOperator = [
        '?' => 'assembleValuesExistInJsonCondition',
        '?|' => 'assembleValuesExistInJsonCondition',
        '?&' => 'assembleValuesExistInJsonCondition',
        '@>' => 'assembleJsonContainsJsonCondition',
        '<@' => 'assembleJsonContainsJsonCondition',
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

    public function __construct(MysqlConfig $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
    }

    public function getConnectionConfig(): MysqlConfig
    {
        return $this->connectionConfig;
    }

    public function isDbSupportsTableSchemas(): bool
    {
        return false;
    }

    public function getDefaultTableSchema(): ?string
    {
        return null;
    }

    public function setTimezone(string $timezone): static
    {
        $this->exec(DbExpr::create("SET time_zone = ``$timezone``"));
        return $this;
    }

    public function setSearchPath(string $newSearchPath): static
    {
        // todo: find out if there is something similar in mysql
        return $this;
    }

    public function addDataTypeCastToExpression(string $dataType, string $expression): string
    {
        return 'CAST(' . $expression . ' AS ' . $this->getRealDataType($dataType) . ')';
    }

    protected function getRealDataType(string $dataType): string
    {
        $dataType = strtolower($dataType);
        if (array_key_exists($dataType, static::$dataTypesMap)) {
            return static::$dataTypesMap[$dataType];
        }

        return 'CHAR';
    }

    protected function resolveInsertOneQueryWithReturningColumns(
        string $insertQuery,
        string $table,
        array $data,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        $isLocalTransaction = !$this->inTransaction();
        if ($isLocalTransaction) {
            $this->begin();
        }
        /** @var PDOStatement $insertStatement */
        $insertStatement = $this->query($insertQuery, static::FETCH_STATEMENT);
        $this->assertInsertedRowsCount($table, 1, $insertStatement->rowCount());
        // get id
        $id = $this->getDataFromStatement(
            $this->query('SELECT LAST_INSERT_ID()'),
            static::FETCH_VALUE
        );
        if ($isLocalTransaction) {
            $this->commit();
        }
        // get data for PK value
        $record = $this->selectOne(
            $table,
            empty($returning) ? ['*'] : $returning,
            [$pkName => $id]
        );

        $this->assertSelectedRecordsCountForReturningFeature(
            $insertStatement->rowCount(),
            (int)!empty($record),
            $insertStatement->queryString,
            $this->getLastQuery()
        );

        return $record;
    }

    protected function resolveInsertManyQueryWithReturningColumns(
        string $insertQuery,
        string $table,
        array $columns,
        array $data,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        $isLocalTransaction = !$this->inTransaction();
        if ($isLocalTransaction) {
            $this->begin();
        }
        /** @var PDOStatement $insertStatement */
        $insertStatement = $this->query($insertQuery, static::FETCH_STATEMENT);
        $this->assertInsertedRowsCount($table, count($data), $insertStatement->rowCount());
        $minId = (int)$this->getDataFromStatement(
            $this->query('SELECT LAST_INSERT_ID()'),
            static::FETCH_VALUE
        );
        if ($minId === 0) {
            throw new DbQueryReturningValuesException(
                'Failed to get IDs of inserted records. LAST_INSERT_ID() returned 0 or non-numeric value',
                $insertStatement->queryString
            );
        }
        $maxId = $minId + $insertStatement->rowCount() - 1;

        $records = $this->select(
            $table,
            empty($returning) ? ['*'] : $returning,
            [
                $pkName . ' BETWEEN' => [$minId, $maxId],
                'ORDER' => [$pkName => SelectQueryBuilderInterface::ORDER_DIRECTION_ASC],
            ]
        );

        $this->assertSelectedRecordsCountForReturningFeature(
            $insertStatement->rowCount(),
            count($records),
            $insertStatement->queryString,
            $this->getLastQuery()
        );

        return $records;
    }

    protected function resolveUpdateQueryWithReturningColumns(
        string $updateQuery,
        string $assembledConditions,
        string $table,
        array $updates,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        $isLocalTransaction = !$this->inTransaction();
        if ($isLocalTransaction) {
            $this->begin();
        }
        // $this->query() won't work here because $pdoStatement->rowsCount() will be 0
        $updatedRowsCount = $this->exec($updateQuery);
        if ($updatedRowsCount === 0) {
            return [];
        }

        $records = $this->select(
            $table,
            empty($returning) ? ['*'] : $returning,
            DbExpr::create('WHERE ' . $assembledConditions)
        );
        $this->assertSelectedRecordsCountForReturningFeature(
            $updatedRowsCount,
            count($records),
            $updateQuery,
            $this->getLastQuery()
        );
        if ($isLocalTransaction) {
            $this->commit();
        }

        return $records;
    }

    protected function resolveDeleteQueryWithReturningColumns(
        string $deleteQuery,
        string $assembledConditions,
        string $table,
        array $returning,
        string $pkName
    ): array {
        $isLocalTransaction = !$this->inTransaction();
        if ($isLocalTransaction) {
            $this->begin();
        }
        // first we select data for records to be deleted
        $records = $this->select(
            $table,
            empty($returning) ? ['*'] : $returning,
            DbExpr::create('WHERE ' . $assembledConditions)
        );
        if (empty($records)) {
            return [];
        }
        $selectQuery = $this->getLastQuery();
        // now we delete records
        // $this->query() won't work here because $pdoStatement->rowsCount() will be 0
        $deletedCount = $this->exec($deleteQuery);
        $this->assertSelectedRecordsCountForReturningFeature(
            $deletedCount,
            count($records),
            $deleteQuery,
            $selectQuery
        );
        if ($isLocalTransaction) {
            $this->commit();
        }
        return $records;
    }

    protected function assertSelectedRecordsCountForReturningFeature(
        int $expectedCount,
        int $selectedCount,
        string $mainQuery,
        string $selectQuery,
    ): void {
        if ($expectedCount !== $selectedCount) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw new DbQueryReturningValuesException(
                "Selected {$selectedCount} records while expected {$expectedCount}"
                . ' records for RETURNING feature',
                $mainQuery,
                $selectQuery
            );
        }
    }

    /**
     * {@inheritDoc}
     * @throws DbAdapterDoesNotSupportFeature
     */
    public function quoteJsonSelectorExpression(array $sequence): string
    {
        $sequence[0] = $this->quoteDbEntityName($sequence[0]);
        $max = count($sequence);
        // prepare keys
        for ($i = 2; $i < $max; $i += 2) {
            $value = trim($sequence[$i], '\'"` ');
            if (is_numeric($value)) {
                $value = '[' . $value . ']';
            }
            $sequence[$i] = $this->quoteJsonSelectorValue($value);
        }
        // make selector
        $result = $sequence[0];
        for ($i = 1; $i < $max; $i += 2) {
            switch ($sequence[$i]) {
                case '->':
                case '->>':
                    $result .= $sequence[$i] . $sequence[$i + 1];
                    break;
                case '#>':
                    $result = "JSON_EXTRACT({$result}, {$sequence[$i + 1]})";
                    break;
                case '#>>':
                    $result = "JSON_UNQUOTE(JSON_EXTRACT({$result}, {$sequence[$i + 1]}))";
                    break;
                default:
                    throw new DbAdapterDoesNotSupportFeature(
                        "Mysql adapter does not support json operator '{$sequence[$i]}'"
                    );
            }
        }
        return $result;
    }

    protected function convertNormalizedConditionOperatorForDbQuery(string $normalizedOperator): string
    {
        // convert PostgreSQL operators to MySQL operators
        return match ($normalizedOperator) {
            '~', '~*', 'SIMILAR TO' => 'REGEXP',
            '!~', '!~*', 'NOT SIMILAR TO' => 'NOT REGEXP',
            default => parent::convertNormalizedConditionOperatorForDbQuery($normalizedOperator)
        };
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

    protected function assembleValuesExistInJsonCondition(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|SelectQueryBuilderInterface|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        // operators: '?', '?|', '?&'
        if (is_object($rawValue)) {
            // DbExpr, AbstractSelect - use default value assembler
            $value = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
        } else {
            if (!is_array($rawValue)) {
                $rawValue = [$valueAlreadyQuoted ? $rawValue : $this->quoteJsonSelectorValue($rawValue)];
            } else {
                foreach ($rawValue as &$localValue) {
                    $localValue = $valueAlreadyQuoted ? $localValue : $this->quoteJsonSelectorValue($localValue);
                }
                unset($localValue);
            }
            $value = implode(', ', $rawValue);
        }

        $howMany = $this->quoteValue($operator === '?|' ? 'one' : 'many', \PDO::PARAM_STR);
        return "JSON_CONTAINS_PATH($quotedColumn, $howMany, $value)";
    }

    protected function quoteJsonSelectorValue(string $key): string
    {
        if ($key[0] === '[') {
            $key = '$' . $key;
        } else {
            $key = '$.' . $key;
        }
        return $this->quoteValue($key, \PDO::PARAM_STR);
    }

    protected function assembleJsonContainsJsonCondition(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|SelectQueryBuilderInterface|null $rawValue,
        bool $valueAlreadyQuoted = false
    ): string {
        // operators: '@>', '<@'
        if (is_object($rawValue)) {
            // DbExpr, AbstractSelect - use default value assembler
            $value = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
        } elseif ($valueAlreadyQuoted) {
            if (!is_string($rawValue)) {
                throw new \InvalidArgumentException(
                    'Condition value with $valueAlreadyQuoted === true must be a string but it is ' . gettype($rawValue)
                );
            }
            $value = $rawValue;
        } else {
            $value = is_array($rawValue)
                ? json_encode($rawValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : $rawValue;
        }

        return "JSON_CONTAINS($quotedColumn, $value)";
    }

    public function hasTable(string $table, ?string $schema = null): bool
    {
        $exists = (bool)$this->query(
            DbExpr::create("SELECT true FROM `information_schema`.`tables` WHERE `table_name` = ``$table``"),
            static::FETCH_VALUE
        );
        return !empty($exists);
    }

    public function listen(
        string $channel,
        \Closure $handler,
        int $sleepIfNoNotificationMs = 1000,
        int $sleepAfterNotificationMs = 0
    ): void {
        throw new DbAdapterDoesNotSupportFeature('MySQL does not support notifications');
    }

}
