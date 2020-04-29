<?php

namespace PeskyORM\ORM;

interface TableStructureInterface {
    
    /**
     * @return $this
     */
    static public function getInstance(): TableStructureInterface;
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    static public function getConnectionName(bool $writable): string;
    
    /**
     * @return string|null
     */
    static public function getSchema(): ?string;
    
    /**
     * @return string
     */
    static public function getTableName(): string;
    
    /**
     * @param string $columnName
     * @return bool
     */
    static public function hasColumn(string $columnName): bool;
    
    /**
     * @param string $columnName
     * @return Column
     */
    static public function getColumn(string $columnName): Column;
    
    /**
     * @return Column[]
     */
    static public function getColumns(): array;
    
    /**
     * @return string|null
     */
    static public function getPkColumnName(): ?string;
    
    /**
     * @return Column|null
     */
    static public function getPkColumn(): ?Column;
    
    /**
     * @return bool
     */
    static public function hasPkColumn(): bool;
    
    /**
     * @return bool
     */
    static public function hasFileColumns(): bool;
    
    /**
     * @param string $columnName
     * @return bool
     */
    static public function hasFileColumn(string $columnName): bool;
    
    /**
     * @return Column[] = array('column_name' => Column)
     */
    static public function getFileColumns(): array;
    
    /**
     * @param string $relationName
     * @return bool
     */
    static public function hasRelation(string $relationName): bool;
    
    /**
     * @param string $relationName
     * @return Relation
     */
    static public function getRelation(string $relationName): Relation;
    
    /**
     * @return Relation[]
     */
    static public function getRelations(): array;


}