<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PDO;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Utils\ArgumentValidators;

class PostgresConfig extends DbConnectionConfigAbstract
{
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
    protected array $sslConfigs = [];

    /**
     * @throws \InvalidArgumentException
     * @noinspection DuplicatedCode
     */
    public static function fromArray(array $config, ?string $name = null): static
    {
        $dbName = $config['database'] ?? null;
        $user = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        if (empty($dbName)) {
            throw new \InvalidArgumentException(
                '$config argument must contain not empty \'database\' key value'
            );
        }
        if (empty($user)) {
            throw new \InvalidArgumentException(
                '$config argument must contain not empty \'username\' key value'
            );
        }
        if (empty($password)) {
            throw new \InvalidArgumentException(
                '$config argument must contain not empty \'password\' key value'
            );
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
            $object->setCharset(
                !empty($config['charset']) ? $config['charset'] : $config['encoding']
            );
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
        ArgumentValidators::assertNotEmpty('$dbName', $dbName);
        ArgumentValidators::assertNotEmpty('$user', $user);
        ArgumentValidators::assertNotEmpty('$password', $password);
        $this->dbName = $dbName;
        $this->dbUser = $user;
        $this->dbPassword = $password;
    }

    /**
     * Get PDO connection string
     * Example: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass
     * @return string
     */
    public function getPdoConnectionString(): string
    {
        $dsn = 'pgsql:host=' . $this->dbHost
            . ';port=' . $this->dbPort
            . ';dbname=' . $this->dbName;
        foreach ($this->sslConfigs as $option => $value) {
            $dsn .= ";{$option}={$value}";
        }
        return $dsn;
    }

    public function getDefaultSchemaName(): string
    {
        return $this->defaultSchemaName;
    }

    public function setDefaultSchemaName(string|array $defaultSchemaName): static
    {
        if (is_array($defaultSchemaName)) {
            $this->defaultSchemaName = array_values($defaultSchemaName)[0];
        } else {
            $this->defaultSchemaName = $defaultSchemaName;
        }
        $this->searchPath = implode(',', (array)$defaultSchemaName);
        $this->addOnConnectCallback(
            function (DbAdapterInterface $connection) {
                $connection->setSearchPath($this->searchPath);
            },
            'search_path'
        );
        return $this;
    }
}