<?php

namespace PeskyORM;

use PeskyORM\Exception\DbException;
use Swayok\Utils\Utils;

class Db {

    const MYSQL = 'mysql';
    const PGSQL = 'pgsql';
    const SQLITE = 'sqlite';

    const PGSQL_TRANSACTION_TYPE_READ_COMMITTED = 'READ COMMITTED';
    const PGSQL_TRANSACTION_TYPE_REPEATABLE_READ = 'REPEATABLE READ';
    const PGSQL_TRANSACTION_TYPE_SERIALIZABLE = 'SERIALIZABLE';
    const PGSQL_TRANSACTION_TYPE_DEFAULT = self::PGSQL_TRANSACTION_TYPE_READ_COMMITTED;

    static public $transactionTypes = array(
        self::PGSQL => array(
            self::PGSQL_TRANSACTION_TYPE_READ_COMMITTED,
            self::PGSQL_TRANSACTION_TYPE_REPEATABLE_READ,
            self::PGSQL_TRANSACTION_TYPE_SERIALIZABLE
        )
    );

    protected $dbEngine = self::PGSQL;
    protected $dbName = '';
    protected $dbUser = '';
    protected $dbHost = '';
    protected $dontRememberNextQuery = '';

    static $inTransaction = false;

    static $transactionsTraces = array();

    static protected $engineSpecials = array(
        'name_quotes' => array(
            'mysql' => '`',
            'pgsql' => '"',
            'sqlite' => '\'',
        ),
        'bool' => array(
            'mysql' => array(true => '1', false => '0'),
            'sqlite' => array(true => '1', false => '0'),
            'pgsql' => array(true => 'TRUE', false => 'FALSE'),
        ),
        'no_limit' => array(
            'mysql' => '0',
            'pgsql' => 'ALL',
            'sqlite' => '0',
        )
    );
    public $nameQuotes;
    public $boolTrue;
    public $boolFalse;
    public $defaultLimit;
    public $hasReturning;
    /** @var \PDO $pdo */
    public $pdo;
    /** @var null|string $lastQuery */
    protected $lastQuery = null;

    /** @var bool|callable */
    static public $collectAllQueries = false;
    static protected $allQueries = array();

    /** @var null|callable */
    static public $connectionWrapper = null;

    /**
     * Create pdo connection
     * @param string $dbType - database type (mysql, pgsql, etc.)
     * @param string $dbName - database name
     * @param null|string $user - user name
     * @param null|string $password - user password
     * @param string $server - server address in format 'host.name' or 'ddd.ddd.ddd.ddd', can contain port ':ddddd' (default: 'localhost')
     */
    public function __construct($dbType, $dbName = null, $user = null, $password = null, $server = 'localhost') {
        $this->dbEngine = strtolower($dbType);
        $this->dbName = $dbName;
        $this->dbUser = $user;
        $this->dbHost = $server;
        switch ($this->dbEngine) {
            case self::MYSQL:
            case self::PGSQL:
                $this->pdo = new \PDO(
                    $this->dbEngine . ':host=' . $server . (!empty($dbName) ? ';dbname=' . $dbName : ''),
                    $user,
                    $password
                );
                break;
            case self::SQLITE:
                $this->pdo = new \PDO($this->dbEngine . (!empty($dbName) ? ':' . $dbName : ''));
                break;
        }
        //$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->defaultLimit = self::$engineSpecials['no_limit'][$this->dbEngine];
        $this->boolTrue = self::$engineSpecials['bool'][$this->dbEngine][true];
        $this->boolFalse = self::$engineSpecials['bool'][$this->dbEngine][false];
        $this->nameQuotes = self::$engineSpecials['name_quotes'][$this->dbEngine];
        $this->hasReturning = $this->dbEngine == self::PGSQL;
        $this->wrapConnection();
    }

    /**
     * Set a wrapper to PDO connection. Wrapper called on any new DB connection
     * @param callable $wrapper
     */
    static public function setConnectionWrapper(callable $wrapper) {
        self::$connectionWrapper = $wrapper;
    }

    /**
     * Wrap PDO connection if wrapper is provided
     */
    private function wrapConnection() {
        if (is_callable(self::$connectionWrapper)) {
            $this->pdo = call_user_func_array(self::$connectionWrapper, [$this, $this->pdo]);
        }
    }

