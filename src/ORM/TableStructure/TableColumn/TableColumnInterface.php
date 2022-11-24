<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

interface TableColumnInterface
{
    public function setTableStructure(TableStructureInterface $tableStructure): static;

    public function getName(): string;

    public function isValueCanBeNull(): bool;

    public function isEmptyStringMustBeConvertedToNull(): bool;

    public function isValueTrimmingRequired(): bool;

    public function isValueLowercasingRequired(): bool;

    public function isItPrimaryKey(): bool;

    public function isValueMustBeUnique(): bool;

    public function isUniqueContraintCaseSensitive(): bool;

    public function getUniqueContraintAdditonalColumns(): array;

    public function isItExistsInDb(): bool;

    public function isValueCanBeSetOrChanged(): bool;

    public function isValueHeavy(): bool;

    public function isItAForeignKey(): bool;

    public function isItAFile(): bool;

    public function isItAnImage(): bool;

    public function isValuePrivate(): bool;

    public function isAutoUpdatingValue(): bool;

    public function setDefaultValue(mixed $defaultValue): static;

    public function getValidDefaultValue(mixed $fallbackValue = null): mixed;

    public function hasDefaultValue(): bool;

    public function getRelations(): array;

    public function hasRelation(string $relationName): bool;

    public function getRelation(string $relationName): RelationInterface;

    /**
     * Get list of column names including formatters.
     * Example for column 'created_at':
     * ['created_at', 'created_at_as_date', 'created_at_as_carbon', ...]
     * Column values will be accessible through Record by any returned name
     */
    public function getPossibleColumnNames(): array;
}