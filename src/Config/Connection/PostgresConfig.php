<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PDO;

class PostgresConfig implements DbConnectionConfigInterface
{
    protected string $dbName;
    protected string $dbUser;
    protected string $dbPassword;
    protected ?string $configName = null;
    protected string $dbHost = 'localhost';
    protected string $dbPort = '5432';
    protected array $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    protected string $searchPath = 'public';
    protected string $defaultSchemaName = 'public';
    protected string $charset = 'UTF8';
    protected ?string $timezone = null;
    protected array $sslConfigs = [];
    
    /**
     * @throws \InvalidArgumentException
     * @noinspection DuplicatedCode
     */
    public static function fromArray(array $config, ?string $name = null): PostgresConfig
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
        if (!empty($config['options'])) {
            $object->setOptions($config['options']);
        }
        if (!empty($config['schema'])) {
            $object->setDefaultSchemaName($config['schema']);
        }
        if (!empty($config['timezone'])) {
            $object->setTimezone($config['timezone']);
        }
        if (!empty($config['charset']) || !empty($config['encoding'])) {
            $object->setCharset(!empty($config['charset']) ? $config['charset'] : $config['encoding']);
        }
        foreach (['sslmode', 'sslcert', 'sslkey', 'sslrootcert'] as $option) {
            if (isset($config[$option])) {
                $object->sslConfigs[$option] = $config[$option];
            }
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
     * @return string
     */
    public function getPdoConnectionString(): string
    {
        $dsn = 'pgsql:host=' . $this->dbHost . ';port=' . $this->dbPort . ';dbname=' . $this->dbName;
        foreach ($this->sslConfigs as $option => $value) {
            $dsn .= ";{$option}={$value}";
        }
        return $dsn;
    }
    
    public function getName(): string
    {
        return $this->configName ?: $this->dbName;
    }
    
    public function setName(string $name): PostgresConfig
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
    public function setDbHost(string $dbHost): PostgresConfig
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
    public function setDbPort(int|string $dbPort): PostgresConfig
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
    
    /**
     * Set options for PDO connection (key-value)
     */
    public function setOptions(array $options): PostgresConfig
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
    
    public function getDefaultSchemaName(): string
    {
        return $this->defaultSchemaName;
    }
    
    public function setDefaultSchemaName(string|array $defaultSchemaName): PostgresConfig
    {
        if (is_array($defaultSchemaName)) {
            $this->defaultSchemaName = array_values($defaultSchemaName)[0];
        } else {
            $this->defaultSchemaName = $defaultSchemaName;
        }
        $this->searchPath = implode(',', (array)$defaultSchemaName);
        return $this;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setCharset(string $charset): PostgresConfig
    {
        if (empty($charset)) {
            throw new \InvalidArgumentException('DB charset argument cannot be empty');
        }
        $this->charset = $charset;
        return $this;
    }
    
    public function setTimezone(?string $timezone): PostgresConfig
    {
        $this->timezone = $timezone;
        return $this;
    }
    
    /**
     * Do some action on connect (set charset, default db schema, etc)
     */
    public function onConnect(PDO $connection): PostgresConfig
    {
        $connection->prepare("SET NAMES '{$this->charset}'")
            ->execute();
        $connection->prepare("SET search_path TO {$this->searchPath}")
            ->execute();
        if (isset($this->timezone)) {
            $connection->prepare("SET TIME ZONE '{$this->timezone}'")
                ->execute();
        }
        return $this;
    }
}