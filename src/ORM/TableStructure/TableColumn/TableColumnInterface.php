<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

interface TableColumnInterface
{
    public function getName(): string;

    public function setTableStructure(TableStructureInterface $tableStructure): static;

    public function getTableStructure(): ?TableStructureInterface;

    public function isNullableValues(): bool;

    public function isPrimaryKey(): bool;

    public function isValueMustBeUnique(): bool;

    public function isUniqueContraintCaseSensitive(): bool;

    public function getUniqueContraintAdditonalColumns(): array;

    public function isReal(): bool;

    public function isReadonly(): bool;

    public function isHeavyValues(): bool;

    public function isForeignKey(): bool;

    public function getForeignKeyRelation(): ?RelationInterface;

    public function isFile(): bool;

    public function isPrivateValues(): bool;

    public function isAutoUpdatingValues(): bool;

    public function getAutoUpdateForAValue(RecordInterface|array $record): mixed;

    /**
     * Returns default value as it is (without validation and normalization).
     * Default value may be an instance of some classes or a closure:
     *  - \Closure: function() { return 'default value'; }.
     *  - instance of DbExpr
     *  - instance of SelectQueryBuilderInterface
     *  - instance of RecordsSet
     * @throws \BadMethodCallException when default value is not set
     */
    public function getDefaultValue(): mixed;

    /**
     * Get validated normalized default value.
     * @see self::getDefaultValue()
     * @throws \BadMethodCallException when default value is not set
     * @throws \UnexpectedValueException when default value is invalid
     */
    public function getValidDefaultValue(): mixed;

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

    /**
     * Validates a new value
     * @param mixed|RecordValueContainerInterface $value
     * @param bool $isFromDb - true: value received from DB | false: value is update
     * @param bool $isForCondition - true: value is for condition (less strict) | false: value is for Record
     * @throws \UnexpectedValueException
     */
    public function validateValue(mixed $value, bool $isFromDb = false, bool $isForCondition = false): array;

    //public function getValue(RecordValueContainerInterface $valueContainer): void;
}