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
        if (!empty($config['host'])) {
            $object->setDbHost($config['host']);
        }
        if (!empty($config['port'])) {
            $object->setDbPort($config['port']);
        }
        if (!empty($config['options'])) {
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
        } else if (!is_string($dbName)) {
            throw new \InvalidArgumentException('DB name argument must be a string');
        }
        $this->dbName = $dbName;
        
        if (empty($user)) {
            throw new \InvalidArgumentException('DB user argument cannot be empty');
        } else if (!is_string($user)) {
            throw new \InvalidArgumentException('DB user argument must be a string');
        }
        $this->dbUser = $user;
        
        if (empty($password)) {
            throw new \InvalidArgumentException('DB password argument cannot be empty');
        } else if (!is_string($password)) {
            throw new \InvalidArgumentException('DB password argument must be a string');
        }
        $this->dbPassword = $password;
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
        if (empty($dbHost)) {
            throw new \InvalidArgumentException('DB host argument cannot be empty');
        } else if (!is_string($dbHost)) {
            throw new \InvalidArgumentException('DB host argument must be a string');
        }
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
     * @throws \InvalidArgumentException
     */
    public function setDbPort($dbPort) {
        if (!is_numeric($dbPort) || !preg_match('%^\d+$%', $dbPort)) {
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

    /**
     * @return null
     */
    public function getCharset() {
        return null;
    }
}