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
    
    /**
     * @var array
     */
    static protected $defaultValidationErrorsMessages = [
        self::VALUE_CANNOT_BE_NULL => 'Null value is not allowed.',
        self::VALUE_MUST_BE_BOOLEAN => 'Value must be of a boolean data type.',
        self::VALUE_MUST_BE_INTEGER => 'Value must be of an integer data type.',
        self::VALUE_MUST_BE_FLOAT => 'Value must be of a numeric data type.',
        self::VALUE_MUST_BE_IMAGE => 'Value must be an uploaded image info.',
        self::VALUE_MUST_BE_FILE => 'Value must be an uploaded file info.',
        self::VALUE_MUST_BE_JSON => 'Value must be of a json data type.',
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
    
    /**
     * @var array
     */
    static protected $validationErrorsMessages = [];
    /**
     * @var null|array
     */
    static protected $defaultClosures;
    
    // params that can be set directly or calculated
    /**
     * @var TableStructure
     */
    protected $tableStructure;
    /**
     * @var Relation[]
     */
    protected $relations = null;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $type;
    /**
     * @var bool
     */
    protected $valueCanBeNull = true;
    /**
     * @var bool
     */
    protected $trimValue = false;
    /**
     * @var bool
     */
    protected $lowercaseValue = false;
    /**
     * @var bool|null - null means autodetect (true if column accepts null values; false - otherwise)
     */
    protected $convertEmptyStringToNull = null;
    /**
     * @var array|\Closure
     */
    protected $allowedValues = [];
    /**
     * @var mixed - can be a \Closure
     */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    /**
     * @var null|bool
     */
    protected $hasDefaultValue = null;
    /**
     * @var string
     */
    protected $validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;
    /**
     * @var bool
     */
    protected $isPrimaryKey = false;
    /**
     * @var bool
     */
    protected $isValueMustBeUnique = false;
    /**
     * Should value uniqueness be case sensitive or not?
     * @var bool
     */
    protected $isUniqueContraintCaseSensitive = true;
    /**
     * Other columns used in uniqueness constraint (multi-column uniqueness)
     * @var array
     */
    protected $uniqueContraintAdditonalColumns = [];
    /**
     * This column is private for the object and will be excluded from iteration, toArray(), etc.
     * Access to this column's value is only by its name. For example $user->password
     * @var bool
     */
    protected $isPrivate = false;
    /**
     * Is this column exists in DB or not.
     * If not - column valueGetter() must be provided to return a value of this column
     * Record will not save columns that does not exist in DB
     * @var bool
     */
    protected $existsInDb = true;
    /**
     * Allow/disallow value setting and modification
     * Record will not save columns that cannot be set or modified
     * @var int
     */
    protected $isValueCanBeSetOrChanged = true;
    /**
     * Then true - value contains a lot of data and should not be fetched by '*' selects
     * @var bool
     */
    protected $isHeavy = false;
    /**
     * @var string
     */
    protected $defaultClosuresClass = DefaultColumnClosures::class;
    /**
     * Function to return default column value
     * By default returns: $this->defaultValue
     * @var \Closure
     */
    protected $validDefaultValueGetter = null;
    /**
     * Function to return column value. Useful for virtual columns
     * By default: $defaultClosuresClass::valueGetter()
     * @var null|\Closure
     */
    protected $valueGetter = null;
    /**
     * Function to check if column value is set
     * By default: $defaultClosuresClass::valueExistenceChecker()
     * @var null|\Closure
     */
    protected $valueExistenceChecker = null;
    /**
     * Function to set new column value
     * By default: $defaultClosuresClass::valueSetter()
     * @var null|\Closure
     */
    protected $valueSetter = null;
    /**
     * Function to preprocess value.
     * Default: $defaultClosuresClass::valuePreprocessor() that uses $column->convertEmptyValueToNull,
     *      $column->trimValue, $column->lowercaseValue params to make value more reliable for validation
     * @var null|\Closure
     */
    protected $valuePreprocessor = null;
    /**
     * Function to normalize new validated column value
     * By default: $defaultClosuresClass->valueNormalizer()
     * @var null|\Closure
     */
    protected $valueNormalizer = null;
    /**
     * Validates column value
     * By default: $defaultClosuresClass::valueValidator()
     * @var null|\Closure
     */
    protected $valueValidator = null;
    /**
     * Validates if column value is within $this->allowedValues (if any)
     * By default: $defaultClosuresClass::valueIsAllowedValidator()
     * @var null|\Closure
     */
    protected $valueIsAllowedValidator = null;
    /**
     * Extends default value validator.
     * Useful for additional validation like min/max length, min/max value, regex, etc
     * @var null|\Closure
     */
    protected $valueValidatorExtender = null;
    /**
     * Saves value somewhere except DB. Used only with columns that are not present in DB
     * For example: saves files and images to file system
     * @var null|\Closure
     */
    protected $valueSavingExtender = null;
    /**
     * Deletes value stored somewhere except DB. Used only with columns that are not present in DB
     * For example: deletes files and images from file system
     * @var null|\Closure
     */
    protected $valueDeleteExtender = null;
    /**
     * Formats value. Used in default getter to add possibility to convert original value to specific format.
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     * @var null|\Closure
     */
    protected $valueFormatter = null;
    /**
     * List of value formatters names. Used in default getter to add possibility to convert original value to specific format.
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     * @var null|\Closure
     */
    protected $defaultValueFormattersNames = [];
    /**
     * List of custom value formatters. Used in $this->valueFormatter to extend default list of formatters.
     * @var \Closure[]
     */
    protected $customValueFormatters = [];
    /**
     * Function that generates new value for a column for each save operation
     * Usage example: updated_at column
     * @var null|\Closure
     */
    protected $valueAutoUpdater = null;
    
    // calculated params (not allowed to be set directly)
    /**
     * @var bool
     */
    protected $isFile = false;
    /**
     * @var bool
     */
    protected $isImage = false;
    /**
     * @var bool
     */
    protected $isForeignKey = null;
    /**
     * relation that stores values for this column
     * Note: false value means "needs detection"
     * @var Relation|null
     */
    protected $foreignKeyRelation = false;
    
    // service params
    static public $fileTypes = [
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    ];
    
    static public $imageFileTypes = [
        self::TYPE_IMAGE,
    ];
    
    /**
     * @param string $name
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function create($type, $name = null)
    {
        return new static($name, $type);
    }
    
    /**
     * @param string $name
     * @param string $type
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function __construct($name, $type)
    {
        if (!empty($name)) {
            $this->setName($name);
        }
        $this->setType($type);
        $this->setDefaultClosures();
    }
    
    /**
     * @return \Closure[]
     */
    static protected function getDefaultClosures()
    {
        if (self::$defaultClosures === null) {
            self::$defaultClosures = [
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
        return self::$defaultClosures;
    }
    
    protected function setDefaultClosures()
    {
        $closures = static::getDefaultClosures();
        $this->setValueGetter($closures['valueGetter']);
        $this->setValueExistenceChecker($closures['valueExistenceChecker']);
        $this->setValueSetter($closures['valueSetter']);
        $this->setValueValidator($closures['valueValidator']);
        $this->setValueIsAllowedValidator($closures['valueIsAllowedValidator']);
        $this->setValueValidatorExtender($closures['valueValidatorExtender']);
        $this->setValueNormalizer($closures['valueNormalizer']);
        $this->setValuePreprocessor($closures['valuePreprocessor']);
        $this->setValueSavingExtender($closures['valueSavingExtender']);
        $this->setValueDeleteExtender($closures['valueDeleteExtender']);
        /** @var DefaultColumnClosures $closuresClass */
        $closuresClass = $this->getClosuresClass();
        $this->setValueFormatter($closures['valueFormatter'], $closuresClass::getValueFormats($this));
    }
    
    /**
     * @return array - 0 - \Closure $formatter; 1 - array $formats
     */
    protected function detectValueFormatterByType()
    {
        return RecordValueHelpers::getValueFormatterAndFormatsByType($this->getType());
    }
    
    /**
     * @return TableStructure
     */
    public function getTableStructure(): TableStructure
    {
        return $this->tableStructure;
    }
    
    /**
     * @param TableStructure $tableStructure
     * @return $this
     */
    public function setTableStructure(TableStructure $tableStructure)
    {
        $this->tableStructure = $tableStructure;
        return $this;
    }
    
    /**
     * Class that provides all closures for a column.
     * Note: if some closure is defined via Column->setClosureName(\Closure $fn) then $fn will be used istead of
     * same closure provided by class
     * @param string $class - class that implements ColumnClosuresInterface
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setClosuresClass($class)
    {
        if (
            !is_string($class)
            || !class_exists($class)
            || !(new \ReflectionClass($class))->implementsInterface(ColumnClosuresInterface::class)
        ) {
            throw new \InvalidArgumentException(
                '$class argument must be a string and contain a full name of a calss that implements ColumnClosuresInterface'
            );
        }
        $this->defaultClosuresClass = $class;
        return $this;
    }
    
    /**
     * @return string|ColumnClosuresInterface
     */
    public function getClosuresClass(): string
    {
        return $this->defaultClosuresClass;
    }
    
    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getName()
    {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('DB column name is not provided');
        }
        return $this->name;
    }
    
    /**
     * @return bool
     */
    public function hasName()
    {
        return !empty($this->name);
    }
    
    /**
     * @param string $name
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName($name)
    {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Column name alteration is forbidden');
        }
        if (!is_string($name)) {
            throw new \InvalidArgumentException('$name argument must be a string');
        }
        if (!preg_match(static::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: " . static::NAME_VALIDATION_REGEXP . '. Example: snake_case1'
            );
        }
        $this->name = $name;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * @return bool
     */
    public function isEnum()
    {
        return $this->getType() === static::TYPE_ENUM;
    }
    
    /**
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function setType($type)
    {
        if (!is_string($type) && !is_numeric($type)) {
            throw new \InvalidArgumentException('$type argument must be a string, integer or float');
        }
        if (is_string($type)) {
            $type = strtolower($type);
        }
        $this->type = $type;
        if (in_array($type, self::$fileTypes, true)) {
            $this->itIsFile();
            if (in_array($type, self::$imageFileTypes, true)) {
                $this->itIsImage();
            }
        }
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isValueCanBeNull()
    {
        return $this->valueCanBeNull;
    }
    
    /**
     * @return $this
     */
    public function allowsNullValues()
    {
        $this->valueCanBeNull = true;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function disallowsNullValues()
    {
        $this->valueCanBeNull = false;
        return $this;
    }
    
    /**
     * @param bool $bool
     * @return $this
     */
    public function setIsNullableValue($bool)
    {
        $this->valueCanBeNull = (bool)$bool;
        return $this;
    }
    
    /**
     * Computed value that indicates if value must be not empty
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     */
    public function isValueRequiredToBeNotEmpty()
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
     * @return $this
     */
    public function trimsValue()
    {
        $this->trimValue = true;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isValueTrimmingRequired()
    {
        return $this->trimValue;
    }
    
    /**
     * @return $this
     */
    public function lowercasesValue()
    {
        $this->lowercaseValue = true;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isValueLowercasingRequired()
    {
        return $this->lowercaseValue;
    }
    
    /**
     * Get default value set via $this->setDefaultValue()
     * @return mixed - may be a \Closure: function() { return 'default value'; }
     * @throws \UnexpectedValueException
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
     * @return $this
     */
    public function setValidDefaultValueGetter(\Closure $validDefaultValueGetter)
    {
        $this->validDefaultValueGetter = $validDefaultValueGetter;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function hasDefaultValue()
    {
        if ($this->hasDefaultValue === null) {
            return $this->defaultValue !== self::DEFAULT_VALUE_NOT_SET || $this->validDefaultValueGetter;
        }
        return $this->hasDefaultValue();
    }
    
    /**
     * @param mixed $defaultValue - may be a \Closure: function() { return 'default value'; }
     * @return $this
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        $this->validDefaultValue = self::VALID_DEFAULT_VALUE_UNDEFINED;
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isEmptyStringMustBeConvertedToNull()
    {
        if ($this->convertEmptyStringToNull === null) {
            return $this->isValueCanBeNull();
        } else {
            return $this->convertEmptyStringToNull;
        }
    }
    
    /**
     * @return $this
     */
    public function convertsEmptyStringToNull()
    {
        $this->convertEmptyStringToNull = true;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function setConvertEmptyStringToNull(?bool $convert)
    {
        $this->convertEmptyStringToNull = $convert;
        return $this;
    }
    
    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function getAllowedValues()
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
     * @return $this
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
    
    /**
     * @return boolean
     */
    public function isItPrimaryKey()
    {
        return $this->isPrimaryKey;
    }
    
    /**
     * @return $this
     */
    public function primaryKey()
    {
        $this->isPrimaryKey = true;
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isValueMustBeUnique()
    {
        return $this->isValueMustBeUnique;
    }
    
    /**
     * @return bool
     */
    public function isUniqueContraintCaseSensitive()
    {
        return $this->isUniqueContraintCaseSensitive;
    }
    
    /**
     * @return array
     */
    public function getUniqueContraintAdditonalColumns()
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
     * @return $this
     */
    public function uniqueValues($caseSensitive = self::CASE_SENSITIVE, ...$withinColumns)
    {
        $this->isValueMustBeUnique = true;
        $this->isUniqueContraintCaseSensitive = (bool)$caseSensitive;
        $this->uniqueContraintAdditonalColumns = count($withinColumns) === 1 && isset($withinColumns[0]) && is_array($withinColumns[0])
            ? $withinColumns[0]
            : $withinColumns;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function doesNotExistInDb()
    {
        $this->existsInDb = false;
        return $this;
    }
    
    /**
     * Is this column exists in DB?
     * @return bool
     */
    public function isItExistsInDb()
    {
        return $this->existsInDb;
    }
    
    /**
     * @return $this
     */
    public function valueCannotBeSetOrChanged()
    {
        $this->isValueCanBeSetOrChanged = false;
        return $this;
    }
    
    /**
     * @return bool|int - one of self::ON_UPDATE, self::ON_CREATE, self::ON_ALL, self::ON_NONE
     */
    public function isValueCanBeSetOrChanged()
    {
        return $this->isValueCanBeSetOrChanged;
    }
    
    /**
     * Value contains a lot of data and should not be fetched by '*' selects
     * @return $this
     */
    public function valueIsHeavy()
    {
        $this->isHeavy = true;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isValueHeavy(): bool
    {
        return $this->isHeavy;
    }
    
    /**
     * @return Relation[]
     */
    public function getRelations()
    {
        if ($this->relations === null) {
            $this->relations = $this->getTableStructure()->getColumnRelations($this->getName());
        }
        return $this->relations;
    }
    
    /**
     * @param $relationName
     * @return bool
     */
    public function hasRelation($relationName)
    {
        return isset($this->getRelations()[$relationName]);
    }
    
    /**
     * @param string $relationName
     * @return Relation
     * @throws \InvalidArgumentException
     */
    public function getRelation($relationName)
    {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException(
                "Column '{$this->getName()}' is not linked with '{$relationName}' relation"
            );
        }
        return $this->relations[$relationName];
    }
    
    /**
     * @return null|Relation
     */
    public function getForeignKeyRelation()
    {
        if ($this->foreignKeyRelation === false) {
            $this->foreignKeyRelation = null;
            foreach ($this->getRelations() as $relation) {
                if ($relation->getType() === Relation::BELONGS_TO) {
                    $this->itIsForeignKey($relation);
                    // don't break here - let it validate if there are no multiple foreign keys here
                }
            }
        }
        return $this->foreignKeyRelation;
    }
    
    /**
     * @return boolean
     */
    public function isItAForeignKey()
    {
        return $this->getForeignKeyRelation() !== null;
    }
    
    /**
     * @param Relation $relation - relation that stores values for this column
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
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
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isItAFile()
    {
        return $this->isFile;
    }
    
    /**
     * @return $this
     */
    protected function itIsFile()
    {
        $this->isFile = true;
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isItAnImage()
    {
        return $this->isImage;
    }
    
    /**
     * @return $this
     */
    protected function itIsImage()
    {
        $this->isImage = true;
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function isValuePrivate()
    {
        return $this->isPrivate;
    }
    
    /**
     * Value will not appear in Record->toArray() results and in iteration
     * @return $this
     */
    public function privateValue()
    {
        $this->isPrivate = true;
        return $this;
    }
    
    /**
     * @return array
     */
    static public function getValidationErrorsMessages()
    {
        return static::$validationErrorsMessages ?: static::$defaultValidationErrorsMessages;
    }
    
    /**
     * Provide custom validation errors messages.
     * Default errors are listed in static::$defaultValidationErrorsLocalization
     * @param array $validationErrorsMessages
     */
    static public function setValidationErrorsMessages(array $validationErrorsMessages)
    {
        if (!empty($validationErrorsMessages)) {
            static::$validationErrorsMessages = array_merge(
                static::$defaultValidationErrorsMessages,
                $validationErrorsMessages
            );
        }
    }
    
    /**
     * @return \Closure
     */
    public function getValueSetter()
    {
        return $this->valueSetter;
    }
    
    /**
     * Sets new value. Called after value validation
     * @param \Closure $valueSetter = function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) { modify $valueContainer }
     * @return $this
     */
    public function setValueSetter(\Closure $valueSetter)
    {
        $this->valueSetter = $valueSetter;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValuePreprocessor()
    {
        return $this->valuePreprocessor;
    }
    
    /**
     * Function to preprocess raw value for validation and normalization
     * @param \Closure $newValuePreprocessor = function ($value, $isFromDb, Column $column) { return $value }
     * @return $this
     */
    public function setValuePreprocessor($newValuePreprocessor)
    {
        $this->valuePreprocessor = $newValuePreprocessor;
        return $this;
    }
    
    /**
     * Get function that returns a column value
     * @return \Closure
     */
    public function getValueGetter()
    {
        return $this->valueGetter;
    }
    
    /**
     * @param \Closure $valueGetter = function (RecordValue $value, $format = null) { return $value->getValue(); }
     * Note: do not forget to provide valueExistenceChecker in case of columns that do not exist in db
     * @return $this
     */
    public function setValueGetter(\Closure $valueGetter)
    {
        $this->valueGetter = $valueGetter;
        return $this;
    }
    
    /**
     * Get function that checks if column value is set
     * @return \Closure
     */
    public function getValueExistenceChecker()
    {
        return $this->valueExistenceChecker;
    }
    
    /**
     * Set function that checks if column value is set and returns boolean value (true: value is set)
     * Note: column value is set if it has any value (even null) or default value
     * @param \Closure $valueChecker = function (RecordValue $value, $checkDefaultValue = false) { return true; }
     * @return $this
     */
    public function setValueExistenceChecker(\Closure $valueChecker)
    {
        $this->valueExistenceChecker = $valueChecker;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValueValidator()
    {
        return $this->valueValidator;
    }
    
    /**
     * @param \Closure $validator = function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - value is mixed or a RecordValue instance. If value is mixed - it should be preprocessed
     * - defalut validator uses $this->getValueIsAllowedValidator() and  $this->getValueValidatorExtender(). Make sure
     * to use that additional validators if needed
     * @return $this
     */
    public function setValueValidator(\Closure $validator)
    {
        $this->valueValidator = $validator;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValueIsAllowedValidator()
    {
        return $this->valueIsAllowedValidator;
    }
    
    /**
     * @param \Closure $validator = function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - If you do not use not default value validator - you'll need to call this one manually
     * @return $this
     */
    public function setValueIsAllowedValidator(\Closure $validator)
    {
        $this->valueIsAllowedValidator = $validator;
        return $this;
    }
    
    /**
     * Note: it should not be used as public. Better use setValueValidator() method
     * @return \Closure
     */
    public function getValueValidatorExtender()
    {
        return $this->valueValidatorExtender;
    }
    
    /**
     * Additional validation called after
     * @param \Closure $extender - function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - Value has mixed type, not a RecordValue instance;
     * - If there is no errors - return empty array
     * @return $this
     */
    public function extendValueValidator(\Closure $extender)
    {
        $this->valueValidatorExtender = $extender;
        return $this;
    }
    
    /**
     * Alias for Column::extendValueValidator
     * @param \Closure $validator - function ($value, $isFromDb, $isForCondition, Column $column) { return ['validation error 1', ...]; }
     * @return $this
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
    public function validateValue($value, $isFromDb = false, $isForCondition = false)
    {
        $errors = call_user_func($this->getValueValidator(), $value, $isFromDb, $isForCondition, $this);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Validator closure must return an array');
        }
        return $errors;
    }
    
    /**
     * @return \Closure
     */
    public function getValueNormalizer()
    {
        return $this->valueNormalizer;
    }
    
    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function ($value, $isFromDb, Column $column) { return 'normalized value'; }
     * @return $this
     */
    public function setValueNormalizer(\Closure $normalizer)
    {
        $this->valueNormalizer = $normalizer;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValueSavingExtender()
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
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function setValueSavingExtender(\Closure $valueSaver)
    {
        $this->valueSavingExtender = $valueSaver;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValueDeleteExtender()
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
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function setValueDeleteExtender(\Closure $valueDeleteExtender)
    {
        $this->valueDeleteExtender = $valueDeleteExtender;
        return $this;
    }
    
    /**
     * @return \Closure
     */
    public function getValueFormatter()
    {
        return $this->valueFormatter;
    }
    
    /**
     * Function to transform original value into another format and return result. Used by default value getter
     * @param \Closure $valueFormatter - function (RecordValue $valueContainer, $format) { return 'formatted value'; }
     * @param array $formattersNames - list of formats
     * @return $this
     */
    public function setValueFormatter(\Closure $valueFormatter, array $formattersNames)
    {
        $this->valueFormatter = $valueFormatter;
        $this->defaultValueFormattersNames = $formattersNames;
        return $this;
    }
    
    public function getValueFormattersNames(): array
    {
        return array_merge($this->defaultValueFormattersNames, array_keys($this->customValueFormatters));
    }
    
    /**
     * @param string $name
     * @param \Closure $formatter = function(RecordValue $valueContainer) { return $modifiedValue }
     * @return $this
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
     * @return $this
     */
    public function autoUpdateValueOnEachSaveWith(\Closure $valueGenerator)
    {
        $this->valueAutoUpdater = $valueGenerator;
        return $this;
    }
    
    /**
     * @param RecordInterface|Record|array
     * @return \Closure|null
     * @throws \UnexpectedValueException
     */
    public function getAutoUpdateForAValue($record)
    {
        if (empty($this->valueAutoUpdater)) {
            throw new \UnexpectedValueException('Value auto updater function is not set');
        }
        return call_user_func($this->valueAutoUpdater, $record);
    }
    
    /**
     * @return bool
     */
    public function isAutoUpdatingValue()
    {
        return !empty($this->valueAutoUpdater);
    }
    
}
