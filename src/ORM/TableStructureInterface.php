<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

interface TableStructureInterface
{
    
    /**
     * @return $this
     */
    public static function getInstance(): TableStructureInterface;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    public static function getConnectionName(bool $writable): string;
    
    /**
     * @return string|null
     */
    public static function getSchema(): ?string;
    
    /**
     * @return string
     */
    public static function getTableName(): string;
    
    /**
     * @param string $columnName
     * @return bool
     */
    public static function hasColumn(string $columnName): bool;
    
    /**
     * @param string $columnName
     * @return Column
     */
    public static function getColumn(string $columnName): Column;
    
    /**
     * @return Column[]
     */
    public static function getColumns(): array;
    
    /**
     * @return string|null
     */
    public static function getPkColumnName(): ?string;
    
    /**
     * @return Column|null
     */
    public static function getPkColumn(): ?Column;
    
    /**
     * @return bool
     */
    public static function hasPkColumn(): bool;
    
    /**
     * @return bool
     */
    public static function hasFileColumns(): bool;
    
    /**
     * @param string $columnName
     * @return bool
     */
    public static function hasFileColumn(string $columnName): bool;
    
    /**
     * @return Column[] = array('column_name' => Column)
     */
    public static function getFileColumns(): array;
    
    /**
     * @param string $relationName
     * @return bool
     */
    public static function hasRelation(string $relationName): bool;
    
    /**
     * @param string $relationName
     * @return Relation
     */
    public static function getRelation(string $relationName): Relation;
    
    /**
     * @return Relation[]
     */
    public static function getRelations(): array;
    
    
}