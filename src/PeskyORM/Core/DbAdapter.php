<?php

namespace PeskyORM\Core;

use PeskyORM\DbColumnConfig;
use PeskyORM\DbExpr;
use Swayok\Utils\Utils;

abstract class DbAdapter implements DbAdapterInterface {

    const VALUE_QUOTES = '';
    const NAME_QUOTES = '';

    // db-specific values for bool data type
    const BOOL_TRUE = '1';
    const BOOL_FALSE = '0';

    // db-specific value for unlimited amount of query results (ex: SELECT .. OFFSET 10 LIMIT 0)
    const NO_LIMIT = '0';

    /**
     * Traces of all transactions (required for debug)
     * @var array
     */
    protected static $transactionsTraces = [];

    /**
     * Enables/disables collecting of transactions traces
     * @var bool
     */
    protected static $isTransactionTracesEnabled = false;

    protected $dbName = '';
    protected $dbUser = '';
    protected $dbHost = '';
    protected $dbPassword = '';

    /**
     * @var \PDO $pdo
     */
    protected $pdo;

    /**
     * Class that wraps PDO connection. Used for debugging
     * function (DbAdapter $adapter, \PDO $pdo) { return $wrappedPdo; }
     *
     * @var null|callable
     */
    static private $connectionWrapper = null;

    /**
     * Last executed query
     * @var null|string
     */
    protected $lastQuery;

    /**
     * Set a wrapper to PDO connection. Wrapper called on any new DB connection
     * @param callable $wrapper
     */
    static public function setConnectionWrapper(callable $wrapper) {
        static::$connectionWrapper = $wrapper;
    }

    /**
     * Remove PDO connection wrapper. This does not unwrap existing PDO objects
     */
    static public function unsetConnectionWrapper() {
        static::$connectionWrapper = null;
    }

    /**
     * DbAdapter constructor.
     * @param string $dbName
     * @param string $user
     * @param string $password
     * @param string $server
     */
    public function __construct(
        $dbName,
        $user,
        $password,
        $server = 'localhost'
    ) {
        $this->dbName = $dbName;
        $this->dbUser = $user;
        $this->dbHost = $server;
        $this->dbPassword = $password;
    }

    /**
     * Connect to DB once
     * @return $this
     */
    public function getConnection() {
        if ($this->pdo === null) {
            $this->makePdo();
            $this->wrapConnection();
        }
        return $this;
    }

    /**
     * Create \PDO object
     * @return $this
     */
    abstract protected function makePdo();

    public function disconnect() {
        $this->pdo = null;
        return $this;
    }

    /**
     * Wrap PDO connection if wrapper is provided
     */
    private function wrapConnection() {
        if (is_callable(static::$connectionWrapper)) {
            $this->pdo = call_user_func(static::$connectionWrapper, $this, $this->pdo);
        }
    }

    /**
     * @return string
     */
    public function getDbName() {
        return $this->dbName;
    }

    /**
     * @return string
     */
    public function getDbUser() {
        return $this->dbUser;
    }

    /**
     * @return string
     */
    public function getDbHost() {
        return $this->dbHost;
    }

    /**
     * Get last executed query
     * @return null|string
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }

    /**
     * @return string
     */
    public function getDbPassword() {
        return $this->dbPassword;
    }

    /**
     * Enable/disable tracing of transactions
     * Use when you have problems related to transactions
     * @param bool $enable = true: enable; false: disable
     * @return $this
     */
    static public function enableTransactionTraces($enable = true) {
        static::$isTransactionTracesEnabled = $enable;
    }

    /**
     * @param string|DbExpr $query
     * @return bool|\PDOStatement
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function query($query) {
        if ($query instanceof DbExpr) {
            $query = $this->replaceDbExprQuotes($query->get());
        }
        $this->lastQuery = $query;
        try {
            return $this->pdo->query($query);
        } catch (\PDOException $exc) {
            throw $this->getDetailedException($query);
        }
    }

    /**
     * @param string|DbExpr $query
     * @return int
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function exec($query) {
        if ($query instanceof DbExpr) {
            $query = $this->replaceDbExprQuotes($query->get());
        }
        $this->lastQuery = $query;
        try {
            return $this->pdo->exec($query);
        } catch (\PDOException $exc) {
            throw $this->getDetailedException($query);
        }
    }

    /**
     * @param bool $readOnly - true: transaction only reads data
     * @param null|string $transactionType - type of transaction
     * @return $this
     * @throws \PeskyORM\Core\DbException
     */
    public function begin($readOnly = false, $transactionType = null) {
        try {
            $this->pdo->beginTransaction();
            static::rememberTransactionTrace();
        } catch (\Exception $exc) {
            static::rememberTransactionTrace('failed');
            throw new DbException('Already in transaction: ' . Utils::printToStr(static::$transactionsTraces));
        }
        return $this;
    }

