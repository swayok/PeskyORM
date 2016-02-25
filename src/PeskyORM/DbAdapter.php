<?php

namespace PeskyORM;

abstract class DbAdapter {

    const ADAPTER_MYSQL = 'mysql';
    const ADAPTER_POSTGRES = 'postgres';

    static private $adapters = [
        self::ADAPTER_MYSQL => 'PeskyORM\Adapter\Mysql',
        self::ADAPTER_POSTGRES => 'PeskyORM\Adapter\Postgres'
    ];

    const FETCH_ALL = 'all';
    const FETCH_FIRST = 'first';
    const FETCH_VALUE = 'value';
    const FETCH_COLUMN = 'column';

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
     * @var null|string $queryString
     */
    protected $lastQuery = null;

    static public function addAdapter($name, $className) {
        self::$adapters[$name] = $className;
    }

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
     * @param string $adapterName
     * @param null|string $dbName
     * @param null|string $user
     * @param null|string $password
     * @param string $server
     * @return DbAdapter
     * @throws \InvalidArgumentException
     */
    static public function make(
        $adapterName,
        $dbName = null,
        $user = null,
        $password = null,
        $server = 'localhost'
    ) {
        if (empty($adapterName) || !isset(self::$adapters[$adapterName])) {
            throw new \InvalidArgumentException("DB adapter with name [$adapterName] not exists");
        }
        return new self::$adapters[$adapterName]($dbName, $user, $password, $server);
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
        $this->makePdo();
        $this->wrapConnection();
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
     * @return \PDO
     */
    abstract protected function makePdo();

    public function disconnect() {
        $this->pdo = null;
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
     * @return null|string
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }



}