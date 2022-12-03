<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesEn;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ValueToObjectConverterInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Utils\ArgumentValidators;

/**
 * Value setter workflow:
 * $this->valueSetter closure is called, and it calls
 * 1. $this->valuePreprocessor closure (result and original value saved to RecordValue object)
 * 2. $this->valueValidator closure (validation errors saved to RecordValue->setRawValue(...))
 * 2.1. $this->valueValidatorExtender closure (if DefaultColumnClosures::valueValidator() is used and value is still valid)
 * 3. (if value is valid) $this->valueNormalizer closure
 * Valid value saved to RecordValue->setValue(...)
 *
 * Value getter workflow:
 * $this->valueGetter closure is called, and it will possibly call $this->valueFormatter closure
 */
class TableColumn implements TableColumnInterface
{

    public const TYPE_INT = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOL = 'boolean';
    public const TYPE_STRING = 'string';
    public const TYPE_EMAIL = 'email';
    public const TYPE_TEXT = 'text';
    public const TYPE_JSON = 'json';
    public const TYPE_JSONB = 'jsonb';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_TIMESTAMP = 'timestamp';
    public const TYPE_TIMESTAMP_WITH_TZ = 'timestamp_tz';
    public const TYPE_UNIX_TIMESTAMP = 'unix_timestamp';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    public const TYPE_TIMEZONE_OFFSET = 'timezone_offset';
    public const TYPE_IPV4_ADDRESS = 'ip';
    public const TYPE_FILE = 'file';
    public const TYPE_IMAGE = 'image';
    public const TYPE_BLOB = 'blob';

    public const DEFAULT_VALUE_NOT_SET = '___NOT_SET___';
    public const VALID_DEFAULT_VALUE_UNDEFINED = '___UNDEFINED___';

    public const VALUE_CANNOT_BE_NULL = 'value_cannot_be_null';
    public const VALUE_MUST_BE_BOOLEAN = 'value_must_be_boolean';
    public const VALUE_MUST_BE_INTEGER = 'value_must_be_integer';
    public const VALUE_MUST_BE_FLOAT = 'value_must_be_float';
    public const VALUE_MUST_BE_IMAGE = 'value_must_be_image';
    public const VALUE_MUST_BE_FILE = 'value_must_be_file';
    public const VALUE_MUST_BE_JSON = 'value_must_be_json';
    public const VALUE_MUST_BE_IPV4_ADDRESS = 'value_must_be_ipv4_address';
    public const VALUE_MUST_BE_EMAIL = 'value_must_be_email';
    public const VALUE_MUST_BE_TIMEZONE_OFFSET = 'value_must_be_timezone_offset';
    public const VALUE_MUST_BE_TIMESTAMP = 'value_must_be_timestamp';
    public const VALUE_MUST_BE_TIMESTAMP_WITH_TZ = 'value_must_be_timestamp_with_tz';
    public const VALUE_MUST_BE_TIME = 'value_must_be_time';
    public const VALUE_MUST_BE_DATE = 'value_must_be_date';
    public const VALUE_MUST_BE_STRING = 'value_must_be_string';
    public const VALUE_MUST_BE_ARRAY = 'value_must_be_array';

    public const CASE_SENSITIVE = true;
    public const CASE_INSENSITIVE = false;

    protected ?string $name = null;
    protected string $type;
    protected ?TableStructureInterface $tableStructure = null;

    // params that can be set directly or calculated

    /**
     * @var RelationInterface[]
     */
    protected array $relations = [];

    protected bool $valueCanBeNull = true;
    protected bool $trimValue = false;
    protected bool $lowercaseValue = false;
    /**
     * null - autodetect;
     * true - accepts null values;
     * false - forbids null values;
     */
    protected ?bool $convertEmptyStringToNull = null;

    /**
     * @var mixed|\Closure
     */
    protected mixed $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    protected ?bool $hasDefaultValue = null;
    /**
     * @var mixed
     */
    protected mixed $validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;