    public function disconnect() {
        $this->pdo = null;
    }

    /**
     * @return string
     */
    public function getDbEngine() {
        return $this->dbEngine;
    }

    /**
     * @return string
     */
    public function getDbHost() {
        return $this->dbHost;
    }

    /**
     * @return string
     */
    public function getDbName() {
        return $this->dbName;
    }

    /**
     * @return null|string
     */
    public function getDbUser() {
        return $this->dbUser;
    }

    public function query($query) {
        $index = -1;
        if (is_object($query) && $query instanceof DbExpr) {
            $query = self::replaceQuotes($query->get());
        }
        if (!$this->dontRememberNextQuery) {
            $this->lastQuery = $query;
            $index = self::rememberQuery($query);
        }
        try {
            $statement = $this->pdo->query($query);
        } catch (\PDOException $exc) {
            if (!$this->dontRememberNextQuery) {
                self::rememberQueryException($index, $exc);
            }
            $statement = false;
        }
        if (!$this->dontRememberNextQuery) {
            self::rememberQueryStatement($index, $statement, $this->pdo);
        }
        if (empty($statement)) {
            throw $this->getDetailedException($query);
        }
        $this->dontRememberNextQuery = false;
        return $statement;
    }

    public function exec($query) {
        $index = -1;
        if (is_object($query) && $query instanceof DbExpr) {
            $query = self::replaceQuotes($query->get());
        }
        if (!$this->dontRememberNextQuery) {
            $this->lastQuery = $query;
            $index = self::rememberQuery($query);
        }
        try {
            $statement = $this->pdo->exec($query);
        } catch (\PDOException $exc) {
            if (!$this->dontRememberNextQuery) {
                self::rememberQueryException($index, $exc);
            }
            $statement = false;
        }
        if (!$this->dontRememberNextQuery) {
            self::rememberQueryStatement($index, $statement, $this->pdo);
        }
        if (empty($statement) && !is_int($statement)) {
            throw $this->getDetailedException($query);
        }
        $this->dontRememberNextQuery = false;
        return $statement;
    }

    /**
     * Make detailed exception from last pdo error
     * @param string $query - failed query
     * @return \PDOException
     */
    private function getDetailedException($query) {
        $errorInfo = $this->pdo->errorInfo();
        if (preg_match('%syntax error at or near "\$\d+"%is', $errorInfo[2])) {
            $errorInfo[2] .= "\n NOTE: PeskyORM do not use prepared statements. You possibly used one of Postgresql jsonb opertaors - '?', '?|' or '?&'."
                . ' You should use alternative functions: jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text) or jsonb_exists_all(jsonb, text) respectively';
        }
        return new \PDOException($errorInfo[2] . "<br>\nQuery: " . $query, $errorInfo[1]);
    }

    /** DEBUG helpers */

    /**
     * Remember query in self::$allQueries if required
     * @param string $queryString
     * @return bool|int - int: index in self::$allQueries | false: when self::$collectAllQueries == false
     */
    static public function rememberQuery($queryString) {
        if (self::$collectAllQueries) {
            self::$allQueries[] = array('query' => $queryString);
            return count(self::$allQueries) -1;
        }
        return false;
    }

    /**
     * Remember PDOStatement information about query
     * @param int|bool $index - query index in self::$allQueries
     * @param null|bool|int|\PDOStatement $statement - empty: error happened | int: rows affected
     * @param \PDO $pdo
     */
    static public function rememberQueryStatement($index, $statement, $pdo) {
        if (self::$collectAllQueries) {
            if (empty($statement) && !is_int($statement)) {
                self::$allQueries[$index]['error'] = $pdo->errorInfo();
            } else if (is_int($statement)) {
                self::$allQueries[$index]['rows affected'] = $statement;
            } else {
                /** @var \PDOStatement $statement */
                self::$allQueries[$index]['result'] = array(
                    'columns' => $statement->columnCount(),
                    'rows' => $statement->rowCount(),
                );
            }
            if (is_callable(self::$collectAllQueries)) {
                call_user_func(self::$collectAllQueries, self::$allQueries[$index]);
            }
        }
    }

    /**
     * Remember exception information triggereb by query
     * @param int|bool $index - query index in self::$allQueries
     * @param \Exception $exception
     */
    static public function rememberQueryException($index, \Exception $exception) {
        if (self::$collectAllQueries) {
            self::$allQueries[$index]['exception'] = array(
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            );
        }
    }

