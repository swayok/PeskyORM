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
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public static function getColumns(): array;

    /**
     * Get columns that really exist in DB
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public static function getRealColumns(): array;

    /**
     * Get columns that do not exist in DB
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public static function getVirtualColumns(): array;
    
    public static function getPkColumnName(): ?string;
    
    public static function getPkColumn(): ?TableColumnInterface;
    
    public static function hasRelation(string $relationName): bool;
    
    public static function getRelation(string $relationName): RelationInterface;
    
    /**
     * @return RelationInterface[] - ['relation_name' => RelationInterface]
     */
    public static function getRelations(): array;
    
    
}