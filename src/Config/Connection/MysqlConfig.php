<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PeskyORM\Utils\ArgumentValidators;

class MysqlConfig extends DbConnectionConfigAbstract
{
    protected string $dbHost = 'localhost';
    protected string $dbPort = '3306';
    protected string $charset = 'utf8';
    protected ?string $unixSocket = null;

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
        if (!empty($config['charset']) || !empty($config['encoding'])) {
            $object->setCharset(
                !empty($config['charset']) ? $config['charset'] : $config['encoding']
            );
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
        ArgumentValidators::assertNotEmpty('$dbName', $dbName);
        ArgumentValidators::assertNotEmpty('$user', $user);
        ArgumentValidators::assertNotEmpty('$password', $password);
        $this->dbName = $dbName;
        $this->dbUser = $user;
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
    
    public function setUnixSocket(string $unixSocket): static
    {
        $this->unixSocket = $unixSocket;
        return $this;
    }
    
    public function getUnixSocket(): ?string
    {
        return $this->unixSocket;
    }
    
    public function getDefaultSchemaName(): ?string
    {
        return null;
    }
    
    public function setDefaultSchemaName(string|array $defaultSchemaName): static
    {
        return $this;
    }

    public function setCharset(string $charset): static
    {
        parent::setCharset($charset);
        // Charset for MySQL is provided in DSN (see self::getPdoConnectionString())
        $this->removeOnConnectCallback('charset');
        return $this;
    }
}