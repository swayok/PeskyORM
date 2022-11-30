<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

interface TableColumnInterface
{
    public function getName(): string;

    /**
     * One of TableColumnDataType constants
     * @see TableColumnDataType
     */
    public function getDataType(): string;

    public function setTableStructure(TableStructureInterface $tableStructure): static;

    public function getTableStructure(): ?TableStructureInterface;

    public function isNullableValues(): bool;

    public function isPrimaryKey(): bool;

    /**
     * Values in column are unique within self::getUniqueContraintAdditonalColumns().
     * @see CanBeUnique for implementation.
     */
    public function isValueMustBeUnique(): bool;

    /**
     * Should return true if uniqueness validation should perform
     * case sensitive validation.
     * Default: true
     * Case sensitive validation is faster
     */
    public function isUniqueContraintCaseSensitive(): bool;

    public function getUniqueContraintAdditonalColumns(): array;

    /**
     * Should return true if column really exists in DB.
     * Any column that does not exist in DB should return false.
     * In this case ORM will not try to save its value to DB.
     * Usually used for columns that provide convenient access to data
     * stored in real columns.
     * For example: json columns may store a lot of data, and it is hard
     * to access parts of it. Virtual columns may help here.
     * Also, it is possible to modify part of data in real column
     * through virtual column. This way it will be safer than direct
     * modification of real column data.
     */
    public function isReal(): bool;

    /**
     * Should return true if values received from DB
     * is not allowed to be modified.
     * Usually used for columns like created_at
     * when value saved once and should never change later.
     * Value still can be set if record does not exist in DB yet.
     */
    public function isReadonly(): bool;

    /**
     * Should return true if column values contain a lot of data
     * and should not be fetched by '*' selects.
     * Usually used for big json and text columns.
     */
    public function isHeavyValues(): bool;

    /**
     * Should return true to hide values from
     * RecordInterface::toArray() and iterators.
     * Private values usually: passwords, tokens.
     * In RecordInterface values can be accessed only directly
     * by column name: $record->column_name or $record->getValue('column_name').
     * @see RecordInterface::toArray()
     * @see RecordInterface::getValue()
     */
    public function isPrivateValues(): bool;

    /**
     * Values should be autoupdated on each save to DB.
     * Usually used for timestamp columns like: updated_at.
     */
    public function isAutoUpdatingValues(): bool;

    /**
     * @see self::isAutoUpdatingValues()
     * @throws TableColumnConfigException when column values are not autoupdatable
     */
    public function getAutoUpdateForAValue(RecordInterface|array $record): mixed;

    /**
     * Should return true if column contains files or data about files.
     * @see RecordInterface::toArrayWithoutFiles()
     */
    public function isFile(): bool;

    // Default values

    /**
     * Returns default value as it is (without validation and normalization).
     * Default value may be an instance of some classes or a closure:
     *  - \Closure: function() { return 'default value'; }.
     *  - instance of DbExpr
     *  - instance of SelectQueryBuilderInterface
     *  - instance of RecordsSet
     * @throws TableColumnConfigException when default value is not set
     */
    public function getDefaultValue(): mixed;

    /**
     * Get validated normalized default value.
     * @see self::getDefaultValue()
     * @throws TableColumnConfigException when default value is not set or invalid
     */
    public function getValidDefaultValue(): mixed;

    public function hasDefaultValue(): bool;

    // Relations

    public function getRelations(): array;

    public function hasRelation(string $relationName): bool;

    public function getRelation(string $relationName): RelationInterface;

    public function addRelation(RelationInterface $relation): static;

    public function isForeignKey(): bool;

    public function getForeignKeyRelation(): ?RelationInterface;

    // TableStructure utils

    /**
     * Get list of column names including formatters.
     * Example for column 'created_at':
     * ['created_at', 'created_at_as_date', 'created_at_as_carbon', ...]
     * Column values will be accessible through Record by any returned name
     */
    public function getPossibleColumnNames(): array;

    // Values handling

    /**
     * Validates a new value
     * @param mixed|RecordValueContainerInterface $value
     * @param bool $isFromDb - true: value received from DB | false: value is update
     * @param bool $isForCondition - true: value is for condition (less strict) | false: value is for Record
     */
    public function validateValue(
        mixed $value,
        bool $isFromDb = false,
        bool $isForCondition = false
    ): array;

    public function getNewRecordValueContainer(
        RecordInterface $record
    ): RecordValueContainerInterface;


    //public function getValue(RecordValueContainerInterface $valueContainer): void;

    //public function setValue(RecordValueContainerInterface $valueContainer): void;
}