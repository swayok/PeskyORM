<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesEn;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ArgumentValidators;

abstract class TableColumnAbstract implements TableColumnInterface
{
    protected const AFTER_SAVE_PAYLOAD_KEY = '_for_after_save';

    protected ?string $name = null;
    protected ?TableStructureInterface $tableStructure = null;

    protected bool $valueCanBeNull = false;

    /**
     * This column is private for the object and will be excluded from iteration, toArray(), etc.
     * Access to this column's value can be done only directly. For example $user->password
     */
    protected bool $isPrivate = false;
    /**
     * Is this column exists in DB or not.
     * If not - column valueGetter() must be provided to return a value of this column
     * Record will not save columns that does not exist in DB
     */
    protected bool $isReal = true;
    /**
     * Allow/disallow value setting and modification
     * Record will not save columns that are read only
     */
    protected bool $isReadonly = true;
    /**
     * Then true - value contains a lot of data and should not be fetched by '*' selects
     */
    protected bool $isHeavy = false;

    /**
     * @var mixed|\Closure
     */
    protected mixed $defaultValue = null;
    protected bool $hasDefaultValue = false;
    protected bool $isDefaultValueValidated = false;
    protected mixed $validDefaultValue = null;

    /**
     * @var RelationInterface[]
     */
    protected array $relations = [];
    protected ?RelationInterface $foreignKeyRelation = null;

    /**
     * Function that generates new value for a column for each save operation
     * Usage example: updated_at column
     */
    protected ?\Closure $valueAutoUpdater = null;

    protected ?ColumnValueValidationMessagesInterface $valueValidationMessages = null;

