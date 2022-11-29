<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
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
    public const CASE_SENSITIVE = true;
    public const CASE_INSENSITIVE = false;

    protected ?string $name = null;
    protected ?TableStructureInterface $tableStructure = null;

    protected bool $valueCanBeNull = true;
    protected bool $trimValue = false;
    protected bool $lowercaseValue = false;
    /**
     * null - autodetect depending on $this->valueCanBeNull value;
     * true - accepts null values;
     * false - forbids null values;
     */
    protected ?bool $convertEmptyStringToNull = null;

    /**
     * @var mixed|\Closure
     */
    protected mixed $defaultValue = null;
    protected bool $hasDefaultValue = false;
    protected bool $isDefaultValueValidated = false;
    protected mixed $validDefaultValue = null;

    protected bool $isValueMustBeUnique = false;
    /**
     * Should value uniqueness be case-sensitive or not?
     */
    protected bool $isUniqueContraintCaseSensitive = true;
    /**
     * Other columns used in uniqueness constraint (multi-column uniqueness)
     */
    protected array $uniqueContraintAdditonalColumns = [];

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
     * Record will not save columns that cannot be set or modified
     */
    protected bool $isValueCanBeSetOrChanged = true;
    /**
     * Then true - value contains a lot of data and should not be fetched by '*' selects
     */
    protected bool $isHeavy = false;

    protected bool $isFile = false;

    protected bool $isPrimaryKey = false;
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

    protected function getColumnNameForException(): string
    {
        $tableStructure = $this->getTableStructure();
        if ($tableStructure) {
            return get_class($tableStructure) . '->' . $this->getName();
        }
        return static::class . "('{$this->getName()}')";
    }

    public function isNullableValues(): bool
    {
        return $this->valueCanBeNull;
    }

    public function allowsNullValues(): static
    {
        $this->valueCanBeNull = true;
        return $this;
    }

    public function disallowsNullValues(): static
    {
        $this->valueCanBeNull = false;
        return $this;
    }

    public function setIsNullableValue(bool $bool): static
    {
        $this->valueCanBeNull = $bool;
        return $this;
    }

    public function trimsValue(): static
    {
        $this->trimValue = true;
        return $this;
    }

    protected function shouldTrimValues(): bool
    {
        return $this->trimValue;
    }

    public function lowercasesValue(): static
    {
        $this->lowercaseValue = true;
        return $this;
    }

    protected function shouldLowercaseValues(): bool
    {
        return $this->lowercaseValue;
    }

    protected function shouldConvertEmptyStringToNull(): bool
    {
        return $this->convertEmptyStringToNull ?? $this->isNullableValues();
    }

    public function convertsEmptyStringToNull(): static
    {
        $this->convertEmptyStringToNull = true;
        return $this;
    }

    public function setConvertEmptyStringToNull(?bool $convert): static
    {
        $this->convertEmptyStringToNull = $convert;
        return $this;
    }

    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    public function primaryKey(): static
    {
        $this->isPrimaryKey = true;
        return $this;
    }

    public function isValueMustBeUnique(): bool
    {
        return $this->isValueMustBeUnique;
    }

    public function isUniqueContraintCaseSensitive(): bool
    {
        return $this->isUniqueContraintCaseSensitive;
    }

    public function getUniqueContraintAdditonalColumns(): array
    {
        return $this->uniqueContraintAdditonalColumns;
    }

    /**
     * Note: there is no automatic uniqueness validation in DefaultColumnClosures class!
     * @param bool $caseSensitive - true: compare values as is; false: compare lowercased values (emails for example);
     *      Note that case-insensitive mode uses more resources than case-sensitive!
     * @param array $withinColumns - used to provide list of columns for cases when uniqueness constraint in DB
     *      uses 2 or more columns.
     *      For example: when 'title' column must be unique within 'category' (category_id column)
     */
    public function uniqueValues(bool $caseSensitive = self::CASE_SENSITIVE, ...$withinColumns): static
    {
        $this->isValueMustBeUnique = true;
        $this->isUniqueContraintCaseSensitive = $caseSensitive;
        if (
            count($withinColumns) === 1
            && isset($withinColumns[0])
            && is_array($withinColumns[0])
        ) {
            $this->uniqueContraintAdditonalColumns = $withinColumns[0];
        } else {
            $this->uniqueContraintAdditonalColumns = $withinColumns;
        }
        return $this;
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

    public function valueCannotBeSetOrChanged(): static
    {
        $this->isValueCanBeSetOrChanged = false;
        return $this;
    }

    public function setIsValueCanBeSetOrChanged(bool $can): static
    {
        $this->isValueCanBeSetOrChanged = $can;
        return $this;
    }

    public function isReadonly(): bool
    {
        return !$this->isValueCanBeSetOrChanged;
    }

    /**
     * Value contains a lot of data and should not be fetched by '*' selects
     */
    public function valueIsHeavy(): static
    {
        $this->isHeavy = true;
        return $this;
    }

    public function isHeavyValues(): bool
    {
        return $this->isHeavy;
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
            throw new \InvalidArgumentException(
                'Conflict detected for column ' . $this->getName() . ': relation '
                . $relation->getName() . ' pretends to be the source of '
                . 'values for this foreign key but there is already another relation for this: '
                . $this->foreignKeyRelation->getName()
            );
        }
        $this->foreignKeyRelation = $relation;
        return $this;
    }

    public function isFile(): bool
    {
        return $this->isFile;
    }

    protected function itIsFile(): static
    {
        $this->isFile = true;
        return $this;
    }

    public function isPrivateValues(): bool
    {
        return $this->isPrivate;
    }

    /**
     * Value will not appear in Record->toArray() results and in iteration
     */
    public function privateValue(): static
    {
        $this->isPrivate = true;
        return $this;
    }

    /**
     * @param \Closure $valueGenerator - function (array|RecordInterface $record): mixed { return 'value' }
     */
    public function autoUpdateValueOnEachSaveWith(\Closure $valueGenerator): static
    {
        $this->valueAutoUpdater = $valueGenerator;
        return $this;
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function getAutoUpdateForAValue(RecordInterface|array $record): mixed
    {
        if (empty($this->valueAutoUpdater)) {
            throw new \UnexpectedValueException('Value auto updater function is not set');
        }
        return call_user_func($this->valueAutoUpdater, $record);
    }

    public function isAutoUpdatingValues(): bool
    {
        return !empty($this->valueAutoUpdater);
    }

    public function getPossibleColumnNames(): array
    {
        // todo: implement this
        $name = $this->getName();
        $ret = [
            $name,
        ];
//        foreach ($this->getValueFormattersNames() as $formatterName) {
//            $ret[] = $name . '_as_' . $formatterName;
//        }
        return $ret;
    }

    public function getDefaultValue(): mixed
    {
        if (!$this->hasDefaultValue()) {
            throw new \BadMethodCallException(
                "Default value for column '{$this->getColumnNameForException()}' is not set."
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
            throw new \UnexpectedValueException(
                "Default value for column {$this->getColumnNameForException()} is not valid. Errors: "
                . implode(', ', $errors)
            );
        }

        return $this->normalizeValidatedValue($defaultValue);
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    /**
     * @param mixed|\Closure $defaultValue - may be a \Closure: function() { return 'default value'; }
     */
    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        $this->hasDefaultValue = true;
        $this->isDefaultValueValidated = false;
        return $this;
    }

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
        return $this->shouldValidateValue($value);
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
        if (is_string($value)) {
            return $this->normalizeStringValue($value, $isFromDb);
        }
        return $value;
    }

    /**
     * Apply string value normalizations based on Column options like
     * shouldTrimValues(), shouldConvertEmptyStringToNull(), shouldLowercaseValues()
     */
    protected function normalizeStringValue(string $value, bool $isFromDb): ?string
    {
        if ($isFromDb) {
            // do not modify DB value to avoid unintended changes
            return $value;
        }
        if ($this->shouldTrimValues()) {
            $value = trim($value);
        }
        if ($value === '' && $this->shouldConvertEmptyStringToNull()) {
            return null;
        }
        if ($this->shouldLowercaseValues()) {
            $value = mb_strtolower($value);
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

        return $this->normalizeValueType($validatedValue);
    }

    /**
     * Normalize value according to column type.
     * Value cannot be null, DbExpr or SelectQueryBuilderInterface.
     * @see self::normalizeValidatedValue()
     * @see self::shouldNormalizeValidatedValue()
     */
    protected function normalizeValueType(mixed $validatedValue): mixed
    {
        return $validatedValue;
    }

    public function setValue(
        RecordInterface $record,
        mixed $newValue,
        bool $isFromDb
    ): RecordValueContainerInterface {
        // todo: review this
        $normalizedValue = $this->normalizeValueForValidation($newValue, $isFromDb);
        $errors = $this->validateNormalizedValue($normalizedValue, $isFromDb, false);
        if (!empty($errors)) {
            throw new InvalidDataException(
                [$this->getName() => $errors],
                $record,
                $this,
                $newValue
            );
        }
        $valueContainer = $this->getRecordValueContainer($record);
        $valueContainer->setValue(
            $newValue,
            $this->normalizeValidatedValue($normalizedValue),
            $isFromDb
        );
        return $valueContainer;
    }

    public function getRecordValueContainer(
        RecordInterface $record
    ): RecordValueContainerInterface {
        return new RecordValue($this, $record);
    }
}