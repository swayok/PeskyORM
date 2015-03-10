<?php

namespace ORM;

use ORM\Exception\DbException;
use ORM\Lib\Utils;

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

    protected $engine = self::PGSQL;
    protected $name = '';
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
    /** @var null|string $queryString */
    protected $queryString = null;

    static public $collectAllQueries = false;
    static protected $allQueries = array();

    /**
     * Create pdo connection
     * @param string $dbType - database type (mysql, pgsql, etc.)
     * @param string $dbName - database name
     * @param null|string $user - user name
     * @param null|string $password - user password
     * @param string $server - server address in format 'host.name' or 'ddd.ddd.ddd.ddd', can contain port ':ddddd' (default: 'localhost')
     */
    public function __construct($dbType, $dbName, $user = null, $password = null, $server = 'localhost') {
        $this->engine = strtolower($dbType);
        switch ($this->engine) {
            case self::MYSQL:
            case self::PGSQL:
                $this->pdo = new \PDO(
                    $this->engine . ':host=' . $server . ';dbname=' . $dbName,
                    $user,
                    $password
                );
                break;
            case self::SQLITE:
                $this->pdo = new \PDO($this->engine . ':' . $dbName);
                break;
        }
        //$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->defaultLimit = self::$engineSpecials['no_limit'][$this->engine];
        $this->boolTrue = self::$engineSpecials['bool'][$this->engine][true];
        $this->boolFalse = self::$engineSpecials['bool'][$this->engine][false];
        $this->nameQuotes = self::$engineSpecials['name_quotes'][$this->engine];
        $this->hasReturning = $this->engine == self::PGSQL;
    }

    public function query($query) {
        $index = -1;
        if (!$this->dontRememberNextQuery) {
            $this->queryString = $query;
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
            $errorInfo = $this->pdo->errorInfo();
            throw new \PDOException($errorInfo[2] . ' <br>Query: ' . $query, $errorInfo[1]);
        }
        $this->dontRememberNextQuery = false;
        return $statement;
    }

    public function exec($query) {
        $index = -1;
        if (!$this->dontRememberNextQuery) {
            $this->queryString = $query;
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
            $errorInfo = $this->pdo->errorInfo();
            throw new DbException($this, $errorInfo[2] . ' <br>Query: ' . $query, $errorInfo[1]);
        }
        $this->dontRememberNextQuery = false;
        return $statement;
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
        return $this->queryString;
    }

    static public function getAllQueries() {
        return self::$allQueries;
    }

    public function error() {
        return $this->pdo->errorInfo();
    }

    /* Transactions */

    public function begin($readOnly = false, $transactionType = null) {
        if (!empty($transactionType) && !in_array($transactionType, self::$transactionTypes[$this->engine])) {
            throw new DbException($this, "Unknown transaction type [{$transactionType}] for DB engine [{$this->engine}]");
        }
        if (!$readOnly && (empty($transactionType) || $transactionType === self::PGSQL_TRANSACTION_TYPE_DEFAULT)) {
            try {
                $this->pdo->beginTransaction();
            } catch (\Exception $exc) {
                throw new DbException($this, 'Already in transaction: ' . Utils::printToStr(self::$transactionsTraces));
            }
        } else {
            self::$inTransaction = true;
            $this->dontRememberNextQuery = true;
            $this->exec('BEGIN ISOLATION LEVEL ' . $transactionType . ' ' . ($readOnly ? 'READ ONLY' : ''));
        }
        if (function_exists('\dbt')) {
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
            return $value->get();
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

    /* Service */

    /**
     * convert passed $array to string compatible with sql query
     * @param mixed $array
     * @return mixed
     */
    public function serializeArray($array) {
        return json_decode($array);
    }

    /**
     * Get all records as arrays
     * @param \PDOStatement $statement
     * @param string $type = 'first', 'value', 'all'
     * @return array|string
     */
    static public function processRecords(\PDOStatement $statement, $type = 'all') {
        if ($statement && $statement->rowCount() > 0) {
            $type = strtolower($type);
            if (!in_array($type, array('all', 'first'))) {
                $value = $statement->fetchColumn();
                return $value;
            } else {
                $records = $statement->fetchAll(\PDO::FETCH_ASSOC);
                return $type == 'first' ? $records[0] : $records;
            }
        } else {
            return in_array($type, array('all', 'first')) ? array() : null;
        }
    }

}