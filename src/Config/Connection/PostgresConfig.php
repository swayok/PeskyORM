<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PDO;
use PeskyORM\Core\DbConnectionConfigInterface;

class PostgresConfig implements DbConnectionConfigInterface
{
    
    protected $name;
    protected $dbName;
    protected $dbUser;
    protected $dbPassword;
    protected $dbHost = 'localhost';
    protected $dbPort = '5432';
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    protected $searchPath = 'public';
    protected $defaultSchemaName = 'public';
    protected $charset = 'UTF8';
    protected $timezone;
    protected $sslConfigs = [];
    
    /**
     * @param array $config
     * @param string|null $name
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $config, ?string $name = null)
    {
        $dbName = $config['database'] ?? null;
        $user = $config['username'] ?? null;
        $password = $config['password'] ?? null;
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
        } elseif (!is_string($dbName)) {
            throw new \InvalidArgumentException('DB name argument must be a string');
        }
        $this->dbName = $dbName;
        
        if (empty($user)) {
            throw new \InvalidArgumentException('DB user argument cannot be empty');
        } elseif (!is_string($user)) {
            throw new \InvalidArgumentException('DB user argument must be a string');
        }
        $this->dbUser = $user;
        
        if (empty($password)) {
            throw new \InvalidArgumentException('DB password argument cannot be empty');
        } elseif (!is_string($password)) {
            throw new \InvalidArgumentException('DB password argument must be a string');
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
    
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?: $this->dbName;
    }
    
    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->dbUser;
    }
    
    /**
     * @return string
     */
    public function getUserPassword(): string
    {
        return $this->dbPassword;
    }
    
    /**
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }
    
    /**
     * @param string $dbHost
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbHost($dbHost)
    {
        if (empty($dbHost)) {
            throw new \InvalidArgumentException('DB host argument cannot be empty');
        } elseif (!is_string($dbHost)) {
            throw new \InvalidArgumentException('DB host argument must be a string');
        }
        $this->dbHost = $dbHost;
        return $this;
    }
    
    /**
     * @return null|string
     */
    public function getDbHost(): string
    {
        return $this->dbHost;
    }
    
    /**
     * @param string|int $dbPort
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbPort($dbPort)
    {
        if (!is_numeric($dbPort) || !ctype_digit((string)$dbPort)) {
            throw new \InvalidArgumentException('DB port argument must be an integer number');
        }
        $this->dbPort = (string)$dbPort;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getDbPort(): string
    {
        return $this->dbPort;
    }
    
    /**
     * Set options for PDO connection (key-value)
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    
    /**
     * @return string
     */
    public function getDefaultSchemaName(): ?string
    {
        return $this->defaultSchemaName;
    }
    
    /**
     * @param string|array $defaultSchemaName
     * @return $this
     */
    public function setDefaultSchemaName($defaultSchemaName)
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
     * @param string $charset
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setCharset(string $charset)
    {
        if (empty($charset)) {
            throw new \InvalidArgumentException('DB charset argument cannot be empty');
        }
        $this->charset = $charset;
        return $this;
    }
    
    /**
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone(?string $timezone)
    {
        $this->timezone = $timezone;
        return $this;
    }
    
    /**
     * Do some action on connect (set charset, default db schema, etc)
     * @param PDO $connection
     * @return $this
     */
    public function onConnect(PDO $connection)
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