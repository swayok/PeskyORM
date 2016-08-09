<?php

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbException;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

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
        $this->exec('ROLLBACK');
        if (!$this->rememberTransactionQueries) {
            $this->lastQuery = $lastQuery;
        }
        $this->inTransaction = false;
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
     * @return array
     */
    public function describeTable($table) {
        // todo: implement describeTable
    }

    /**
     * @return DbExpr
     */
    public function getExpressionToSetDefaultValueForAColumn() {
        return DbExpr::create('DEFAULT');
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
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function assembleConditionValue($value, $operator) {
        if (in_array($operator, ['@>', '<@'], true)) {
            $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            return $this->quoteValue($value) . '::jsonb';
        } else {
            return parent::assembleConditionValue($value, $operator);
        }
    }

    /**
     * Assemble condition from prepared parts
     * @param string $quotedColumn
     * @param string $operator
     * @param mixed $rawValue
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function assembleCondition($quotedColumn, $operator, $rawValue) {
        // jsonb opertaors - '?', '?|' or '?&' interfere with prepared PDO statements that use '?' to insert values
        // so it is impossible to use this operators directly. We need to use workarounds
        if (in_array($operator, ['?', '?|', '?&'], true)) {
            if ($operator === '?') {
                $value = $this->assembleConditionValue($rawValue, $operator);
                return "jsonb_exists($quotedColumn, $value)";
            } else {
                if (!is_array($rawValue)) {
                    $rawValue = [$this->quoteValue($rawValue)];
                } else {
                    foreach ($rawValue as &$localValue) {
                        $localValue = $this->quoteValue($localValue);
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
            return parent::assembleCondition($quotedColumn, $operator, $rawValue);
        }
    }

}