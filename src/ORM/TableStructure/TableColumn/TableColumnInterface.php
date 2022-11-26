<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\ORM\TableStructure\RelationInterface;

interface TableColumnInterface
{
    public function getName(): string;

    public function isNullableValues(): bool;

    public function isPrimaryKey(): bool;

    public function isValueMustBeUnique(): bool;

    public function isUniqueContraintCaseSensitive(): bool;

    public function getUniqueContraintAdditonalColumns(): array;

    public function isReal(): bool;

    public function isValuesModificationAllowed(): bool;

    public function isHeavyValues(): bool;

    public function isForeignKey(): bool;

    public function getForeignKeyRelation(): ?RelationInterface;

    public function isFile(): bool;

    public function isPrivateValues(): bool;

    public function isAutoUpdatingValues(): bool;

    /**
     * @deprecated
     */
    public function shouldConvertEmptyStringToNull(): bool;

    /**
     * @deprecated
     */
    public function shouldTrimValues(): bool;

    /**
     * @deprecated
     */
    public function shouldLowercaseValues(): bool;

    /**
     * Returns default value as it is when it was added to column
     */
    public function getDefaultValue(): mixed;

    /**
     * Validates original default value and returns it.
     * If default value is not set - returns $fallbackValue after validation.
     * @throws \UnexpectedValueException when default value or $fallbackValue are invalid
     */
    public function getValidDefaultValue(mixed $fallbackValue = null): mixed;

    public function hasDefaultValue(): bool;

    public function getRelations(): array;

    public function hasRelation(string $relationName): bool;

    public function getRelation(string $relationName): RelationInterface;

    public function addRelation(RelationInterface $relation): static;

    /**
     * Get list of column names including formatters.
     * Example for column 'created_at':
     * ['created_at', 'created_at_as_date', 'created_at_as_carbon', ...]
     * Column values will be accessible through Record by any returned name
     */
    public function getPossibleColumnNames(): array;
}