    protected bool $isPrimaryKey = false;
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
     * Access to this column's value is only by its name. For example $user->password
     */
    protected bool $isPrivate = false;
    /**
     * Is this column exists in DB or not.
     * If not - column valueGetter() must be provided to return a value of this column
     * Record will not save columns that does not exist in DB
     */
    protected bool $existsInDb = true;
    /**
     * Allow/disallow value setting and modification
     * Record will not save columns that cannot be set or modified
     */
    protected bool $isValueCanBeSetOrChanged = true;
    /**
     * Then true - value contains a lot of data and should not be fetched by '*' selects
     */
    protected bool $isHeavy = false;

    private string $columnClosuresClass = DefaultColumnClosures::class;
    /**
     * Function to return default column value
     * By default returns: $this->defaultValue
     */
    protected ?\Closure $validDefaultValueGetter = null;
    /**
     * Function to return column value. Useful for virtual columns
     * By default: $defaultClosuresClass::valueGetter()
     */
    protected ?\Closure $valueGetter = null;
    /**
     * Function to check if column value is set
     * By default: $defaultClosuresClass::valueExistenceChecker()
     */
    protected ?\Closure $valueExistenceChecker = null;
    /**
     * Function to set new column value
     * By default: $defaultClosuresClass::valueSetter()
     */
    protected ?\Closure $valueSetter = null;
    /**
     * Function to preprocess value.
     * Default: $defaultClosuresClass::valuePreprocessor() that uses $column->convertEmptyValueToNull,
     *      $column->trimValue, $column->lowercaseValue params to make value more reliable for validation
     */
    protected ?\Closure $valuePreprocessor = null;
    /**
     * Function to normalize new validated column value
     * By default: $defaultClosuresClass->valueNormalizer()
     */
    protected ?\Closure $valueNormalizer = null;
    /**
     * Validates column value
     * By default: $defaultClosuresClass::valueValidator()
     */
    protected ?\Closure $valueValidator = null;
    /**
     * Extends default value validator.
     * Useful for additional validation like min/max length, min/max value, regex, etc
     */
    protected ?\Closure $valueValidatorExtender = null;
    /**
     * Saves value somewhere except DB. Used only with columns that are not present in DB
     * For example: saves files and images to file system
     */
    protected ?\Closure $valueSavingExtender = null;
    /**
     * Deletes value stored somewhere except DB. Used only with columns that are not present in DB
     * For example: deletes files and images from file system
     */
    protected ?\Closure $valueDeleteExtender = null;
    /**
     * Formats value. Used in default getter to add possibility to convert original value to specific format.
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     */
    protected ?\Closure $valueFormatter = null;
    /**
     * List of default value formatters for column type.
     * Used in default getter to add possibility to convert original value to specific format.
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     */
    protected ?array $valueFormattersForColumnType = null;
    /**
     * List of custom value formatters. Used in $this->valueFormatter to extend default list of formatters.
     */
    protected array $customValueFormatters = [];
    /**
     * Function that generates new value for a column for each save operation
     * Usage example: updated_at column
     */
    protected ?\Closure $valueAutoUpdater = null;

    // calculated params (not allowed to be set directly)
    protected bool $isFile = false;
    protected bool $isImage = false;
    /**
     * relation that stores values for this column
     */
    protected ?RelationInterface $foreignKeyRelation = null;

    protected ?string $classNameForValueToObjectFormatter = null;

    // service params
    public static array $fileTypes = [
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    ];

    public static array $imageFileTypes = [
        self::TYPE_IMAGE,
    ];

    protected ColumnValueValidationMessagesInterface $validationErrorsMessages;

    public static function create(string $type, ?string $name = null): static
    {
        return new static($name, $type);
    }

    public function __construct(?string $name, string $type)
    {
        if (!empty($name)) {
            $this->setName($name);
        }
        $this->setDataType($type);
        $this->setDefaultColumnClosures();
        $this->validationErrorsMessages = new ColumnValueValidationMessagesEn();
    }

