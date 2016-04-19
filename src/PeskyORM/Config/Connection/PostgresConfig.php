<?php

namespace PeskyORM\Config\Connection;

use PeskyORM\Core\DbConnectionConfigInterface;

class PostgresConfig implements DbConnectionConfigInterface {

    protected $dbName;
    protected $dbUser;
    protected $dbPassword;
    protected $dbHost = 'localhost';
    protected $dbPort = '5432';
    protected $options = [];

    /**
     * @param array $config
     * @return static
     */
    static public function fromArray(array $config) {
        $dbName = array_key_exists('name', $config) ? $config['name'] : null;
        $user = array_key_exists('user', $config) ? $config['user'] : null;
        $password = array_key_exists('password', $config) ? $config['password'] : null;
        $object = new static($dbName, $user, $password);
        if (array_key_exists('host', $config)) {
            $object->setDbHost($config['host']);
        }
        if (array_key_exists('port', $config)) {
            $object->setDbPort($config['port']);
        }
        if (array_key_exists('options', $config)) {
            $object->setOptions($config['options']);
        }
        return $object;
    }

    /**
     * @param string $dbName
     * @param string $user
     * @param string $password
     */
    public function __construct(
        $dbName,
        $user,
        $password
    ) {
        if (empty($dbName)) {
            throw new \InvalidArgumentException('DB name argument cannot be empty');
        }
        $this->dbName = $dbName;
        if (empty($user)) {
            throw new \InvalidArgumentException('DB user argument cannot be empty');
        }
        $this->dbUser = $user;
        if (empty($password)) {
            throw new \InvalidArgumentException('DB password argument cannot be empty');
        }
        $this->dbPassword = $password;
        if (!empty($host)) {
            $this->dbHost = $host;
        }
        if (!empty($port)) {
            if (!preg_match('%^\d+$%', $port)) {
                throw new \InvalidArgumentException('DB port argument must be an integer number');
            }
            $this->dbPort = $port;
        }
    }

    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     * @return string
     */
    public function getPdoConnectionString() {
        return 'pgsql:host=' . $this->dbHost . ';port=' . $this->dbPort . ';dbname=' . $this->dbName;
    }

    /**
     * @return string
     */
    public function getUserName() {
        return $this->dbUser;
    }

    /**
     * @return string
     */
    public function getUserPassword() {
        return $this->dbPassword;
    }

    /**
     * @return string
     */
    public function getDbName() {
        return $this->dbName;
    }

    /**
     * @param string $dbHost
     * @return $this
     */
    public function setDbHost($dbHost) {
        $this->dbHost = $dbHost;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDbHost() {
        return $this->dbHost;
    }

    /**
     * @param string $dbPort
     * @return $this
     */
    public function setDbPort($dbPort) {
        if (!preg_match('%^\d+$%', $dbPort)) {
            throw new \InvalidArgumentException('DB port argument must be an integer number');
        }
        $this->dbPort = $dbPort;
        return $this;
    }

    /**
     * @return int|null|string
     */
    public function getDbPort() {
        return $this->dbPort;
    }

    /**
     * Set options for PDO connection (key-value)
     * @param array $options
     */
    public function setOptions(array $options) {
        $this->options = $options;
    }

    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }
}