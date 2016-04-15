<?php

namespace PeskyORM\Core;

use PeskyORM\DbColumnConfig;
use PeskyORM\DbExpr;

abstract class DbAdapter implements DbAdapterInterface {

    const VALUE_QUOTES = '';
    const NAME_QUOTES = '';

    // db-specific values for bool data type
    const BOOL_TRUE = '1';
    const BOOL_FALSE = '0';

    // db-specific value for unlimited amount of query results (ex: SELECT .. OFFSET 10 LIMIT 0)
    const NO_LIMIT = '0';

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
        self::$connectionWrapper = $wrapper;
    }

    /**
     * Remove PDO connection wrapper. This does not unwrap existing PDO objects
     */
    static public function unsetConnectionWrapper() {
        self::$connectionWrapper = null;
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
        if (is_callable(self::$connectionWrapper)) {
            $this->pdo = call_user_func(self::$connectionWrapper, $this, $this->pdo);
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