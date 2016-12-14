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
     * @throws \InvalidArgumentException
     */
    static public function fromArray(array $config) {
        $dbName = $config['database'] ?: null;
        $user = $config['username'] ?: null;
        $password = $config['password'] ?: null;
        $object = new static($dbName, $user, $password);
        if (!empty($config['host'])) {
            $object->setDbHost($config['host']);
        }
        if (!empty($config['port'])) {
            $object->setDbPort($config['port']);
        }
        if (!empty($config['charset'])) {
            $object->setCharset($config['charset']);
        }
        if (!empty($config['socket'])) {
            $object->setUnixSocket($config['socket']);
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
     * @throws \InvalidArgumentException
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
     * @throws \InvalidArgumentException
     */
    public function setCharset($charset) {
        if (empty($charset)) {
            throw new \InvalidArgumentException('DB charset argument cannot be empty');
        } else if (!is_string($charset)) {
            throw new \InvalidArgumentException('DB charset argument must be a string');
        }
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
     * @throws \InvalidArgumentException
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
        if (!is_numeric($dbPort) || !ctype_digit((string)$dbPort)) {
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