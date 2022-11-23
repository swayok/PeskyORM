<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

class MysqlConfig implements DbConnectionConfigInterface
{
    protected string $dbName;
    protected string $dbUser;
    protected string $dbPassword;
    protected ?string $configName = null;
    protected string $dbHost = 'localhost';
    protected string $dbPort = '3306';
    protected string $charset = 'utf8';
    protected ?string $unixSocket = null;
    protected array $options = [];
    protected ?string $timezone = null;
    
    /**
     * @throws \InvalidArgumentException
     * @noinspection DuplicatedCode
     */
    public static function fromArray(array $config, ?string $name = null): MysqlConfig
    {
        $dbName = $config['database'] ?? null;
        $user = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        if (empty($dbName)) {
            throw new \InvalidArgumentException('$config argument must contain not empty \'database\' key value');
        }
        if (empty($user)) {
            throw new \InvalidArgumentException('$config argument must contain not empty \'username\' key value');
        }
        if (empty($password)) {
            throw new \InvalidArgumentException('$config argument must contain not empty \'password\' key value');
        }
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
     * @throws \InvalidArgumentException
     * @noinspection DuplicatedCode
     */
    public function __construct(
        string $dbName,
        string $user,
        string $password
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
     */
    public function getPdoConnectionString(): string
    {
        if ($this->unixSocket === null) {
            $ret = 'mysql:host=' . $this->dbHost . ';port=' . $this->dbPort;
        } else {
            $ret = 'mysql:unix_socket=' . $this->unixSocket;
        }
        $ret .= ';dbname=' . $this->dbName;
        if ($this->charset) {
            $ret .= ';charset=' . $this->charset;
        }
        return $ret;
    }
    
    public function getName(): string
    {
        return $this->configName ?: $this->dbName;
    }
    
    public function setName(string $name): MysqlConfig
    {
        $this->configName = $name;
        return $this;
    }
    
    public function getUserName(): string
    {
        return $this->dbUser;
    }
    
    public function getUserPassword(): string
    {
        return $this->dbPassword;
    }
    
    public function getDbName(): string
    {
        return $this->dbName;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setCharset(string $charset): MysqlConfig
    {
        if (empty($charset)) {
            throw new \InvalidArgumentException('DB charset argument cannot be empty');
        }
        $this->charset = $charset;
        return $this;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setDbHost(string $dbHost): MysqlConfig
    {
        if (empty($dbHost)) {
            throw new \InvalidArgumentException('DB host argument cannot be empty');
        }
        $this->dbHost = $dbHost;
        return $this;
    }
    
    public function getDbHost(): string
    {
        return $this->dbHost;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setDbPort(int|string $dbPort): MysqlConfig
    {
        if (!is_numeric($dbPort) || !preg_match('%^\s*\d+\s*$%', (string)$dbPort)) {
            throw new \InvalidArgumentException('DB port argument must be a positive integer number or numeric string');
        }
        $this->dbPort = trim((string)$dbPort);
        return $this;
    }
    
    public function getDbPort(): string
    {
        return $this->dbPort;
    }
    
    public function setUnixSocket(string $unixSocket): MysqlConfig
    {
        $this->unixSocket = $unixSocket;
        return $this;
    }
    
    public function getUnixSocket(): ?string
    {
        return $this->unixSocket;
    }
    
    /**
     * Set options for PDO connection (key-value)
     */
    public function setOptions(array $options): MysqlConfig
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * GET options for PDO connection
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    
    public function setTimezone(?string $timezone): MysqlConfig
    {
        $this->timezone = $timezone;
        return $this;
    }
    
    /**
     * Do some action on connect (set charset, default db schema, etc)
     */
    public function onConnect(\PDO $connection): MysqlConfig
    {
        if ($this->timezone) {
            $connection
                ->prepare('set time_zone="' . $this->timezone . '"')
                ->execute();
        }
        return $this;
    }
    
    public function getDefaultSchemaName(): ?string
    {
        return null;
    }
    
    public function setDefaultSchemaName(string|array $defaultSchemaName): MysqlConfig
    {
        return $this;
    }
}