<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface DbConnectionConfigInterface
{
    
    public static function fromArray(array $config, ?string $name = null): DbConnectionConfigInterface;
    
    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     */
    public function getPdoConnectionString(): string;
    
    /**
     * Connection name (Default: DB name)
     */
    public function getName(): string;
    
    public function getDbName(): string;
    
    public function getDbHost(): string;
    
    public function getDbPort(): string;
    
    public function getUserName(): string;
    
    public function getUserPassword(): string;
    
    /**
     * Set options for PDO connection (key-value)
     */
    public function setOptions(array $options): DbConnectionConfigInterface;
    
    /**
     * GET options for PDO connection
     */
    public function getOptions(): array;
    
    public function setCharset(string $charset): DbConnectionConfigInterface;
    
    public function setTimezone(?string $timezone): DbConnectionConfigInterface;
    
    public function getDefaultSchemaName(): ?string;
    
    /**
     * @param string|array $defaultSchemaName
     */
    public function setDefaultSchemaName($defaultSchemaName): DbConnectionConfigInterface;
    
    /**
     * Do some action on connect (set charset, default db schema, etc)
     */
    public function onConnect(\PDO $connection): DbConnectionConfigInterface;
    
}