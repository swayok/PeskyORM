<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

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
    
    public static function getColumn(string $columnName): TableColumnInterface;
    
    /**
     * @return TableColumnInterface[]
     */
    public static function getColumns(): array;
    
    public static function getPkColumnName(): ?string;
    
    public static function getPkColumn(): ?TableColumnInterface;
    
    public static function hasPkColumn(): bool;
    
    public static function hasFileColumns(): bool;
    
    public static function hasFileColumn(string $columnName): bool;
    
    /**
     * @return TableColumnInterface[] - ['column_name' => TableColumn]
     */
    public static function getFileColumns(): array;
    
    public static function hasRelation(string $relationName): bool;
    
    public static function getRelation(string $relationName): RelationInterface;
    
    /**
     * @return RelationInterface[]
     */
    public static function getRelations(): array;
    
    
}