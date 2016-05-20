<?php

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbException;
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

    const VALUE_QUOTES = "'";
    const NAME_QUOTES = '"';

    const BOOL_TRUE = 'TRUE';
    const BOOL_FALSE = 'FALSE';

    const NO_LIMIT = 'ALL';

    public $writeTransactionQueriesToLastQuery = true;

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
            if (!$this->writeTransactionQueriesToLastQuery) {
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
        if (!$this->writeTransactionQueriesToLastQuery) {
            $this->lastQuery = $lastQuery;
        }
        $this->inTransaction = false;
    }

    public function rollBack() {
        $this->guardTransaction('rollback');
        $lastQuery = $this->getLastQuery();
        $this->exec('ROLLBACK');
        if (!$this->writeTransactionQueriesToLastQuery) {
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
}