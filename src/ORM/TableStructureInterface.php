<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

interface TableStructureInterface
{
    
    /**
     * @return static
     */
    public static function getInstance(): TableStructureInterface;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    public static function getConnectionName(bool $writable): string;
    
    public static function getSchema(): ?string;
    
    public static function getTableName(): string;
    
    public static function hasColumn(string $columnName): bool;
    
    public static function getColumn(string $columnName): Column;
    
    /**
     * @return Column[]
     */
    public static function getColumns(): array;
    
    public static function getPkColumnName(): ?string;
    
    public static function getPkColumn(): ?Column;
    
    public static function hasPkColumn(): bool;
    
    public static function hasFileColumns(): bool;
    
    public static function hasFileColumn(string $columnName): bool;
    
    /**
     * @return Column[] - ['column_name' => Column]
     */
    public static function getFileColumns(): array;
    
    public static function hasRelation(string $relationName): bool;
    
    public static function getRelation(string $relationName): Relation;
    
    /**
     * @return Relation[]
     */
    public static function getRelations(): array;
    
    
}