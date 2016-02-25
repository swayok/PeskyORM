<?php

namespace PeskyORM\Adapter;

use PeskyORM\DbAdapter;

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
    static $inTransaction = false;

    protected function makePdo() {
        return new \PDO(
            'pgsql:host=' . $this->dbHost . (!empty($this->dbName) ? ';dbname=' . $this->dbName : ''),
            $this->dbUser,
            $this->dbPassword
        );
    }


}