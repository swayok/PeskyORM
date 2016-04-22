<?php

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapter;

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

    /**
     * @var bool
     */
    protected $inTransaction = false;

    public function __construct(PostgresConfig $connectionConfig) {
        parent::__construct($connectionConfig);
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
            $this->exec('BEGIN ISOLATION LEVEL ' . $transactionType . ' ' . ($readOnly ? 'READ ONLY' : ''));
            $this->inTransaction = true;
            static::rememberTransactionTrace();
        } catch (\PDOException $exc) {
            static::rememberTransactionTrace('failed');
            throw $exc;
        }
        return $this;
    }

    public function commit() {
        $this->guardTransaction('commit');
        $this->exec('COMMIT');
        $this->inTransaction = false;
    }

    public function rollBack() {
        $this->guardTransaction('rollback');
        $this->exec('ROLLBACK');
        $this->inTransaction = false;
    }


}