    /**
     * @return \Closure[]
     */
    protected static function getDefaultColumnClosures(): array
    {
        static $defaultClosures = null;
        if ($defaultClosures === null) {
            $defaultClosures = [
                'valueGetter' => function (RecordValue $valueContainer, $format = null) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    return $class::valueGetter($valueContainer, $format);
                },
                'valueExistenceChecker' => function (RecordValue $valueContainer, $checkDefaultValue = false) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    return $class::valueExistenceChecker($valueContainer, $checkDefaultValue);
                },
                'valueSetter' => function (
                    $newValue,
                    $isFromDb,
                    RecordValue $valueContainer,
                    $trustDataReceivedFromDb
                ) {
                    $class = $valueContainer->getColumn()->getClosuresClass();
                    return $class::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
                },
                'valueValidator' => function ($value, $isFromDb, $isForCondition, TableColumnInterface $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueValidator($value, $isFromDb, $isForCondition, $column);
                },
                'valueValidatorExtender' => function (
                    $value,
                    $isFromDb,
                    $isForCondition,
                    TableColumnInterface $column
                ) {
                    $class = $column->getClosuresClass();
                    return $class::valueValidatorExtender($value, $isFromDb, $column);
                },
                'valueNormalizer' => function ($value, $isFromDb, TableColumnInterface $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueNormalizer($value, $isFromDb, $column);
                },
                'valuePreprocessor' => function ($newValue, $isFromDb, $isForValidation, TableColumnInterface $column) {
                    $class = $column->getClosuresClass();
                    return $class::valuePreprocessor($newValue, $isFromDb, $isForValidation, $column);
                },
                'valueSavingExtender' => function (RecordValue $valueContainer, $isUpdate) {
                    $class = $valueContainer->getColumn()->getClosuresClass();
                    $class::valueSavingExtender($valueContainer, $isUpdate);
                },
                'valueDeleteExtender' => function (RecordValue $valueContainer, $deleteFiles) {
                    $class = $valueContainer->getColumn()->getClosuresClass();
                    $class::valueDeleteExtender($valueContainer, $deleteFiles);
                },
                'valueFormatter' => function (RecordValue $valueContainer, $format) {
                    $class = $valueContainer->getColumn()->getClosuresClass();
                    return $class::valueFormatter($valueContainer, $format);
                },
            ];
        }
        return $defaultClosures;
    }

    protected function setDefaultColumnClosures(): static
    {
        $closures = static::getDefaultColumnClosures();
        if (!$this->valueGetter) {
            $this->setValueGetter($closures['valueGetter']);
        }
        if (!$this->valueExistenceChecker) {
            $this->setValueExistenceChecker($closures['valueExistenceChecker']);
        }
        if (!$this->valueSetter) {
            $this->setValueSetter($closures['valueSetter']);
        }
        if (!$this->valueValidator) {
            $this->setValueValidator($closures['valueValidator']);
        }
        if (!$this->valueValidatorExtender) {
            $this->setValueValidatorExtender($closures['valueValidatorExtender']);
        }
        if (!$this->valueNormalizer) {
            $this->setValueNormalizer($closures['valueNormalizer']);
        }
        if (!$this->valuePreprocessor) {
            $this->setValuePreprocessor($closures['valuePreprocessor']);
        }
        if (!$this->valueSavingExtender) {
            $this->setValueSavingExtender($closures['valueSavingExtender']);
        }
        if (!$this->valueDeleteExtender) {
            $this->setValueDeleteExtender($closures['valueDeleteExtender']);
        }
        if (!$this->valueFormatter) {
            $this->setValueFormatter($closures['valueFormatter']);
        }
        return $this;
    }

    /**
     * Set class that provides all behavioral closures for a column (class must implement ColumnClosuresInterface).
     * Class' closures will be used by default. But any closure may be overriden by calling TableColumn->set{ClosureName}(\Closure $fn).
     * In this case overrides will have priority over defaults.
     * @throws \InvalidArgumentException
     */
    public function setClosuresClass(string $className): static
    {
        if (
            !class_exists($className)
            || !(new \ReflectionClass($className))->implementsInterface(ColumnClosuresInterface::class)
        ) {
            throw new \InvalidArgumentException(
                '$className argument must be a string and contain a full name of a class that implements ColumnClosuresInterface'
            );
        }
        $this->columnClosuresClass = $className;
        return $this;
    }

    /**
     * @return string|ColumnClosuresInterface
     * @noinspection PhpDocSignatureInspection
     */
    public function getClosuresClass(): string
    {
        return $this->columnClosuresClass;
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
    public function setName(string $name): static
    {
        if ($this->hasName()) {
            throw new \BadMethodCallException('TableColumn name changing is forbidden');
        }
        ArgumentValidators::assertNotEmpty('$name', $name);
        ArgumentValidators::assertSnakeCase('$name', $name);

        $this->name = $name;
        return $this;
    }

    public function getDataType(): string
    {
        return $this->type;
    }

    protected function setDataType(string $type): static
    {
        $this->type = mb_strtolower($type);
        if (in_array($type, self::$fileTypes, true)) {
            $this->itIsFile();
            if (in_array($type, self::$imageFileTypes, true)) {
                $this->itIsImage();
            }
        }
        $this->valueFormattersForColumnType = null;
        return $this;
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

    public function shouldTrimValues(): bool
    {
        return $this->trimValue;
    }

    public function lowercasesValue(): static
    {
        $this->lowercaseValue = true;
        return $this;
    }

    public function shouldLowercaseValues(): bool
    {
        return $this->lowercaseValue;
    }

    public function getDefaultValue(): mixed
    {
        if (!$this->hasDefaultValue()) {
            throw new \BadMethodCallException(
                "Default value for column {$this->getColumnNameForException()} is not set."
            );
        }
        return $this->defaultValue;
    }

    protected function getColumnNameForException(): string
    {
        if ($this->hasTableStructure()) {
            return get_class($this->getTableStructure()) . '->' . $this->getName();
        }
        return static::class . "('{$this->getName()}')";
    }

    public function getValidDefaultValue(): mixed
    {
        if ($this->validDefaultValue === self::VALID_DEFAULT_VALUE_UNDEFINED) {
            if ($this->validDefaultValueGetter) {
                $defaultValue = call_user_func($this->validDefaultValueGetter, null, $this);
                $excPrefix = 'Default value received from validDefaultValueGetter Closure';
            } else {
                $defaultValue = $this->getDefaultValue();
                $excPrefix = 'Default value';
            }
            if ($defaultValue instanceof \Closure) {
                $defaultValue = $defaultValue();
            }
            $errors = $this->validateValue($defaultValue, false, false);
            if (!($defaultValue instanceof DbExpr)) {
                if (count($errors) > 0) {
                    throw new \UnexpectedValueException(
                        "{$excPrefix} for column {$this->getColumnNameForException()} is not valid. Errors: "
                        . implode(', ', $errors)
                    );
                }

                $defaultValue = call_user_func($this->getValueNormalizer(), $defaultValue, false, $this);
            }
            $this->validDefaultValue = $defaultValue;
        }
        return $this->validDefaultValue;
    }

    /**
     * @param \Closure $validDefaultValueGetter - function (mixed $fallbackValue, TableColumnInterface $column): mixed { return 'default'; }
     */
    public function setValidDefaultValueGetter(\Closure $validDefaultValueGetter): static
    {
        $this->validDefaultValueGetter = $validDefaultValueGetter;
        return $this;
    }

    public function hasDefaultValue(): bool
    {
        if ($this->hasDefaultValue === null) {
            return $this->defaultValue !== self::DEFAULT_VALUE_NOT_SET || $this->validDefaultValueGetter;
        }
        return $this->hasDefaultValue();
    }

    /**
     * @param mixed|\Closure $defaultValue - may be a \Closure: function() { return 'default value'; }
     */
    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        $this->validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;
        return $this;
    }

    public function shouldConvertEmptyStringToNull(): bool
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
        $this->existsInDb = false;
        return $this;
    }

    /**
     * Is this column exists in DB?
     */
    public function isReal(): bool
    {
        return $this->existsInDb;
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
                "Column {$this->getColumnNameForException()} is not linked with '{$relationName}' relation"
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
                'Conflict detected for column ' . $this->getColumnNameForException()
                . ": relation {$relation->getName()} pretends to be the source of values"
                . ' for this foreign key but there is already another relation for this: '
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

    public function isImage(): bool
    {
        return $this->isImage;
    }

    protected function itIsImage(): static
    {
        $this->isImage = true;
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

    public function getValidationErrorsMessages(): ColumnValueValidationMessagesInterface
    {
        return $this->validationErrorsMessages;
    }

    public function getValueSetter(): \Closure
    {
        return $this->valueSetter;
    }

    /**
     * Sets new value. Called after value validation
     * @param \Closure $valueSetter - function (mixed $newValue, bool $isFromDb, RecordValue $valueContainer, bool $trustDataReceivedFromDb): RecordValue { modify $valueContainer }
     */
    public function setValueSetter(\Closure $valueSetter): static
    {
        $this->valueSetter = $valueSetter;
        return $this;
    }

    public function getValuePreprocessor(): \Closure
    {
        return $this->valuePreprocessor;
    }

    /**
     * Function to preprocess raw value for validation and normalization
     * @param \Closure $newValuePreprocessor - function (mixed $value, bool $isFromDb, TableColumnInterface $column): mixed { return $value }
     */
    public function setValuePreprocessor(\Closure $newValuePreprocessor): static
    {
        $this->valuePreprocessor = $newValuePreprocessor;
        return $this;
    }

    public function getValueGetter(): \Closure
    {
        return $this->valueGetter;
    }

    /**
     * @param \Closure $valueGetter - function (RecordValue $value, ?string $format = null): mixed { return $value->getValue(); }
     * Note: do not forget to provide valueExistenceChecker in case of columns that do not exist in db
     */
    public function setValueGetter(\Closure $valueGetter): static
    {
        $this->valueGetter = $valueGetter;
        return $this;
    }

    /**
     * Get function that checks if column value is set
     */
    public function getValueExistenceChecker(): \Closure
    {
        return $this->valueExistenceChecker;
    }

    /**
     * Set function that checks if column value is set and returns boolean value (true: value is set)
     * Note: column value is set if it has any value (even null) or default value
     * @param \Closure $valueChecker - function (RecordValue $value, bool $checkDefaultValue = false): bool { return true; }
     */
    public function setValueExistenceChecker(\Closure $valueChecker): static
    {
        $this->valueExistenceChecker = $valueChecker;
        return $this;
    }

    public function getValueValidator(): \Closure
    {
        return $this->valueValidator;
    }

    /**
     * @param \Closure $validator - function (mixed|RecordValue $value, bool $isFromDb, bool $isForCondition, TableColumnInterface $column): array { return ['validation error 1', ...]; }
     * Notes:
     * - value is 'mixed' or a RecordValue instance. If value is 'mixed' - it should be preprocessed;
     * - if there are no errors - return empty array;
     * - default validator uses $this->getValueValidatorExtender().
     *   Make sure to use that additional validators if needed.
     */
    public function setValueValidator(\Closure $validator): static
    {
        $this->valueValidator = $validator;
        return $this;
    }

    /**
     * Note: it should not be used as public. Better use setValueValidator() method
     */
    public function getValueValidatorExtender(): \Closure
    {
        return $this->valueValidatorExtender;
    }

    /**
     * Additional validation called after
     * @param \Closure $extender - function (mixed $value, bool $isFromDb, bool $isForCondition, TableColumnInterface $column): array { return ['validation error 1', ...]; }
     * Notes:
     * - value has mixed type, not a RecordValue instance;
     * - if there are no errors - return empty array.
     */
    public function extendValueValidator(\Closure $extender): static
    {
        $this->valueValidatorExtender = $extender;
        return $this;
    }

    /**
     * Alias for TableColumn::extendValueValidator
     * @see TableColumn::extendValueValidator
     */
    public function setValueValidatorExtender(\Closure $validator): static
    {
        return $this->extendValueValidator($validator);
    }

    /**
     * Validates a new value
     * @param mixed|RecordValue $value
     * @param bool $isFromDb - true: value received from DB | false: value is update
     * @param bool $isForCondition - true: value is for condition (less strict) | false: value is for column
     * @throws \UnexpectedValueException
     */
    public function validateValue(mixed $value, bool $isFromDb = false, bool $isForCondition = false): array
    {
        $errors = call_user_func($this->getValueValidator(), $value, $isFromDb, $isForCondition, $this);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Validator closure must return an array');
        }
        return $errors;
    }

    public function getValueNormalizer(): \Closure
    {
        return $this->valueNormalizer;
    }

    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function (mixed $value, bool $isFromDb, TableColumnInterface $column): mixed { return 'normalized value'; }
     */
    public function setValueNormalizer(\Closure $normalizer): static
    {
        $this->valueNormalizer = $normalizer;
        return $this;
    }

    public function getValueSavingExtender(): \Closure
    {
        return $this->valueSavingExtender;
    }

    /**
     * Additional processing for a column's value during a save.
     * WARNING: to be triggered one of conditions is required to be true:
     *   a) column exists in DB and RecordValue was saved to DB
     *   b) column does not exist in DB and RecordValue has value
     * Called after record's values were saved to db and $this->updateValues($savedData, true) is called to
     * update RecordValue objects and mark them as "Received from DB"
     * Primary usage: storing files to file system or other storage
     * Closure arguments:
     * - RecordValue $valueContainer - contains column's value and some additional info stored before
     *       $record->saveToDb() was called. Data inside it was not modified by $this->updateValues($savedData, true)
     * - bool $isUpdate - true: $record->saveToDb() updated existing DB row | false: $record->saveToDb() inserted DB row
     * - array $savedData - data fetched from DB after saving
     * @param \Closure $valueSaver function (RecordValue $valueContainer, bool $isUpdate, array $savedData): void {  }
     */
    public function setValueSavingExtender(\Closure $valueSaver): static
    {
        $this->valueSavingExtender = $valueSaver;
        return $this;
    }

    public function getValueDeleteExtender(): \Closure
    {
        return $this->valueDeleteExtender;
    }

    /**
     * Designed to manage file-related TableColumn
     * Called after Record::afterDelete() and after transaction started inside Record::delete() was closed
     * but before Record's values is wiped.
     * Note: if transaction started outside of Record::delete() it won't be closed inside it.
     * Closure arguments:
     * - RecordValue $valueContainer
     * - bool $deleteFiles - true: files related to column's value should be deleted | false: leave files if any
     * @param \Closure $valueDeleteExtender function (RecordValue $valueContainer, bool $deleteFiles): void {  }
     */
    public function setValueDeleteExtender(\Closure $valueDeleteExtender): static
    {
        $this->valueDeleteExtender = $valueDeleteExtender;
        return $this;
    }

    public function getValueFormatter(): \Closure
    {
        return $this->valueFormatter;
    }

    /**
     * Function to transform original value into another format and return result. Used in value getter
     * @param \Closure $valueFormatter - function (RecordValue $valueContainer, string $format): mixed { return 'formatted value'; }
     */
    public function setValueFormatter(\Closure $valueFormatter): static
    {
        $this->valueFormatter = $valueFormatter;
        return $this;
    }

    public function getValueFormattersNames(): array
    {
        return array_merge(
            array_keys($this->getValueFormattersForColumnType()),
            array_keys($this->customValueFormatters)
        );
    }

    public function getValueFormattersForColumnType(): array
    {
        if ($this->valueFormattersForColumnType === null) {
            $this->valueFormattersForColumnType = ColumnValueFormatters::getFormattersForColumnType($this->getDataType());
        }
        return $this->valueFormattersForColumnType;
    }

    /**
     * @param string $name - name of formatter.
     * If column name is 'datetime' and formatter name is 'timestamp',
     * then you can use Record->datetime_as_timestamp to get formatted value
     * @param \Closure $formatter = function(RecordValue $valueContainer): mixed { return $modifiedValue }
     */
    public function addCustomValueFormatter(string $name, \Closure $formatter): static
    {
        $this->customValueFormatters[$name] = $formatter;
        return $this;
    }

    public function getCustomValueFormatters(): array
    {
        return $this->customValueFormatters;
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

    /**
     * Used in 'object' formatter for columns with JSON values and also can be used in custom formatters
     * @param string|null $className - string: custom class name | null: \stdClass
     * Custom class name must implement PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ValueToObjectConverterInterface or extend PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ValueToObjectConverter class
     * Note: you can use PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ConvertsArrayToObject trait for simple situations
     */
    public function setClassNameForValueToObjectFormatter(?string $className): static
    {
        if (
            $className
            && (
                !class_exists($className)
                || !(new \ReflectionClass($className))->implementsInterface(ValueToObjectConverterInterface::class)
            )
        ) {
            throw new \InvalidArgumentException(
                '$className argument must be a string and contain a full name of a class that implements ' . ValueToObjectConverterInterface::class
            );
        }
        $this->classNameForValueToObjectFormatter = $className;
        return $this;
    }

    /**
     * @return string|null|ValueToObjectConverterInterface
     * @noinspection PhpDocSignatureInspection
     */
    public function getObjectClassNameForValueToObjectFormatter(): ?string
    {
        return $this->classNameForValueToObjectFormatter;
    }

    public function getPossibleColumnNames(): array
    {
        $name = $this->getName();
        $ret = [
            $name,
        ];
        foreach ($this->getValueFormattersNames() as $formatterName) {
            $ret[] = $name . '_as_' . $formatterName;
        }
        return $ret;
    }

    public function setTableStructure(TableStructureInterface $tableStructure): static
    {
        $this->tableStructure = $tableStructure;
        return $this;
    }

    public function getTableStructure(): TableStructureInterface
    {
        return $this->tableStructure;
    }

    protected function hasTableStructure(): bool
    {
        return $this->tableStructure !== null;
    }

    public function getNewRecordValueContainer(
        RecordInterface $record
    ): RecordValueContainerInterface {
        return new RecordValue($this, $record);
    }

    public function setValue(
        RecordValueContainerInterface $currentValueContainer,
        mixed $newValue,
        bool $isFromDb,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        return call_user_func(
            $this->getValueSetter(),
            $newValue,
            $isFromDb,
            $currentValueContainer,
            $trustDataReceivedFromDb
        );
    }

    public function getValue(
        RecordValueContainerInterface $valueContainer,
        ?string $format
    ): mixed {
        return call_user_func(
            $this->getValueGetter(),
            $valueContainer,
            $format
        );
    }

    public function hasValue(
        RecordValueContainerInterface $valueContainer,
        bool $allowDefaultValue
    ): bool {
        return call_user_func(
            $this->getValueExistenceChecker(),
            $valueContainer,
            $allowDefaultValue
        );
    }

    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate,
    ): void {
        if ($valueContainer->hasValue()) {
            call_user_func(
                $this->getValueSavingExtender(),
                $valueContainer,
                $isUpdate,
            );
        }
    }

    public function afterDelete(
        RecordValueContainerInterface $valueContainer,
        bool $shouldDeleteFiles,
    ): void {
        call_user_func(
            $this->getValueDeleteExtender(),
            $valueContainer,
            $shouldDeleteFiles
        );
    }
}
