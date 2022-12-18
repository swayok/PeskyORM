<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

/**
 * @property-read TableStructureInterface $tableStructure
 */
trait DelegateTableStructureMethods
{
    public function getConnection(bool $writable = false): DbAdapterInterface
    {
        return $this->tableStructure->getConnection($writable);
    }

    public function getSchema(): ?string
    {
        return $this->tableStructure->getSchema();
    }

    public function getTableName(): string
    {
        return $this->tableStructure->getTableName();
    }

    public function hasColumn(string $columnNameOrAlias): bool
    {
        return $this->tableStructure->hasColumn($columnNameOrAlias);
    }

    public function getColumn(string $columnNameOrAlias): TableColumnInterface
    {
        return $this->tableStructure->getColumn($columnNameOrAlias);
    }

    #[ArrayShape([
        'column' => TableColumnInterface::class,
        'format' => 'null|string',
    ])]
    public function getColumnAndFormat(string $columnNameOrAlias): array
    {
        return $this->tableStructure->getColumnAndFormat($columnNameOrAlias);
    }

    public function getColumns(): array
    {
        return $this->tableStructure->getColumns();
    }

    public function getRealColumns(): array
    {
        return $this->tableStructure->getRealColumns();
    }

    public function getVirtualColumns(): array
    {
        return $this->tableStructure->getVirtualColumns();
    }

    public function getNotPrivateColumns(): array
    {
        return $this->tableStructure->getNotPrivateColumns();
    }

    public function getNotHeavyColumns(): array
    {
        return $this->tableStructure->getNotHeavyColumns();
    }

    public function getRealAutoupdatingColumns(): array
    {
        return $this->tableStructure->getRealAutoupdatingColumns();
    }

    public function getColumnsWhichValuesCanBeSavedToDb(): array
    {
        return $this->tableStructure->getColumnsWhichValuesCanBeSavedToDb();
    }

    public function getPkColumnName(): ?string
    {
        return $this->tableStructure->getPkColumnName();
    }

    public function getPkColumn(): ?TableColumnInterface
    {
        return $this->tableStructure->getPkColumn();
    }

    public function hasRelation(string $relationName): bool
    {
        return $this->tableStructure->hasRelation($relationName);
    }

    public function getRelation(string $relationName): RelationInterface
    {
        return $this->tableStructure->getRelation($relationName);
    }

    public function getRelations(): array
    {
        return $this->tableStructure->getRelations();
    }
}