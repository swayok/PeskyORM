<?php

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

class Postgres extends DbAdapter {

    const TRANSACTION_TYPE_READ_COMMITTED = 'READ COMMITTED';
    const TRANSACTION_TYPE_REPEATABLE_READ = 'REPEATABLE READ';
    const TRANSACTION_TYPE_SERIALIZABLE = 'SERIALIZABLE';
    const TRANSACTION_TYPE_DEFAULT = self::TRANSACTION_TYPE_READ_COMMITTED;

    static public $transactionTypes = [
        self::TRANSACTION_TYPE_READ_COMMITTED,
        self::TRANSACTION_TYPE_REPEATABLE_READ,
        self::TRANSACTION_TYPE_SERIALIZABLE
    ];

    const ENTITY_NAME_QUOTES = '"';

    const BOOL_TRUE = 'TRUE';
    const BOOL_FALSE = 'FALSE';

    const NO_LIMIT = 'ALL';

    /**
     * @var array
     */
    protected static $dbTypeToOrmType = [
        'bool' => Column::TYPE_BOOL,
        'bytea' => Column::TYPE_BLOB,
        'char' => Column::TYPE_STRING,
        'name' => Column::TYPE_STRING,
        'int8' => Column::TYPE_INT,
        'int2' => Column::TYPE_INT,
        'int4' => Column::TYPE_INT,
        'text' => Column::TYPE_TEXT,
        'json' => Column::TYPE_JSON,
        'jsonb' => Column::TYPE_JSONB,
        'xml' => Column::TYPE_STRING,
        'float4' => Column::TYPE_FLOAT,
        'float8' => Column::TYPE_FLOAT,
        'money' => Column::TYPE_FLOAT,
        'macaddr' => Column::TYPE_STRING,
        'inet' => Column::TYPE_STRING,       //< 192.168.0.0/24
        'cidr' => Column::TYPE_IPV4_ADDRESS, //< 192.168.0.0
        'bpchar' => Column::TYPE_STRING,     //< blank-padded char == char, internal use but may happen
        'varchar' => Column::TYPE_STRING,
        'date' => Column::TYPE_DATE,
        'time' => Column::TYPE_TIME,
        'timestamp' => Column::TYPE_TIMESTAMP,
        'timestamptz' => Column::TYPE_TIMESTAMP_WITH_TZ,
        'interval' => Column::TYPE_STRING,
        'timetz' => Column::TYPE_TIME,
        'bit' => Column::TYPE_BLOB,
        'varbit' => Column::TYPE_BLOB,
        'numeric' => Column::TYPE_FLOAT,
        'uuid' => Column::TYPE_STRING,
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
    public $rememberTransactionQueries = true;

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

    public function __construct(PostgresConfig $connectionConfig) {
        parent::__construct($connectionConfig);
    }

    public function isDbSupportsTableSchemas() {
        return true;
    }

    public function getDefaultTableSchema() {
        return 'public';
    }

    public function addDataTypeCastToExpression($dataType, $expression) {
        return $expression . '::' . $dataType;
    }

    public function getConditionOperatorsMap() {
        return static::$conditionOperatorsMap;
    }

    public function inTransaction() {
        return $this->inTransaction;
    }

    public function begin($readOnly = false, $transactionType = null) {
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

    public function commit() {
        $this->guardTransaction('commit');
        $lastQuery = $this->getLastQuery();
        $this->exec('COMMIT');
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
        $this->inTransaction = false;
    }

    public function rollBack() {
        $this->guardTransaction('rollback');
        $lastQuery = $this->getLastQuery();
        $this->inTransaction = false;
        $this->_exec('ROLLBACK', true);
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
    }
    
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
            } else if ($operation === 'insert_many' && count($data) !== $statement->rowCount()) {
                throw new DbException(
                    "Inserting data into table {$table} resulted in modification of {$statement->rowCount()} rows while "
                        . count($data). ' rows should be inserted. Query: ' . $this->getLastQuery(),
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
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function describeTable($table, $schema = 'public') {
        $description = new TableDescription($table, $schema);
        $query = "
            SELECT  
                `f`.`attname` AS `name`,  
                `f`.`attnotnull` AS `notnull`,
                `t`.`typname` AS `type`,
                `pg_catalog`.format_type(`f`.`atttypid`,`f`.`atttypmod`) as `type_description`,  
                CASE  
                    WHEN `p`.`contype` = ``p`` THEN true  
                    ELSE false  
                END AS `primarykey`,  
                CASE  
                    WHEN `p`.`contype` = ``u`` THEN true  
                    ELSE false
                END AS `uniquekey`,
                CASE
                    WHEN `p`.`contype` = ``f`` THEN true
                    ELSE false
                END AS `foreignkey`,
                CASE
                    WHEN `p`.`contype` = ``f`` THEN pg_get_constraintdef(`p`.`oid`)
                END AS `foreignkey_definition`,
                CASE
                    WHEN `f`.`atthasdef` = true THEN `d`.`adsrc`
                END AS `default`
            FROM pg_attribute f  
                JOIN `pg_class` `c` ON `c`.`oid` = `f`.`attrelid`  
                JOIN `pg_type` `t` ON `t`.`oid` = `f`.`atttypid`  
                LEFT JOIN `pg_attrdef` `d` ON `d`.adrelid = `c`.`oid` AND `d`.`adnum` = `f`.`attnum`  
                LEFT JOIN `pg_namespace` `n` ON `n`.`oid` = `c`.`relnamespace`  
                LEFT JOIN `pg_constraint` `p` ON `p`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`p`.`conkey`)  
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
            list($limit, $precision) = $this->extractLimitAndPrecisionForColumnDescription($columnInfo['type_description']);
            $columnDescription
                ->setLimitAndPrecision($limit, $precision)
                ->setIsNullable(!(bool)$columnInfo['notnull'])
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
    protected function convertDbTypeToOrmType($dbType) {
        return array_key_exists($dbType, static::$dbTypeToOrmType) 
            ? static::$dbTypeToOrmType[$dbType]
            : Column::TYPE_STRING;
    }

    /**
     * @param string $default
     * @return mixed
     */
    protected function cleanDefaultValueForColumnDescription($default) {
        if ($default === null || $default === '') {
            return null;
        } else if (preg_match("%^'(.*?)'::(bpchar|character varying|char|jsonb?|xml|macaddr|varchar|inet|cidr|text|uuid)$%", $default, $matches)) {
            return str_replace("''", "'", $matches[1]);
        } else if ($default === 'true') {
            return true;
        } else if ($default === 'false') {
            return false;
        } else if (ValidateValue::isInteger($default)) {
            return (int)$default;
        } else if (ValidateValue::isFloat($default)) {
            return (float)$default;
        } else {
            return DbExpr::create($default);
        }
    }

    /**
     * @param string $typeDescription
     * @return array - index 0: limit; index 1: precision
     */
    protected function extractLimitAndPrecisionForColumnDescription($typeDescription) {
        if (preg_match('%\((\d+)(?:,(\d+))?\)$%', $typeDescription, $matches)) {
            return [(int)$matches[1], !isset($matches[2]) ? null : (int)$matches[2]];
        } else {
            return [null, null];
        }
    }

    /**
     * Quote a db entity name like 'table.col_name -> json_key1 ->> json_key2'
     * @param array $sequence -
     *      index 0: base entity name ('table.col_name' or 'col_name');
     *      indexes 1, 3, 5, ...: selection operator (->, ->>, #>, #>>);
     *      indexes 2, 4, 6, ...: json key name or other selector ('json_key1', 'json_key2')
     * @return string - quoted entity name and json selector
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function quoteJsonSelectorExpression(array $sequence) {
        $sequence[0] = $this->quoteDbEntityName($sequence[0]);
        for ($i = 2, $max = count($sequence); $i < $max; $i += 2) {
            $value = trim($sequence[$i], '\'"` ');
            $sequence[$i] = ctype_digit($value) ? $value : $this->quoteValue($value, \PDO::PARAM_STR);
        }
        return implode('', $sequence);
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
        // jsonb opertaors - '?', '?|' or '?&' interfere with prepared PDO statements that use '?' to insert values
        // so it is impossible to use this operators directly. We need to use workarounds
        if (in_array($operator, ['?', '?|', '?&'], true)) {
            if ($operator === '?') {
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

}