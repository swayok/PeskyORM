<?php

namespace PeskyORM\Config\Connection;

use PeskyORM\Core\DbConnectionConfigInterface;

class MysqlConfig implements DbConnectionConfigInterface {

    protected $dbName;
    protected $dbUser;
    protected $dbPassword;
    protected $dbHost = 'localhost';
    protected $dbPort = '3306';
    protected $charset = 'utf8';
    protected $unixSocket;
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
        if (array_key_exists('charset', $config)) {
            $object->setCharset($config['charset']);
        }
        if (array_key_exists('socket', $config)) {
            $object->setUnixSocket($config['socket']);
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
    }

    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     * @return string
     */
    public function getPdoConnectionString() {
        if ($this->unixSocket === null) {
            $ret = 'mysql:host=' . $this->dbHost . ';port=' . $this->dbPort;
        } else {
            $ret = 'mysql:unix_socket=' . $this->unixSocket;
        }
        $ret .= ';dbname=' . $this->dbName;
        if ($this->charset !== null) {
            $ret .= ';charset=' . $this->charset;
        }
        return $ret;
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
     * @param string $charset
     * @return $this
     */
    public function setCharset($charset) {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getCharset() {
        return $this->charset;
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
     * @param string $unixSocket
     * @return $this
     */
    public function setUnixSocket($unixSocket) {
        $this->unixSocket = $unixSocket;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getUnixSocket() {
        return $this->unixSocket;
    }

    /**
     * Set options for PDO connection (key-value)
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options) {
        $this->options = $options;
        return $this;
    }

    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }
}