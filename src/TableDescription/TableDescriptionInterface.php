<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

interface TableDescriptionInterface extends \Serializable
{
    public function getDbSchema(): ?string;

    public function getTableName(): string;

    /**
     * @return ColumnDescriptionInterface[] - ['column_name' => ColumnDescriptionInterface, ...]
     */
    public function getColumns(): array;

    public function hasColumn(string $columnName): bool;

    /**
     * @throws \InvalidArgumentException when column not exists
     */
    public function getColumn($name): ColumnDescriptionInterface;
}