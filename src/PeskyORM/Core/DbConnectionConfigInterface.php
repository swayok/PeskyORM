<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface DbConnectionConfigInterface
{
    
    /**
     * @param array $config
     * @param string|null $name
     * @return $this
     */
    static public function fromArray(array $config, ?string $name = null);
    
    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     * @return string
     */
    public function getPdoConnectionString(): string;
    
    /**
     * Connection name (Default: DB name)
     * @return string
     */
    public function getName(): string;
    
    /**
     * @return string
     */
    public function getDbName(): string;
    
    /**
     * @return string
     */
    public function getDbHost(): string;
    
    /**
     * @return string
     */
    public function getDbPort(): string;
    
    /**
     * @return string
     */
    public function getUserName(): string;
    
    /**
     * @return string
     */
    public function getUserPassword(): string;
    
    /**
     * Set options for PDO connection (key-value)
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options);
    
    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions(): array;
    
    /**
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset);
    
    /**
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone(?string $timezone);
    
    /**
     * @return string|null
     */
    public function getDefaultSchemaName(): ?string;
    
    /**
     * @param string|array $defaultSchemaName
     * @return $this
     */
    public function setDefaultSchemaName($defaultSchemaName);
    
    /**
     * Do some action on connect (set charset, default db schema, etc)
     * @param \PDO $connection
     * @return $this
     */
    public function onConnect(\PDO $connection);
    
}