    /**
     * Remember transaction trace
     * @param null|string $key - array key for this trace
     */
    static protected function rememberTransactionTrace($key = null) {
        if (static::$isTransactionTracesEnabled) {
            $trace = Utils::getBackTrace(true, false, true, 2);
            if ($key) {
                static::$transactionsTraces[$key] = $trace;
            } else {
                static::$transactionsTraces[] = $trace;
            }
        }
    }

    /**
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    /**
     * @return $this
     */
    public function commit() {
        $this->pdo->commit();
        return $this;
    }

    /**
     * @return $this
     */
    public function rollback() {
        $this->pdo->rollBack();
        return $this;
    }

    /**
     * Make detailed exception from last pdo error
     * @param string $query - failed query
     * @return \PDOException
     */
    private function getDetailedException($query) {
        $errorInfo = $this->getPdoError();
        if (preg_match('%syntax error at or near "\$\d+"%i', $errorInfo[2])) {
            $errorInfo['message'] .= "\n NOTE: PeskyORM do not use prepared statements. You possibly used one of Postgresql jsonb opertaors - '?', '?|' or '?&'."
                . ' You should use alternative functions: jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text) or jsonb_exists_all(jsonb, text) respectively';
        }
        return new \PDOException($errorInfo['code'] . "<br>\nQuery: " . $query, $errorInfo['message']);
    }

    /**
     * @return array
     */
    public function getPdoError() {
        $ret = [];
        list($ret['sql_code'], $ret['code'], $ret['message']) = $this->pdo->errorInfo();
        return $ret;
    }

    /**
     * Quote DB entity name (column, table, alias, schema)
     * @param string|array $name - array: list of names to quote.
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @return string
     * @throws \InvalidArgumentException
     */
    public function quoteName($name) {
        if (!preg_match('%[a-zA-Z_]+(\.a-zA-Z_)?%i', $name)) {
            throw new \InvalidArgumentException("Invalid db entity name [$name]");
        }
        return static::NAME_QUOTES
               . str_replace('.', static::NAME_QUOTES . '.' . static::NAME_QUOTES, $name)
               . static::NAME_QUOTES;
    }

    /**
     * Quote passed value
     * @param mixed $value
     * @param int|DbColumnConfig $fieldInfoOrType - one of \PDO::PARAM_* or DbColumnConfig
     * @return string
     * @throws \InvalidArgumentException
     */
    public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        if ($value instanceof DbExpr) {
            return $this->replaceDbExprQuotes($value->get());
        } else {
            $type = \PDO::PARAM_STR;
            if (is_bool($value)) {
                return $value ? static::BOOL_TRUE : static::BOOL_FALSE;
            } else if ($value === null) {
                return 'NULL';
            } else if ($fieldInfoOrType instanceof DbColumnConfig) {
                switch ($fieldInfoOrType->getDbType()) {
                    case DbColumnConfig::DB_TYPE_INT:
                    case DbColumnConfig::DB_TYPE_BIGINT:
                        $type = \PDO::PARAM_INT;
                        break;
                    case DbColumnConfig::DB_TYPE_BOOL:
                        return $value ? static::BOOL_TRUE: static::BOOL_FALSE;
                }
            }
            if (is_array($value)) {
                $value = static::serializeArray($value);
            }
            return $this->pdo->quote($value, $type);
        }
    }

    /**
     * Convert passed $array to string compatible with sql query
     * @param mixed $array
     * @return string
     */
    static public function serializeArray($array) {
        return json_encode($array);
    }

    /**
     * @param string $expression
     * @return string
     * @throws \InvalidArgumentException
     */
    public function replaceDbExprQuotes($expression) {
        $expression = preg_replace_callback('%``(.*?)``%s', function ($matches) {
            return $this->quoteValue($matches[1]);
        }, $expression);
        $expression = preg_replace_callback('%`(.*?)`%s', function ($matches) {
            return $this->quoteName($matches[1]);
        }, $expression);
        return $expression;
    }

}