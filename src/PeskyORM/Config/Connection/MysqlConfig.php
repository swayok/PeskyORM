<?php

namespace PeskyORM\Config\Connection;

use PeskyORM\Core\DbConnectionConfigInterface;

class MysqlConfig implements DbConnectionConfigInterface {

    protected $name;
    protected $dbName;
    protected $dbUser;
    protected $dbPassword;
    protected $dbHost = 'localhost';
    protected $dbPort = '3306';
    protected $charset = 'utf8';
    protected $unixSocket;
    protected $options = [];
    protected $timezone;
    
    /**
     * @param array $config
     * @param string|null $name
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function fromArray(array $config, ?string $name = null) {
        $dbName = $config['database'] ?: null;
        $user = $config['username'] ?: null;
        $password = $config['password'] ?: null;
        $object = new static($dbName, $user, $password);
        if ($name) {
            $object->setName($name);
        }
        if (!empty($config['host'])) {
            $object->setDbHost($config['host']);
        }
        if (!empty($config['port'])) {
            $object->setDbPort($config['port']);
        }
        if (!empty($config['charset']) || !empty($config['encoding'])) {
            $object->setCharset(!empty($config['charset']) ? $config['charset'] : $config['encoding']);
        }
        if (!empty($config['socket'])) {
            $object->setUnixSocket($config['socket']);
        }
        if (!empty($config['options'])) {
            $object->setOptions($config['options']);
        }
        if (!empty($config['timezone'])) {
            $object->setTimezone($config['timezone']);
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
    public function getPdoConnectionString(): string {
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
    public function getName(): string {
        return $this->name ?: $this->dbName;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getUserName(): string {
        return $this->dbUser;
    }
    
    /**
     * @return string
     */
    public function getUserPassword(): string {
        return $this->dbPassword;
    }
    
    /**
     * @return string
     */
    public function getDbName(): string {
        return $this->dbName;
    }
    
    /**
     * @param string $charset
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setCharset(string $charset) {
        if (empty($charset)) {
            throw new \InvalidArgumentException('DB charset argument cannot be empty');
        }
        $this->charset = $charset;
        return $this;
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
    public function getDbHost(): string {
        return $this->dbHost;
    }

    /**
     * @param string|int $dbPort
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbPort($dbPort) {
        if (!is_numeric($dbPort) || !ctype_digit((string)$dbPort)) {
            throw new \InvalidArgumentException('DB port argument must be an integer number');
        }
        $this->dbPort = (string)$dbPort;
        return $this;
    }

    public function getDbPort(): string {
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
    public function getOptions(): array {
        return $this->options;
    }
    
    /**
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone(?string $timezone) {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Do some action on connect (set charset, default db schema, etc)
     * @param \PDO $connection
     * @return $this
     */
    public function onConnect(\PDO $connection) {
        if (isset($this->timezone)) {
            $connection->prepare('set time_zone="' . $this->timezone . '"')->execute();
        }
        return $this;
    }
    
    /**
     * @return string|null
     */
    public function getDefaultSchemaName(): ?string {
        return null;
    }

    /**
     * @param string|array $defaultSchemaName
     * @return $this
     */
    public function setDefaultSchemaName($defaultSchemaName) {
        return $this;
    }
}