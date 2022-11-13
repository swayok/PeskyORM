<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface NormalJoinConfigInterface extends JoinConfigInterface
{

    /**
     * Get source table name
     */
    public function getTableName(): ?string;

    /**
     * Get source table schema
     */
    public function getTableSchema(): ?string;

    /**
     * Get source table alias
     */
    public function getTableAlias(): ?string;

    /**
     * Get source table column name
     */
    public function getColumnName(): ?string;

    /**
     * Get foreign table column name
     */
    public function getForeignColumnName(): ?string;

    /**
     * Get foreign table name
     */
    public function getForeignTableName(): ?string;

    /**
     * Get foreign table schema
     */
    public function getForeignTableSchema(): ?string;

    /**
     * Get additional join conditions.
     * By default, join adds only one condition:
     * "ON LocalTableAlias.local_column_name = ForeignTableAlias.foreign_column_name".
     * These are additinal conditions to be added to default condition.
     */
    public function getAdditionalJoinConditions(): array;

    /**
     * List of foreign table columns to select their values
     */
    public function getForeignColumnsToSelect(): array;
}