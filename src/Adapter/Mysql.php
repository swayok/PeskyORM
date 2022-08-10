<?php

declare(strict_types=1);

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\ColumnDescription;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\TableDescription;
use PeskyORM\Core\Utils;
use PeskyORM\Exception\DbException;
use PeskyORM\ORM\Column;
use Swayok\Utils\ValidateValue;

class Mysql extends DbAdapter
{
    
    public const ENTITY_NAME_QUOTES = '`';
    
    static protected $dataTypesMap = [
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
    
    protected static $conditionOperatorsMap = [
        'SIMILAR TO' => 'LIKE',
        'NOT SIMILAR TO' => 'NOT LIKE',
        '~' => 'REGEXP',
        '!~' => 'NOT REGEXP',
        '~*' => 'REGEXP',
        '!~*' => 'NOT REGEXP',
        'REGEX' => 'REGEXP',
        'NOT REGEX' => 'NOT REGEXP',
    ];
    
    protected static $dbTypeToOrmType = [
        'bool' => Column::TYPE_BOOL,
        'blob' => Column::TYPE_BLOB,
        'tinyblob' => Column::TYPE_BLOB,
        'mediumblob' => Column::TYPE_BLOB,
        'longblob' => Column::TYPE_BLOB,
        'tinyint' => Column::TYPE_INT,
        'smallint' => Column::TYPE_INT,
        'mediumint' => Column::TYPE_INT,
        'bigint' => Column::TYPE_INT,
        'int' => Column::TYPE_INT,
        'integer' => Column::TYPE_INT,
        'decimal' => Column::TYPE_FLOAT,
        'dec' => Column::TYPE_FLOAT,
        'float' => Column::TYPE_FLOAT,
        'double' => Column::TYPE_FLOAT,
        'double precision' => Column::TYPE_FLOAT,
        'char' => Column::TYPE_STRING,
        'binary' => Column::TYPE_STRING,
        'varchar' => Column::TYPE_STRING,
        'varbinary' => Column::TYPE_STRING,
        'enum' => Column::TYPE_STRING,
        'set' => Column::TYPE_STRING,
        'text' => Column::TYPE_TEXT,
        'tinytext' => Column::TYPE_TEXT,
        'mediumtext' => Column::TYPE_TEXT,
        'longtext' => Column::TYPE_TEXT,
        'json' => Column::TYPE_JSON,
        'date' => Column::TYPE_DATE,
        'time' => Column::TYPE_TIME,
        'datetime' => Column::TYPE_TIMESTAMP,
        'timestamp' => Column::TYPE_TIMESTAMP,
        'year' => Column::TYPE_INT,
    ];
    
    public static function getConnectionConfigClass(): string
    {
        return MysqlConfig::class;
    }
    
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
    
    public function setTimezone(string $timezone)
    {
        $this->exec(DbExpr::create("SET time_zone = ``$timezone``"));
        return $this;
    }
    
    public function setSearchPath(string $newSearchPath)
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
        } else {
            return 'CHAR';
        }
    }
    
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
        $returning = $returning === true ? '*' : $this->buildColumnsList($returning, false);
        switch ($operation) {
            case 'insert':
                return $this->resolveInsertOneQueryWithReturningColumns($table, $data, $dataTypes, $returning, $pkName);
            case 'insert_many':
                return $this->resolveInsertManyQueryWithReturningColumns(
                    $table,
                    $columns,
                    $data,
                    $dataTypes,
                    $returning,
                    $pkName
                );
            case 'delete':
                return $this->resolveDeleteQueryWithReturningColumns($query, $table, $returning);
            case 'update':
                return $this->resolveUpdateQueryWithReturningColumns($query, $table, $returning);
            default:
                throw new \InvalidArgumentException("\$operation '$operation' is not supported by " . __CLASS__);
        }
    }
    
    protected function resolveInsertOneQueryWithReturningColumns(
        $table,
        array $data,
        array $dataTypes,
        $returning,
        $pkName
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
        } elseif ($stmnt->rowCount() > 1) {
            throw new DbException(
                'Received more then 1 record for $returning request after insert. Insert: ' . $insertQuery
                . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_FIRST);
    }
    
    protected function resolveInsertManyQueryWithReturningColumns(
        $table,
        array $columns,
        array $data,
        array $dataTypes,
        $returning,
        $pkName
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
        } elseif ($stmnt->rowCount() !== count($data)) {
            throw new DbException(
                "Received amount of records ({$stmnt->rowCount()}) differs from expected (" . count($data) . ')'
                . '. Insert: ' . $insertQuery . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }
    
    protected function resolveUpdateQueryWithReturningColumns(
        $updateQuery,
        $table,
        $returning
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
        $query,
        $table,
        $returning
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
     * Get table description from DB
     * @param string $table
     * @param string|null $schema - not used for MySQL
     * @return TableDescription
     * @throws \UnexpectedValueException
     * @throws DbException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function describeTable(string $table, ?string $schema = null): TableDescription
    {
        $description = new TableDescription($table, $schema);
        /** @var array $columns */
        $columns = $this->query(DbExpr::create("SHOW COLUMNS IN `$table`"), Utils::FETCH_ALL);
        foreach ($columns as $columnInfo) {
            $columnDescription = new ColumnDescription(
                $columnInfo['Field'],
                $columnInfo['Type'],
                $this->convertDbTypeToOrmType($columnInfo['Type'])
            );
            [$limit, $precision] = $this->extractLimitAndPrecisionForColumnDescription($columnInfo['Type']);
            $columnDescription
                ->setLimitAndPrecision($limit, $precision)
                ->setIsNullable(strtolower($columnInfo['Null']) === 'yes')
                ->setIsPrimaryKey(strtolower($columnInfo['Key']) === 'pri')
                ->setIsUnique(strtolower($columnInfo['Key']) === 'uni')
                ->setDefault($this->cleanDefaultValueForColumnDescription($columnInfo['Default']));
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
        $dbType = strtolower(preg_replace(['%\s*unsigned$%i', '%\([^)]+\)$%'], ['', ''], $dbType));
        return array_key_exists($dbType, static::$dbTypeToOrmType)
            ? static::$dbTypeToOrmType[$dbType]
            : Column::TYPE_STRING;
    }
    
    /**
     * @param string $default
     * @return bool|DbExpr|float|int|string|null
     */
    protected function cleanDefaultValueForColumnDescription($default)
    {
        if ($default === null || $default === '') {
            return $default;
        } elseif ($default === 'CURRENT_TIMESTAMP') {
            return DbExpr::create('NOW()');
        } elseif ($default === 'true') {
            return true;
        } elseif ($default === 'false') {
            return false;
        } elseif (ValidateValue::isInteger($default)) {
            return (int)$default;
        } elseif (ValidateValue::isFloat($default)) {
            return (float)$default;
        } else {
            return $default; //< it seems like there is still no possibility to use functions as default value
        }
    }
    
    /**
     * @param string $typeDescription
     * @return array - index 0: limit; index 1: precision
     */
    protected function extractLimitAndPrecisionForColumnDescription($typeDescription)
    {
        if (preg_match('%\((\d+)(?:,(\d+))?\)( unsigned)?$%', $typeDescription, $matches)) {
            return [(int)$matches[1], !isset($matches[2]) ? null : (int)$matches[2]];
        } else {
            return [null, null];
        }
    }
    
    /**
     * {@inheritDoc}
     * @throws DbException
     */
    protected function quoteJsonSelectorExpression(array $sequence): string
    {
        $sequence[0] = $this->quoteDbEntityName($sequence[0]);
        $max = count($sequence);
        // prepare keys
        for ($i = 2; $i < $max; $i += 2) {
            $value = trim($sequence[$i], '\'"` ');
            if (ctype_digit($value)) {
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
    
    /**
     * @param string $key
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function quoteJsonSelectorValue($key)
    {
        if ($key[0] === '[') {
            $key = '$' . $key;
        } else {
            $key = '$.' . $key;
        }
        return $this->quoteValue($key, \PDO::PARAM_STR);
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
                return $value;
            } else {
                $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                return $this->quoteValue($value);
            }
        } else {
            return parent::assembleConditionValue($value, $operator, $valueAlreadyQuoted);
        }
    }
    
    public function assembleCondition(string $quotedColumn, string $operator, $rawValue, bool $valueAlreadyQuoted = false): string
    {
        if (in_array($operator, ['?', '?|', '?&'], true)) {
            if (!is_array($rawValue)) {
                $rawValue = [$valueAlreadyQuoted ? $rawValue : $this->quoteJsonSelectorValue($rawValue)];
            } else {
                foreach ($rawValue as &$localValue) {
                    $localValue = $valueAlreadyQuoted ? $localValue : $this->quoteJsonSelectorValue($localValue);
                }
                unset($localValue);
            }
            $values = implode(', ', $rawValue);
            $howMany = $this->quoteValue($operator === '?|' ? 'one' : 'many', \PDO::PARAM_STR);
            return "JSON_CONTAINS_PATH($quotedColumn, $howMany, $values)";
        } elseif (in_array($operator, ['@>', '<@'], true)) {
            $value = $this->assembleConditionValue($rawValue, $operator, $valueAlreadyQuoted);
            return "JSON_CONTAINS($quotedColumn, $value)";
        } else {
            return parent::assembleCondition($quotedColumn, $operator, $rawValue, $valueAlreadyQuoted);
        }
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
    public function listen(string $channel, \Closure $handler, int $sleepIfNoNotificationMs = 1000, int $sleepAfterNotificationMs = 0)
    {
        throw new DbException('MySQL does not support notifications', DbException::CODE_DB_DOES_NOT_SUPPORT_FEATURE);
    }
    
}
