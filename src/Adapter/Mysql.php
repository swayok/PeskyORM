<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Exception\DbException;
use PeskyORM\ORM\RecordInterface;

class Mysql extends DbAdapter
{

    protected string $quoteForDbEntityName = '`';

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

    protected array $conditionAssemblerForOperator = [
        '?' => 'assembleConditionValuesExistsInJson',
        '?|' => 'assembleConditionValuesExistsInJson',
        '?&' => 'assembleConditionValuesExistsInJson',
        '@>' => 'assembleConditionJsonContainsJson',
        '<@' => 'assembleConditionJsonContainsJson',
    ];

    public function __construct(MysqlConfig $connectionConfig)
    {
        parent::__construct($connectionConfig);
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
        if (empty($returning)) {
            throw new \InvalidArgumentException('$returning argument must be true or array');
        }
        $returningStr = $returning === true ? '*' : $this->buildColumnsList($returning, false);
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($operation) {
            case 'insert':
                return $this->resolveInsertOneQueryWithReturningColumns(
                    $table,
                    $data,
                    $dataTypes,
                    $returningStr,
                    $pkName
                );
            case 'insert_many':
                return $this->resolveInsertManyQueryWithReturningColumns(
                    $table,
                    $columns,
                    $data,
                    $dataTypes,
                    $returningStr,
                    $pkName
                );
            case 'delete':
                return $this->resolveDeleteQueryWithReturningColumns($query, $table, $returningStr);
            case 'update':
                return $this->resolveUpdateQueryWithReturningColumns($query, $table, $returningStr);
            default:
                throw new \InvalidArgumentException("\$operation '$operation' is not supported by " . __CLASS__);
        }
    }

    protected function resolveInsertOneQueryWithReturningColumns(
        string $table,
        array $data,
        array $dataTypes,
        string $returning,
        string $pkName
    ) {
        /** @noinspection MissUsingParentKeywordInspection */
        parent::insert($table, $data, $dataTypes, false);
        $insertQuery = $this->getLastQuery();
        $id = $this->quoteValue(
            Utils::getDataFromStatement(
                $this->query('SELECT LAST_INSERT_ID()'),
                Utils::FETCH_VALUE
            )
        );
        $pkName = $this->quoteDbEntityName($pkName);
        $query = DbExpr::create("SELECT {$returning} FROM {$table} WHERE $pkName=$id");
        $stmnt = $this->query($query);

        if (!$stmnt->rowCount()) {
            throw new DbException(
                'No data received for $returning request after insert. Insert: ' . $insertQuery
                . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }

        if ($stmnt->rowCount() > 1) {
            throw new DbException(
                'Received more then 1 record for $returning request after insert. Insert: ' . $insertQuery
                . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }

        return Utils::getDataFromStatement($stmnt, Utils::FETCH_FIRST);
    }

    protected function resolveInsertManyQueryWithReturningColumns(
        string $table,
        array $columns,
        array $data,
        array $dataTypes,
        string $returning,
        string $pkName
    ) {
        /** @noinspection MissUsingParentKeywordInspection */
        parent::insertMany($table, $columns, $data, $dataTypes, false);
        $insertQuery = $this->getLastQuery();
        $id1 = (int)trim(
            $this->quoteValue(
                Utils::getDataFromStatement(
                    $this->query('SELECT LAST_INSERT_ID()'),
                    Utils::FETCH_VALUE
                )
            ),
            "'"
        );
        if ($id1 === 0) {
            throw new DbException(
                'Failed to get IDs of inserted records. LAST_INSERT_ID() returned 0',
                DbException::CODE_RETURNING_FAILED
            );
        }
        $id2 = $id1 + count($data) - 1;
        $pkName = $this->quoteDbEntityName($pkName);
        $query = DbExpr::create(
            "SELECT {$returning} FROM {$table} WHERE {$pkName} BETWEEN {$id1} AND {$id2} ORDER BY {$pkName}"
        );
        $stmnt = $this->query($query);

        if (!$stmnt->rowCount()) {
            throw new DbException(
                'No data received for $returning request after insert. '
                . "Insert: {$insertQuery}. Select: {$this->getLastQuery()}",
                DbException::CODE_RETURNING_FAILED
            );
        }

        if ($stmnt->rowCount() !== count($data)) {
            throw new DbException(
                "Received amount of records ({$stmnt->rowCount()}) differs from expected (" . count($data) . ')'
                . '. Insert: ' . $insertQuery . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }

        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }

    protected function resolveUpdateQueryWithReturningColumns(
        string $updateQuery,
        string $table,
        string $returning
    ) {
        /** @noinspection MissUsingParentKeywordInspection */
        $rowsUpdated = parent::exec($updateQuery);
        if (empty($rowsUpdated)) {
            return [];
        }
        $conditionsAndOptions = preg_replace('%^.*?WHERE\s*(.*)$%is', '$1', $updateQuery);
        $selectQuery = DbExpr::create("SELECT {$returning} FROM {$table} WHERE {$conditionsAndOptions}");
        $stmnt = $this->query($selectQuery);
        if ($stmnt->rowCount() !== $rowsUpdated) {
            throw new DbException(
                "Received amount of records ({$stmnt->rowCount()}) differs from expected ({$rowsUpdated})"
                . '. Update: ' . $updateQuery . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }

    protected function resolveDeleteQueryWithReturningColumns(
        string $query,
        string $table,
        string $returning
    ) {
        $conditions = preg_replace('%^.*WHERE%i', '', $query);
        $stmnt = $this->query("SELECT {$returning} FROM {$table} WHERE {$conditions}");
        if (!$stmnt->rowCount()) {
            return [];
        }
        $this->exec($query);
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }

    /**
     * {@inheritDoc}
     * @throws DbException
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
                    throw new DbException("Unsopported json operator '{$sequence[$i]}' received", DbException::CODE_DB_DOES_NOT_SUPPORT_FEATURE);
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

    protected function assembleConditionValuesExistsInJson(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|AbstractSelect|null $rawValue,
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

    protected function assembleConditionJsonContainsJson(
        string $quotedColumn,
        string $operator,
        string|int|float|bool|array|DbExpr|AbstractSelect|null $rawValue,
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

    /**
     * Search for $table in $schema
     * @param string $table
     * @param null|string $schema - name of DB schema that contains $table (for PostgreSQL)
     * @return bool
     * @throws DbException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function hasTable(string $table, ?string $schema = null): bool
    {
        $exists = (bool)$this->query(
            DbExpr::create("SELECT true FROM `information_schema`.`tables` WHERE `table_name` = ``$table``"),
            static::FETCH_VALUE
        );
        return !empty($exists);
    }

    /**
     * Listen for DB notifications (mostly for PostgreSQL LISTEN...NOTIFY)
     * @param string $channel
     * @param \Closure $handler - payload handler:
     *      function(string $payload): boolean { return true; } - if it returns false: listener will stop
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
        throw new DbException('MySQL does not support notifications', DbException::CODE_DB_DOES_NOT_SUPPORT_FEATURE);
    }

}