    public function lastQuery() {
        return $this->lastQuery;
    }

    static public function getAllQueries() {
        return self::$allQueries;
    }

    public function error() {
        return $this->pdo->errorInfo();
    }

    /* Transactions */

    public function begin($readOnly = false, $transactionType = null) {
        if (!empty($transactionType) && !in_array($transactionType, self::$transactionTypes[$this->dbEngine])) {
            throw new DbException($this, "Unknown transaction type [{$transactionType}] for DB engine [{$this->dbEngine}]");
        }
        if (!$readOnly && (empty($transactionType) || $transactionType === self::PGSQL_TRANSACTION_TYPE_DEFAULT)) {
            try {
                $this->pdo->beginTransaction();
            } catch (\Exception $exc) {
                self::$transactionsTraces['current'] = Utils::getBackTrace(true, false);
                throw new DbException($this, 'Already in transaction: ' . Utils::printToStr(self::$transactionsTraces));
            }
        } else {
            self::$inTransaction = true;
            $this->dontRememberNextQuery = true;
            $this->exec('BEGIN ISOLATION LEVEL ' . $transactionType . ' ' . ($readOnly ? 'READ ONLY' : ''));
        }
        if (
            $transactionType !== self::PGSQL_TRANSACTION_TYPE_REPEATABLE_READ
            && function_exists('\dbt')
        ) {
            self::$transactionsTraces[] = Utils::getBackTrace(true, false);
        }
    }

    public function inTransaction() {
        return self::$inTransaction || $this->pdo->inTransaction();
    }

    public function commit() {
        if (self::$inTransaction) {
            self::$inTransaction = false;
            $this->dontRememberNextQuery = true;
            $this->exec('COMMIT');
        } else {
            $this->pdo->commit();
        }
    }

    public function rollback() {
        if (self::$inTransaction) {
            self::$inTransaction = false;
            $this->exec('ROLLBACK');
        } else {
            $this->pdo->rollBack();
        }
    }

    /**
     * Quote DB name (column, table, alias, schema)
     * @param string|array $name - array: list of names to quote.
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     * @throws DbException
     */
    public function quoteName($name) {
        if (!preg_match('%[a-zA-Z_]+(\.a-zA-Z_)?%is', $name)) {
            throw new DbException($this, "Invalid db entity name [$name]");
        }
        return $this->nameQuotes . preg_replace('%\.%is', $this->nameQuotes . '.' . $this->nameQuotes, $name) . $this->nameQuotes;
    }

    /**
     * Quote passed value
     * @param mixed $value
     * @param int|array $fieldInfoOrType - one of \PDO::PARAM_* or Model->field[$col]
     * @return string
     */
    public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        if (is_object($value) && $value instanceof DbExpr) {
            return self::replaceQuotes($value->get());
        } else {
            if (is_array($value)) {
                $value = $this->serializeArray($value);
            }
            $type = \PDO::PARAM_STR;
            if (is_bool($value)) {
                return $value ? $this->boolTrue : $this->boolFalse;
            } else if ($value === null) {
                return 'NULL';
            } if (is_array($fieldInfoOrType)) {
                if (isset($fieldInfoOrType['type'])) {
                    switch (strtolower($fieldInfoOrType['type'])) {
                        case 'int':
                        case 'integer':
                            $type = \PDO::PARAM_INT;
                            break;
                        case 'bool':
                        case 'boolean':
                            return $value ? $this->boolTrue : $this->boolFalse;
                    }
                }
            }
            return $this->pdo->quote($value, $type);
        }
    }

    /**
     * @param string $expression
     * @return string
     */
    public function replaceQuotes($expression) {
        $expression = preg_replace_callback('%``(.*?)``%is', function ($matches) {
            return $this->quoteValue($matches[1]);
        }, $expression);
        $expression = preg_replace_callback('%`(.*?)`%is', function ($matches) {
            return $this->quoteName($matches[1]);
        }, $expression);
        return $expression;
    }

    /* Service */

    /**
     * convert passed $array to string compatible with sql query
     * @param mixed $array
     * @return mixed
     */
    public function serializeArray($array) {
        return json_decode($array);
    }

}