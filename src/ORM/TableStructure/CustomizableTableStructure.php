<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * This can be used to get most functionality of ORM for
 * any table in DB without creating specific Table, Record and TableStructure class.
 * Example:
 * $table = new Table(new CustomizableTableStructure('table_name'), Record::class);
 * Now you can use $table to work with records through ORM functionality.
 */
class CustomizableTableStructure extends TableStructure
{
    protected bool $anyColumnExists = false;

    public function __construct(
        protected string $tableName,
        protected bool $autoloadColumns = true,
        ?CacheItemPoolInterface $cachePool = null,
        int $cacheDuration = 86400
    ) {
        parent::__construct($cachePool, $cacheDuration);
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function disableColumnExistanceChecks(): static
    {
        $this->anyColumnExists = true;
        return $this;
    }

    protected function registerColumns(): void
    {
        if ($this->autoloadColumns) {
            $this->importMissingColumnsConfigsFromDbTableDescription();
        }
    }

    protected function registerRelations(): void
    {
    }

    public function importMissingColumnsConfigsFromDbTableDescription(): void
    {
        parent::importMissingColumnsConfigsFromDbTableDescription();
    }

    public function addRelation(RelationInterface $relation): void
    {
        parent::addRelation($relation);
    }

    public function addColumn(TableColumnInterface $column): void
    {
        parent::addColumn($column);
    }

    public function hasColumn(string $columnNameOrAlias): bool
    {
        if ($this->anyColumnExists) {
            return true;
        }
        return parent::hasColumn($columnNameOrAlias);
    }
}