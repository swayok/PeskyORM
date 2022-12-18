<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

interface TableStructureInterface
{
    /**
     * $writable argument:
     *      - true: connection must have access to write data into DB
     *      - false: connection can be read-only
     * This allows to have 2 DB servers:
     *      - master (can modify data),
     *      - slave (read-only replica).
     * Select queries should be sent to slave server (read-only) - it is faster.
     * Insert/Update/Delete queries - to master server.
     */
    public function getConnection(bool $writable = false): DbAdapterInterface;

    /**
     * DB schema name where table is located.
     * If DB does not support schemas - return null.
     * To use default DB schema - return null.
     * @link https://www.postgresql.org/docs/current/ddl-schemas.html
     */
    public function getSchema(): ?string;
    
    public function getTableName(): string;
    
    public function hasColumn(string $columnNameOrAlias): bool;
    
    public function getColumn(string $columnNameOrAlias): TableColumnInterface;

    #[ArrayShape([
        'column' => TableColumnInterface::class,
        'format' => 'null|string',
    ])]
    public function getColumnAndFormat(string $columnNameOrAlias): array;

    /**
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getColumns(): array;

    /**
     * Get columns that really exist in DB
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getRealColumns(): array;

    /**
     * Get columns that do not exist in DB
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getVirtualColumns(): array;

    /**
     * Get columns that store not sensitive values
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getNotPrivateColumns(): array;

    /**
     * Get columns that store not heavy values
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getNotHeavyColumns(): array;

    /**
     * Get real columns that autoupdate values on each save
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getRealAutoupdatingColumns(): array;

    /**
     * Get real columns which values can be modified (not read only)
     * @return TableColumnInterface[] - ['column_name' => TableColumnInterface]
     */
    public function getColumnsWhichValuesCanBeSavedToDb(): array;

    public function getPkColumnName(): ?string;
    
    public function getPkColumn(): ?TableColumnInterface;
    
    public function hasRelation(string $relationName): bool;
    
    public function getRelation(string $relationName): RelationInterface;
    
    /**
     * @return RelationInterface[] - ['relation_name' => RelationInterface]
     */
    public function getRelations(): array;
}