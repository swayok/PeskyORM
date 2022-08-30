<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;

/**
 * Value setter workflow:
 * $this->valueSetter closure is called and it calls
 * 1. $this->valuePreprocessor closure (result and original value saved to RecordValue object)
 * 2. $this->valueValidator closure (validation errors saved to RecordValue->setRawValue(...))
 * 2.1. $this->valueValidatorExtender closure (if DefaultColumnClosures::valueValidator() is used and value is still valid)
 * 3. (if value is valid) $this->valueNormalizer closure
 * Valid value saved to RecordValue->setValidValue(....)
 *
 * Value getter workflow:
 * $this->valueGetter closure is called and it will possibly call $this->valueFormatter closure
 */
class Column
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
    public const TYPE_ENUM = 'enum';
    public const TYPE_IPV4_ADDRESS = 'ip';
    public const TYPE_FILE = 'file';
    public const TYPE_IMAGE = 'image';
    public const TYPE_BLOB = 'blob';
    
    public const NAME_VALIDATION_REGEXP = '%^[a-z][a-z0-9_]*$%';    //< snake_case
    
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
    public const VALUE_IS_NOT_ALLOWED = 'value_is_not_allowed';
    public const ONE_OF_VALUES_IS_NOT_ALLOWED = 'one_of_values_is_not_allowed';
    public const VALUE_MUST_BE_STRING = 'value_must_be_string';
    public const VALUE_MUST_BE_STRING_OR_NUMERIC = 'value_must_be_string_or_numeric';
    public const VALUE_MUST_BE_ARRAY = 'value_must_be_array';
    
    public const CASE_SENSITIVE = true;
    public const CASE_INSENSITIVE = false;
    
    protected static array $defaultValidationErrorsMessages = [
        self::VALUE_CANNOT_BE_NULL => 'Null value is not allowed.',
        self::VALUE_MUST_BE_BOOLEAN => 'Value must be of a boolean data type.',
        self::VALUE_MUST_BE_INTEGER => 'Value must be of an integer data type.',
        self::VALUE_MUST_BE_FLOAT => 'Value must be of a numeric data type.',
        self::VALUE_MUST_BE_IMAGE => 'Value must be an uploaded image info.',
        self::VALUE_MUST_BE_FILE => 'Value must be an uploaded file info.',
        self::VALUE_MUST_BE_JSON => 'Value must be a json-encoded string or array.',
        self::VALUE_MUST_BE_IPV4_ADDRESS => 'Value must be an IPv4 address.',
        self::VALUE_MUST_BE_EMAIL => 'Value must be an email.',
        self::VALUE_MUST_BE_TIMEZONE_OFFSET => 'Value must be a valid timezone offset.',
        self::VALUE_MUST_BE_TIMESTAMP => 'Value must be a valid timestamp.',
        self::VALUE_MUST_BE_TIMESTAMP_WITH_TZ => 'Value must be a valid timestamp with time zone.',
        self::VALUE_MUST_BE_TIME => 'Value must be a valid time.',
        self::VALUE_MUST_BE_DATE => 'Value must be a valid date.',
        self::VALUE_IS_NOT_ALLOWED => 'Value is not allowed: :value.',
        self::ONE_OF_VALUES_IS_NOT_ALLOWED => 'One of values in the received array is not allowed.',
        self::VALUE_MUST_BE_STRING => 'Value must be a string.',
        self::VALUE_MUST_BE_STRING_OR_NUMERIC => 'Value must be a string or a number.',
        self::VALUE_MUST_BE_ARRAY => 'Value must be an array.',
    ];
    
    protected static array $validationErrorsMessages = [];
    
    // params that can be set directly or calculated
    
    protected ?TableStructureInterface $tableStructure = null;
    /**
     * @var Relation[]|null
     */
    protected ?array $relations = null;
    protected ?string $name = null;
    protected string $type;
    
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
     * @var array|\Closure
     */
    protected $allowedValues = [];
    
    /**
     * @var mixed|\Closure
     */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    protected ?bool $hasDefaultValue = null;
    /** @var mixed */
    protected $validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;
    
    protected bool $isPrimaryKey = false;
    protected bool $isValueMustBeUnique = false;
    /**
     * Should value uniqueness be case sensitive or not?
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
     * Validates if column value is within $this->allowedValues (if any)
     * By default: $defaultClosuresClass::valueIsAllowedValidator()
     */
    protected ?\Closure $valueIsAllowedValidator = null;
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
     * null value means "needs detection"
     */
    protected ?bool $isForeignKey = null;
    /**
     * relation that stores values for this column
     */
    protected ?Relation $foreignKeyRelation = null;
    
    protected ?string $classNameForValueToObjectFormatter = null;
    
    // service params
    public static array $fileTypes = [
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    ];
    
    public static array $imageFileTypes = [
        self::TYPE_IMAGE,
    ];
    
    /**
     * @return static
     */
    public static function create(string $type, ?string $name = null)
    {
        return new static($name, $type);
    }
    
    public function __construct(?string $name, string $type)
    {
        if (!empty($name)) {
            $this->setName($name);
        }
        $this->setType($type);
        $this->setDefaultColumnClosures();
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
                'valueSetter' => function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    return $class::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
                },
                'valueValidator' => function ($value, $isFromDb, $isForCondition, Column $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueValidator($value, $isFromDb, $isForCondition, $column);
                },
                'valueIsAllowedValidator' => function ($value, $isFromDb, $isForCondition, Column $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueIsAllowedValidator($value, $isFromDb, $column);
                },
                'valueValidatorExtender' => function ($value, $isFromDb, $isForCondition, Column $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueValidatorExtender($value, $isFromDb, $column);
                },
                'valueNormalizer' => function ($value, $isFromDb, Column $column) {
                    $class = $column->getClosuresClass();
                    return $class::valueNormalizer($value, $isFromDb, $column);
                },
                'valuePreprocessor' => function ($newValue, $isFromDb, $isForValidation, Column $column) {
                    $class = $column->getClosuresClass();
                    return $class::valuePreprocessor($newValue, $isFromDb, $isForValidation, $column);
                },
                'valueSavingExtender' => function (RecordValue $valueContainer, $isUpdate, array $savedData) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    $class::valueSavingExtender($valueContainer, $isUpdate, $savedData);
                },
                'valueDeleteExtender' => function (RecordValue $valueContainer, $deleteFiles) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    $class::valueDeleteExtender($valueContainer, $deleteFiles);
                },
                'valueFormatter' => function (RecordValue $valueContainer, $format) {
                    $class = $valueContainer->getColumn()
                        ->getClosuresClass();
                    return $class::valueFormatter($valueContainer, $format);
                },
            ];
        }
        return $defaultClosures;
    }
    
    /**
     * @return static
     */
    protected function setDefaultColumnClosures()
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
        if (!$this->valueIsAllowedValidator) {
            $this->setValueIsAllowedValidator($closures['valueIsAllowedValidator']);
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
    
    public function getTableStructure(): TableStructureInterface
    {
        return $this->tableStructure;
    }
    
    /**
     * @return static
     */
    public function setTableStructure(TableStructureInterface $tableStructure)
    {
        $this->tableStructure = $tableStructure;
        return $this;
    }
    
    /**
     * Class that provides all closures for a column.
     * Note: if some closure is defined via Column->setClosureName(\Closure $fn) then $fn will be used istead of
     * same closure provided by class
     * @param string $className - class that implements ColumnClosuresInterface
     * @return static
     * @throws \InvalidArgumentException
     */
    public function setClosuresClass(string $className)
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
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName(string $name)
    {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Column name alteration is forbidden');
        }
        if (!preg_match(static::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: " . static::NAME_VALIDATION_REGEXP . '. Example: snake_case1'
            );
        }
        $this->name = $name;
        return $this;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function isEnum(): bool
    {
        return $this->getType() === static::TYPE_ENUM;
    }
    
    /**
     * @return static
     */
    protected function setType(string $type)
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
    
    public function isValueCanBeNull(): bool
    {
        return $this->valueCanBeNull;
    }
    
    /**
     * @return static
     */
    public function allowsNullValues()
    {
        $this->valueCanBeNull = true;
        return $this;
    }
    
    /**
     * @return static
     */
    public function disallowsNullValues()
    {
        $this->valueCanBeNull = false;
        return $this;
    }
    
    /**
     * @return static
     */
    public function setIsNullableValue(bool $bool)
    {
        $this->valueCanBeNull = $bool;
        return $this;
    }
    
    /**
     * Computed value that indicates if value must be not empty
     */
    public function isValueRequiredToBeNotEmpty(): bool
    {
        return (
            !$this->isValueCanBeNull()
            && (
                $this->isEmptyStringMustBeConvertedToNull()
                || !$this->hasDefaultValue()
                || !empty($this->getValidDefaultValue(''))
            )
        );
    }
    
    /**
     * @return static
     */
    public function trimsValue()
    {
        $this->trimValue = true;
        return $this;
    }
    
    public function isValueTrimmingRequired(): bool
    {
        return $this->trimValue;
    }
    
    /**
     * @return static
     */
    public function lowercasesValue()
    {
        $this->lowercaseValue = true;
        return $this;
    }
    
    public function isValueLowercasingRequired(): bool
    {
        return $this->lowercaseValue;
    }
    
    /**
     * Get default value set via $this->setDefaultValue()
     * @return mixed - may be a \Closure: function() { return 'default value'; }
     * @throws \BadMethodCallException
     */
    public function getDefaultValueAsIs()
    {
        if (!$this->hasDefaultValue()) {
            throw new \BadMethodCallException("Default value for column '{$this->getName()}' is not set");
        }
        return $this->defaultValue;
    }
    
    /**
     * Get validated default value
     * @param mixed|\Closure $fallbackValue - value to be returned when default value was not configured (may be a \Closure)
     * @return mixed - validated default value or $fallbackValue or return from $this->validDefaultValueGetter
     * @throws \UnexpectedValueException
     */
    public function getValidDefaultValue($fallbackValue = null)
    {
        if ($this->validDefaultValue === self::VALID_DEFAULT_VALUE_UNDEFINED) {
            $rememberValidDefaultValue = true;
            if ($this->validDefaultValueGetter) {
                $defaultValue = call_user_func($this->validDefaultValueGetter, $fallbackValue, $this);
                $excPrefix = 'Default value received from validDefaultValueGetter Closure';
            } elseif ($this->hasDefaultValue()) {
                $defaultValue = $this->defaultValue;
                $excPrefix = 'Default value';
            } else {
                $rememberValidDefaultValue = false;
                $defaultValue = $fallbackValue;
                $excPrefix = 'Fallback value of the default value';
            }
            if ($defaultValue instanceof \Closure) {
                $defaultValue = $defaultValue();
            }
            $errors = $this->validateValue($defaultValue, false, false);
            if (!($defaultValue instanceof DbExpr)) {
                if (count($errors) > 0) {
                    $tableStructureClass = get_class($this->getTableStructure());
                    throw new \UnexpectedValueException(
                        "{$excPrefix} for column {$tableStructureClass}->{$this->getName()} is not valid. Errors: " . implode(', ', $errors)
                    );
                } else {
                    $defaultValue = call_user_func($this->getValueNormalizer(), $defaultValue, false, $this);
                }
            }
            if ($rememberValidDefaultValue) {
                $this->validDefaultValue = $defaultValue;
            } else {
                return $defaultValue;
            }
        }
        return $this->validDefaultValue;
    }
    
    /**
     * @param \Closure $validDefaultValueGetter - function ($fallbackValue, Column $column) { return 'default'; }
     * @return static
     */
    public function setValidDefaultValueGetter(\Closure $validDefaultValueGetter)
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
     * @param mixed $defaultValue - may be a \Closure: function() { return 'default value'; }
     * @return static
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        $this->validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;
        return $this;
    }
    
    public function isEmptyStringMustBeConvertedToNull(): bool
    {
        return $this->convertEmptyStringToNull ?? $this->isValueCanBeNull();
    }
    
    /**
     * @return static
     */
    public function convertsEmptyStringToNull()
    {
        $this->convertEmptyStringToNull = true;
        return $this;
    }
    
    /**
     * @return static
     */
    public function setConvertEmptyStringToNull(?bool $convert)
    {
        $this->convertEmptyStringToNull = $convert;
        return $this;
    }
    
    /**
     * @throws \UnexpectedValueException
     */
    public function getAllowedValues(): array
    {
        if ($this->allowedValues instanceof \Closure) {
            $allowedValues = call_user_func($this->allowedValues);
            if (!is_array($allowedValues) || empty($allowedValues)) {
                throw new \UnexpectedValueException('Allowed values closure must return a not-empty array');
            }
            $this->allowedValues = $allowedValues;
        }
        return $this->allowedValues;
    }
    
    /**
     * @param array|\Closure $allowedValues - \Closure: function () { return [] }
     * @return static
     * @throws \InvalidArgumentException
     */
    public function setAllowedValues($allowedValues)
    {
        if (!($allowedValues instanceof \Closure) && (!is_array($allowedValues) || empty($allowedValues))) {
            throw new \InvalidArgumentException('$allowedValues argument cannot be empty');
        }
        $this->allowedValues = $allowedValues;
        return $this;
    }
    
    public function isItPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }
    
    /**
     * @return static
     */
    public function primaryKey()
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
     *      Note that case insensitive mode uses more resources than case sensitive!
     * @param array $withinColumns - used to provide list of columns for cases when uniqueness constraint in DB
     *      uses 2 or more columns.
     *      For example: when 'title' column must be unique within 'category' (category_id column)
     * @return static
     */
    public function uniqueValues(bool $caseSensitive = self::CASE_SENSITIVE, ...$withinColumns)
    {
        $this->isValueMustBeUnique = true;
        $this->isUniqueContraintCaseSensitive = $caseSensitive;
        $this->uniqueContraintAdditonalColumns = count($withinColumns) === 1 && isset($withinColumns[0]) && is_array($withinColumns[0])
            ? $withinColumns[0]
            : $withinColumns;
        return $this;
    }
    
    /**
     * @return static
     */
    public function doesNotExistInDb()
    {
        $this->existsInDb = false;
        return $this;
    }
    
    /**
     * Is this column exists in DB?
     */
    public function isItExistsInDb(): bool
    {
        return $this->existsInDb;
    }
    
    /**
     * @return static
     */
    public function valueCannotBeSetOrChanged()
    {
        $this->isValueCanBeSetOrChanged = false;
        return $this;
    }
    
    /**
     * @return static
     */
    public function setIsValueCanBeSetOrChanged(bool $can)
    {
        $this->isValueCanBeSetOrChanged = $can;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isValueCanBeSetOrChanged(): bool
    {
        return $this->isValueCanBeSetOrChanged;
    }
    
    /**
     * Value contains a lot of data and should not be fetched by '*' selects
     * @return static
     */
    public function valueIsHeavy()
    {
        $this->isHeavy = true;
        return $this;
    }
    
    public function isValueHeavy(): bool
    {
        return $this->isHeavy;
    }
    
    /**
     * @return Relation[]
     */
    public function getRelations(): array
    {
        if ($this->relations === null) {
            $this->relations = $this->getTableStructure()
                ->getColumnRelations($this->getName());
        }
        return $this->relations;
    }
    
    public function hasRelation(string $relationName): bool
    {
        return isset($this->getRelations()[$relationName]);
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function getRelation(string $relationName): Relation
    {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException(
                "Column '{$this->getName()}' is not linked with '{$relationName}' relation"
            );
        }
        return $this->relations[$relationName];
    }
    
    public function getForeignKeyRelation(): ?Relation
    {
        if ($this->isForeignKey === null) {
            $this->foreignKeyRelation = null;
            $this->isForeignKey = false;
            foreach ($this->getRelations() as $relation) {
                if ($relation->getType() === Relation::BELONGS_TO) {
                    $this->itIsForeignKey($relation);
                    // don't break here - let it validate if there are no multiple foreign keys here
                }
            }
        }
        return $this->foreignKeyRelation;
    }
    
    public function isItAForeignKey(): bool
    {
        return $this->getForeignKeyRelation() !== null; //< lazy load!
    }
    
    /**
     * @param Relation $relation - relation that stores values for this column
     * @return static
     * @throws \InvalidArgumentException
     */
    protected function itIsForeignKey(Relation $relation)
    {
        if ($this->foreignKeyRelation) {
            throw new \InvalidArgumentException(
                'Conflict detected for column ' . $this->getName() . ': relation ' . $relation->getName() . ' pretends to be the source of '
                . 'values for this foreign key but there is already another relation for this: '
                . $this->foreignKeyRelation->getName()
            );
        }
        $this->foreignKeyRelation = $relation;
        $this->isForeignKey = true;
        return $this;
    }
    
    public function isItAFile(): bool
    {
        return $this->isFile;
    }
    
    /**
     * @return static
     */
    protected function itIsFile()
    {
        $this->isFile = true;
        return $this;
    }
    
    public function isItAnImage(): bool
    {
        return $this->isImage;
    }
    
    /**
     * @return static
     */
    protected function itIsImage()
    {
        $this->isImage = true;
        return $this;
    }
    
    public function isValuePrivate(): bool
    {
        return $this->isPrivate;
    }
    
    /**
     * Value will not appear in Record->toArray() results and in iteration
     * @return static
     */
    public function privateValue()
    {
        $this->isPrivate = true;
        return $this;
    }
    
    public static function getValidationErrorsMessages(): array
    {
        return static::$validationErrorsMessages ?: static::$defaultValidationErrorsMessages;
    }
    
    /**
     * Provide custom validation errors messages.
     * Default errors are listed in static::$defaultValidationErrorsLocalization
     */
    public static function setValidationErrorsMessages(array $validationErrorsMessages): void
    {
        if (!empty($validationErrorsMessages)) {
            static::$validationErrorsMessages = array_merge(
                static::$defaultValidationErrorsMessages,
                $validationErrorsMessages
            );
        }
    }
    
    public function getValueSetter(): \Closure
    {
        return $this->valueSetter;
    }
    
    /**
     * Sets new value. Called after value validation
     * @param \Closure $valueSetter = function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) { modify $valueContainer }
     * @return static
     */
    public function setValueSetter(\Closure $valueSetter)
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
     * @param \Closure $newValuePreprocessor = function ($value, $isFromDb, Column $column) { return $value }
     * @return static
     */
    public function setValuePreprocessor(\Closure $newValuePreprocessor)
    {
        $this->valuePreprocessor = $newValuePreprocessor;
        return $this;
    }
    
    public function getValueGetter(): \Closure
    {
        return $this->valueGetter;
    }
    
    /**
     * @param \Closure $valueGetter = function (RecordValue $value, $format = null) { return $value->getValue(); }
     * Note: do not forget to provide valueExistenceChecker in case of columns that do not exist in db
     * @return static
     */
    public function setValueGetter(\Closure $valueGetter)
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
     * @param \Closure $valueChecker = function (RecordValue $value, $checkDefaultValue = false) { return true; }
     * @return static
     */
    public function setValueExistenceChecker(\Closure $valueChecker)
    {
        $this->valueExistenceChecker = $valueChecker;
        return $this;
    }
    
    public function getValueValidator(): \Closure
    {
        return $this->valueValidator;
    }
    
    /**
     * @param \Closure $validator = function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - value is mixed or a RecordValue instance. If value is mixed - it should be preprocessed
     * - defalut validator uses $this->getValueIsAllowedValidator() and  $this->getValueValidatorExtender(). Make sure
     * to use that additional validators if needed
     * @return static
     */
    public function setValueValidator(\Closure $validator)
    {
        $this->valueValidator = $validator;
        return $this;
    }
    
    public function getValueIsAllowedValidator(): \Closure
    {
        return $this->valueIsAllowedValidator;
    }
    
    /**
     * @param \Closure $validator = function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - If you do not use custom value validator - you'll need to call this one manually (todo: what is this ???)
     * @return static
     */
    public function setValueIsAllowedValidator(\Closure $validator)
    {
        $this->valueIsAllowedValidator = $validator;
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
     * @param \Closure $extender - function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - Value has mixed type, not a RecordValue instance;
     * - If there is no errors - return empty array
     * @return static
     */
    public function extendValueValidator(\Closure $extender)
    {
        $this->valueValidatorExtender = $extender;
        return $this;
    }
    
    /**
     * Alias for Column::extendValueValidator
     * @param \Closure $validator - function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * @return static
     * @see Column::extendValueValidator
     */
    public function setValueValidatorExtender(\Closure $validator)
    {
        return $this->extendValueValidator($validator);
    }
    
    /**
     * Validates a new value
     * @param mixed|RecordValue $value
     * @param bool $isFromDb - true: value received from DB
     *  - true: value is normalzed (trim, strolower, etc)
     *  - false: value is not normalzed (trim, strolower, etc)
     *  - null: value is normalized if $isFromDb === true
     * @param bool $isForCondition - true: value is for condition (less strict) | false: value is for column
     * @return array
     * @throws \UnexpectedValueException
     */
    public function validateValue($value, bool $isFromDb = false, bool $isForCondition = false): array
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
     * @param \Closure $normalizer - function ($value, $isFromDb, Column $column) { return 'normalized value'; }
     * @return static
     */
    public function setValueNormalizer(\Closure $normalizer)
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
     * @param \Closure $valueSaver function (RecordValue $valueContainer, $isUpdate, array $savedData) {  }
     * @return static
     */
    public function setValueSavingExtender(\Closure $valueSaver)
    {
        $this->valueSavingExtender = $valueSaver;
        return $this;
    }
    
    public function getValueDeleteExtender(): \Closure
    {
        return $this->valueDeleteExtender;
    }
    
    /**
     * Designed to manage file-related Column
     * Called after Record::afterDelete() and after transaction started inside Record::delete() was closed
     * but before Record's values is wiped.
     * Note: if transaction started outside of Record::delete() it won't be closed inside it.
     * Closure arguments:
     * - RecordValue $valueContainer
     * - bool $deleteFiles - true: files related to column's value should be deleted | false: leave files if any
     * @param \Closure $valueDeleteExtender function (RecordValue $valueContainer, $deleteFiles) {  }
     * @return static
     */
    public function setValueDeleteExtender(\Closure $valueDeleteExtender)
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
     * @param \Closure $valueFormatter - function (RecordValue $valueContainer, $format) { return 'formatted value'; }
     * @return static
     */
    public function setValueFormatter(\Closure $valueFormatter)
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
            $this->valueFormattersForColumnType = RecordValueFormatters::getFormattersForColumnType($this->getType());
        }
        return $this->valueFormattersForColumnType;
    }
    
    /**
     * @param string $name
     * @param \Closure $formatter = function(RecordValue $valueContainer) { return $modifiedValue }
     * @return static
     */
    public function addCustomValueFormatter(string $name, \Closure $formatter)
    {
        $this->customValueFormatters[$name] = $formatter;
        return $this;
    }
    
    public function getCustomValueFormatters(): array
    {
        return $this->customValueFormatters;
    }
    
    /**
     * @param \Closure $valueGenerator = function (array|RecordInterface $record) { return 'value' }
     * @return static
     */
    public function autoUpdateValueOnEachSaveWith(\Closure $valueGenerator)
    {
        $this->valueAutoUpdater = $valueGenerator;
        return $this;
    }
    
    /**
     * @param RecordInterface|array $record
     * @return mixed
     * @throws \UnexpectedValueException
     */
    public function getAutoUpdateForAValue($record)
    {
        if (empty($this->valueAutoUpdater)) {
            throw new \UnexpectedValueException('Value auto updater function is not set');
        }
        return call_user_func($this->valueAutoUpdater, $record);
    }
    
    public function isAutoUpdatingValue(): bool
    {
        return !empty($this->valueAutoUpdater);
    }
    
    /**
     * Used in 'object' formatter for columns with JSON values and also can be used in custom formatters
     * @param string|null $className - must implement PeskyORM\ORM\ValueToObjectConverterInterface or extend PeskyORM\ORM\ValueToObjectConverter class
     * Note: you can use PeskyORM\ORM\Traits\ConvertsArrayToObject trait for simple situations
     * @return static
     */
    public function setClassNameForValueToObjectFormatter(?string $className)
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
    
}