    public function __construct(string $name)
    {
        $this->setName($name);
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('DB column name is not provided');
        }
        return $this->name;
    }

    public function hasName(): bool
    {
        return !empty($this->name);
    }

    /**
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function setName(string $name): static
    {
        ArgumentValidators::assertNotEmpty('$name', $name);
        ArgumentValidators::assertSnakeCase('$name', $name);

        $this->name = $name;
        return $this;
    }

    public function getTableStructure(): ?TableStructureInterface
    {
        return $this->tableStructure;
    }

    public function setTableStructure(TableStructureInterface $tableStructure): static
    {
        $this->tableStructure = $tableStructure;
        return $this;
    }

    protected function getNameForException(?TableColumnInterface $column = null): string
    {
        if (!$column) {
            $column = $this;
        }
        $tableStructure = $column->getTableStructure();
        if ($tableStructure) {
            return get_class($tableStructure) . '->' . $column->getName();
        }
        return static::class . "('{$column->getName()}')";
    }

    public function isNullableValues(): bool
    {
        return $this->valueCanBeNull;
    }

    public function isPrimaryKey(): bool
    {
        return false;
    }

    public function isValueMustBeUnique(): bool
    {
        return false;
    }

    public function isUniqueContraintCaseSensitive(): bool
    {
        return true;
    }

    public function getUniqueContraintAdditonalColumns(): array
    {
        return [];
    }

    public function doesNotExistInDb(): static
    {
        $this->isReal = false;
        return $this;
    }

    /**
     * Is this column exists in DB?
     */
    public function isReal(): bool
    {
        return $this->isReal;
    }

    public function valuesAreReadOnly(): static
    {
        $this->isReadonly = false;
        return $this;
    }

    public function isReadonly(): bool
    {
        return $this->isReadonly;
    }

    public function valuesAreHeavy(): static
    {
        $this->isHeavy = true;
        return $this;
    }

    public function isHeavyValues(): bool
    {
        return $this->isHeavy;
    }

    public function isFile(): bool
    {
        return false;
    }

    public function isPrivateValues(): bool
    {
        return $this->isPrivate;
    }

    public function valuesArePrivate(): static
    {
        $this->isPrivate = true;
        return $this;
    }

    /**
     * @param \Closure $valueGenerator - function (array|RecordInterface $record): mixed { return 'value' }
     */
    public function setValueAutoUpdater(\Closure $valueGenerator): static
    {
        $this->valueAutoUpdater = $valueGenerator;
        return $this;
    }

    public function getAutoUpdateForAValue(RecordInterface|array $record): mixed
    {
        if (empty($this->valueAutoUpdater)) {
            throw new \BadMethodCallException('Value auto updater function is not set');
        }
        return call_user_func($this->valueAutoUpdater, $record);
    }

    public function isAutoUpdatingValues(): bool
    {
        return !empty($this->valueAutoUpdater);
    }

    public function addRelation(RelationInterface $relation): static
    {
        $this->relations[$relation->getName()] = $relation;
        if ($relation->getType() === RelationInterface::BELONGS_TO) {
            $this->setForeignKeyRelation($relation);
        }
        return $this;
    }

    /**
     * @return RelationInterface[]
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    public function hasRelation(string $relationName): bool
    {
        return isset($this->relations[$relationName]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getRelation(string $relationName): RelationInterface
    {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException(
                "TableColumn '{$this->getName()}' is not linked with '{$relationName}' relation"
            );
        }
        return $this->relations[$relationName];
    }

    public function isForeignKey(): bool
    {
        return $this->foreignKeyRelation !== null;
    }

    public function getForeignKeyRelation(): ?RelationInterface
    {
        return $this->foreignKeyRelation;
    }

    /**
     * @param RelationInterface $relation - relation that stores values for this column
     * @throws \InvalidArgumentException
     */
    protected function setForeignKeyRelation(RelationInterface $relation): static
    {
        if ($this->foreignKeyRelation) {
            throw new TableColumnConfigException(
                'Conflict detected for column ' . $this->getName() . ': relation '
                . $relation->getName() . ' pretends to be the source of '
                . 'values for this foreign key but there is already another relation for this: '
                . $this->foreignKeyRelation->getName(),
                $this
            );
        }
        $this->foreignKeyRelation = $relation;
        return $this;
    }

    public function getDefaultValue(): mixed
    {
        if (!$this->hasDefaultValue()) {
            throw new TableColumnConfigException(
                "Default value for column '{$this->getNameForException()}' is not set.",
                $this
            );
        }
        return $this->defaultValue;
    }

    public function getValidDefaultValue(): mixed
    {
        if (!$this->isDefaultValueValidated) {
            $this->isDefaultValueValidated = true;
            $defaultValue = $this->getDefaultValue();
            if (!$this->shouldValidateValue($defaultValue)) {
                // validation is not possible and value is expected to be valid
                $this->validDefaultValue = $defaultValue;
                return $defaultValue;
            }
            if ($defaultValue instanceof \Closure) {
                // closure need to be called each time default value is requested
                // because value can change depending on situation and some state
                $this->validDefaultValue = $defaultValue;
            } else {
                // $defaultValue value is not a closure, so it can be cached for later usage
                $this->validDefaultValue = $this->validateAndNormalizeDefaultValue($defaultValue);
            }
        }
        if ($this->validDefaultValue instanceof \Closure) {
            // we need to validate and normalize value returned from closure
            return $this->validateAndNormalizeDefaultValue($this->validDefaultValue);
        }
        return $this->validDefaultValue;
    }

    /**
     * $defaultValue cannot be DbExpr or SelectQueryBuilderInterface
     * but can be a \Closure.
     * \Closure must be called to generate value to be normalized and validated.
     * \Closure may return DbExpr or SelectQueryBuilderInterface, so these instances
     * should be returned as is.
     * Returns normalized value to be used outside.
     * @throws \UnexpectedValueException when default value is invalid
     */
    protected function validateAndNormalizeDefaultValue(mixed $defaultValue): mixed
    {
        if ($defaultValue instanceof \Closure) {
            $this->validDefaultValue = $defaultValue;
            $defaultValue = $defaultValue();
            if (!$this->shouldValidateValue($defaultValue)) {
                return $defaultValue;
            }
        }

        $normalizedValue = $this->normalizeValueForValidation($defaultValue, false);
        $errors = $this->validateNormalizedValue($normalizedValue, false, false);
        if (count($errors) > 0) {
            throw new TableColumnConfigException(
                "Default value for column {$this->getNameForException()} is not valid. Errors: "
                . implode(', ', $errors),
                $this
            );
        }

        return $this->normalizeValidatedValue($defaultValue);
    }

    public function hasDefaultValue(): bool
    {
        if ($this->isPrimaryKey()) {
            return false;
        }
        return $this->hasDefaultValue;
    }

    /**
     * @param mixed|\Closure $defaultValue - may be a \Closure: function() { return 'default value'; }
     */
    public function setDefaultValue(mixed $defaultValue): static
    {
        if ($this->isPrimaryKey()) {
            throw new TableColumnConfigException(
                'Primary key column ' . $this->getNameForException()
                . ' is not allowed to have default value.',
                $this
            );
        }
        $this->defaultValue = $defaultValue;
        $this->hasDefaultValue = true;
        $this->isDefaultValueValidated = false;
        return $this;
    }

    public function getPossibleColumnNames(): array
    {
        // todo: implement this
        $name = $this->getName();
        return [
            $name,
        ];
        //        $ret = [
        //            $name,
        //        ];
        //        foreach ($this->getValueFormattersNames() as $formatterName) {
        //            $ret[] = $name . '_as_' . $formatterName;
        //        }
        //        return $ret;
    }

    // values management

    public function validateValue(
        mixed $value,
        bool $isFromDb = false,
        bool $isForCondition = false
    ): array {
        return $this->validateNormalizedValue(
            $this->normalizeValueForValidation($value, $isFromDb),
            $isFromDb,
            $isForCondition
        );
    }

    protected function validateNormalizedValue(
        mixed $normalizedValue,
        bool $isFromDb,
        bool $isForCondition
    ): array {
        if (!$this->shouldValidateValue($normalizedValue)) {
            // validation is not possible
            return [];
        }
        // null value?
        if (
            $normalizedValue === null
            && !$this->validateIfNullValueIsAllowed($isFromDb, $isForCondition)
        ) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_CANNOT_BE_NULL
                ),
            ];
        }
        return $this->validateValueDataType($normalizedValue, $isForCondition);
    }

    /**
     * Check if value should be normalized.
     * Used to skip normalization for instances of some objects
     * that cannot be validated but cannot be used as is:
     * - DbExpr
     * - SelectQueryBuilderInterface
     */
    protected function shouldNormalizeValidatedValue(mixed $value): bool
    {
        return !(
            $value instanceof DbExpr
            || $value instanceof SelectQueryBuilderInterface
        );
    }

    /**
     * Check if value should be validated.
     * Used to skip validation for instances of some objects
     * that cannot be validated but cannot be used as is:
     * - DbExpr
     * - SelectQueryBuilderInterface
     */
    protected function shouldValidateValue(mixed $value): bool
    {
        return !(
            $value instanceof DbExpr
            || $value instanceof SelectQueryBuilderInterface
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function validateIfNullValueIsAllowed(
        bool $isFromDb,
        bool $isForCondition
    ): bool {
        return $isForCondition || $this->isNullableValues();
    }

    /**
     * Validate if
     * At this point value cannot be null.
     * Returns array of errors ot empty array if no errors
     */
    abstract protected function validateValueDataType(mixed $normalizedValue, bool $isForCondition): array;

    protected function getValueValidationMessages(): ColumnValueValidationMessagesInterface
    {
        if (!$this->valueValidationMessages) {
            // todo: get ColumnValueValidationMessagesInterface instance from classes container
            $this->valueValidationMessages = new ColumnValueValidationMessagesEn();
        }
        return $this->valueValidationMessages;
    }

    protected function getValueValidationMessage(string $messageId): string
    {
        return $this->getValueValidationMessages()->getMessage($messageId);
    }

    /**
     * Value can be anything including instances of:
     * - RecordValueContainerInterface
     * - RecordsSet
     * - DbExpr
     * - SelectQueryBuilderInterface
     */
    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        if ($value instanceof RecordValueContainerInterface) {
            $value = $value->getValue();
        }
        if ($value instanceof RecordsSet) {
            return $value->getOrmSelect();
        }
        return $value;
    }

    /**
     * Apply additional normalizations for value.
     * Value already normalized for validation and validated.
     * @see self::normalizeValueForValidation()
     * @see self::validateNormalizedValue()
     * This normalizer should be used in value setter and valid default value getter.
     * @see self::getValidDefaultValue()
     * @see self::setValue()
     * Returned value is final. No more modifications expected.
     */
    protected function normalizeValidatedValue(mixed $validatedValue): mixed
    {
        if ($validatedValue === null) {
            return null;
        }

        if (!$this->shouldNormalizeValidatedValue($validatedValue)) {
            return $validatedValue;
        }

        return $this->normalizeValidatedValueType($validatedValue);
    }

    /**
     * Normalize value according to column type.
     * Value cannot be null, DbExpr or SelectQueryBuilderInterface.
     * @see self::normalizeValidatedValue()
     * @see self::shouldNormalizeValidatedValue()
     */
    abstract protected function normalizeValidatedValueType(mixed $validatedValue): mixed;

    public function setValue(
        RecordValueContainerInterface $currentValueContainer,
        mixed $newValue,
        bool $isFromDb,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        if (!$isFromDb && $this->isReadonly()) {
            throw new TableColumnConfigException(
                "Column {$this->getNameForException()} is read only.",
                $this
            );
        }
        // Handle same value
        if (
            $currentValueContainer->hasValue()
            && $newValue === $currentValueContainer->getRawValue()
        ) {
            // Received same value as current one
            // Warning: if value in $currentValueContainer was already from DB
            // it should not change its status
            if ($isFromDb) {
                // Maybe value has changed its satatus to 'received from db'
                $currentValueContainer->setIsFromDb(true);
            }
            return $currentValueContainer;
        }
        // Normalize and validate value
        if ($isFromDb && $trustDataReceivedFromDb) {
            $normalizedValue = $newValue;
        } else {
            $normalizedValue = $this->normalizeValueForValidation($newValue, $isFromDb);
            if ($this->shouldUseDefaultValueInsteadOfNormalizedValue($currentValueContainer, $normalizedValue, $isFromDb)) {
                $normalizedValue = $this->getValidDefaultValue();
            } else {
                $errors = $this->validateNormalizedValue($normalizedValue, $isFromDb, false);
                if (!empty($errors)) {
                    throw new InvalidDataException(
                        [$this->getName() => $errors],
                        $currentValueContainer->getRecord(),
                        $this,
                        $newValue
                    );
                }
            }
        }
        if ($currentValueContainer->hasValue()) {
            // Create new value container.
            // Each container is allowed to set value only once.
            $valueContainer = $this->getNewRecordValueContainer(
                $currentValueContainer->getRecord()
            );
        } else {
            $valueContainer = $currentValueContainer;
        }
        $valueContainer->setValue(
            $newValue,
            $this->normalizeValidatedValue($normalizedValue),
            $isFromDb
        );
        return $valueContainer;
    }

    protected function assertValueContainerIsValid(
        RecordValueContainerInterface $valueContainer
    ): void {
        if ($valueContainer->getColumn() !== $this) {
            throw new \UnexpectedValueException(
                get_class($valueContainer) . ' class instance belongs to different column: '
                . $this->getNameForException($valueContainer->getColumn()) .
                '. Expected column: ' . $this->getNameForException()
            );
        }
    }

    /**
     * Check if default value should be used instead of $normalizedValue.
     * Ususally default value should be used when $normalizedValue === null
     * and Column configured with default value.
     * Also, it is reasonable to not use default value if $isFromDb === true
     * and when Record exists in DB to avoid unxepected overrides.
     */
    protected function shouldUseDefaultValueInsteadOfNormalizedValue(
        RecordValueContainerInterface $valueContainer,
        mixed $normalizedValue,
        bool $isFromDb
    ): bool {
        return (
            $normalizedValue === null
            && !$isFromDb
            && $this->canUseDefaultValue($valueContainer)
        );
    }

    protected function getRecordInfoForException(
        RecordValueContainerInterface $valueContainer
    ): string {
        $record = $valueContainer->getRecord();
        $pk = 'undefined';
        if (!$this->isPrimaryKey()) {
            try {
                $pk = $record->existsInDb()
                    ? $record->getPrimaryKeyValue()
                    : 'null';
            } catch (\Throwable) {
            }
        }
        return get_class($record) . '(#' . $pk . ')->' . $this->getName();
    }

    protected function canUseDefaultValue(
        RecordValueContainerInterface $valueContainer
    ): bool {
        return (
            $this->hasDefaultValue()
            && !$this->isPrimaryKey()
            && !$valueContainer->getRecord()->existsInDb()
        );
    }

    public function getNewRecordValueContainer(
        RecordInterface $record
    ): RecordValueContainerInterface {
        return new RecordValue($this, $record);
    }

    public function getValue(
        RecordValueContainerInterface $valueContainer,
        ?string $format
    ): mixed {
        if ($format) {
            return $this->getFormattedValue($valueContainer, $format);
        }

        if ($valueContainer->hasValue()) {
            return $valueContainer->getValue();
        }

        if ($this->canUseDefaultValue($valueContainer)) {
            return $this->getValidDefaultValue();
        }

        $columnInfo = $this->getRecordInfoForException($valueContainer);
        $defaultValueRestriction = $this->hasDefaultValue()
            ? 'is not provided.'
            : 'cannot be used.';
        throw new \BadMethodCallException(
            "Value for {$columnInfo} is not set and default value "
            . $defaultValueRestriction
        );
    }

    protected function getFormattedValue(
        RecordValueContainerInterface $valueContainer,
        string $format
    ): mixed {
        // todo: implement new way to define default formatters and use them
        return $valueContainer->getValue();
    }

    public function hasValue(
        RecordValueContainerInterface $valueContainer,
        bool $allowDefaultValue
    ): bool {
        if ($valueContainer->hasValue()) {
            return true;
        }
        if (
            $allowDefaultValue
            && $this->canUseDefaultValue($valueContainer)
        ) {
            return $valueContainer->getColumn()->hasDefaultValue();
        }
        return false;
    }

    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate,
    ): void {
        $valueContainer->pullPayload(static::AFTER_SAVE_PAYLOAD_KEY);
    }

    public function afterDelete(
        RecordValueContainerInterface $valueContainer,
        bool $shouldDeleteFiles
    ): void {
    }


}