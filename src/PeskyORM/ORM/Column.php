<?php

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
class Column {

    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOL = 'boolean';
    const TYPE_STRING = 'string';
    const TYPE_EMAIL = 'email';
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
    const TYPE_JSONB = 'jsonb';
    const TYPE_PASSWORD = 'password';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIMESTAMP_WITH_TZ = 'timestamp_tz';
    const TYPE_UNIX_TIMESTAMP = 'unix_timestamp';
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_TIMEZONE_OFFSET = 'timezone_offset';
    const TYPE_ENUM = 'enum';
    const TYPE_IPV4_ADDRESS = 'ip';
    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';
    const TYPE_BLOB = 'blob';

    const NAME_VALIDATION_REGEXP = '%^[a-z][a-z0-9_]*$%';    //< snake_case

    const DEFAULT_VALUE_NOT_SET = '___NOT_SET___';

    const VALUE_CANNOT_BE_NULL = 'value_cannot_be_null';
    const VALUE_MUST_BE_BOOLEAN = 'value_must_be_boolean';
    const VALUE_MUST_BE_INTEGER = 'value_must_be_integer';
    const VALUE_MUST_BE_FLOAT = 'value_must_be_float';
    const VALUE_MUST_BE_IMAGE = 'value_must_be_image';
    const VALUE_MUST_BE_FILE = 'value_must_be_file';
    const VALUE_MUST_BE_JSON = 'value_must_be_json';
    const VALUE_MUST_BE_IPV4_ADDRESS = 'value_must_be_ipv4_address';
    const VALUE_MUST_BE_EMAIL = 'value_must_be_email';
    const VALUE_MUST_BE_TIMEZONE_OFFSET = 'value_must_be_timezone_offset';
    const VALUE_MUST_BE_TIMESTAMP = 'value_must_be_timestamp';
    const VALUE_MUST_BE_TIMESTAMP_WITH_TZ = 'value_must_be_timestamp_with_tz';
    const VALUE_MUST_BE_TIME = 'value_must_be_time';
    const VALUE_MUST_BE_DATE = 'value_must_be_date';
    const VALUE_IS_NOT_ALLOWED = 'value_is_not_allowed';
    const ONE_OF_VALUES_IS_NOT_ALLOWED = 'one_of_values_is_not_allowed';
    const VALUE_MUST_BE_STRING = 'value_must_be_string';
    const VALUE_MUST_BE_STRING_OR_NUMERIC = 'value_must_be_string_or_numeric';

    /**
     * @var array
     */
    static protected $validationErrorsLocalization = [
        self::VALUE_CANNOT_BE_NULL => 'Null value is not allowed',
        self::VALUE_MUST_BE_BOOLEAN => 'Value must be of a boolean data type',
        self::VALUE_MUST_BE_INTEGER => 'Value must be of an integer data type',
        self::VALUE_MUST_BE_FLOAT => 'Value must be of a numeric data type',
        self::VALUE_MUST_BE_IMAGE => 'Value must be an uploaded image info',
        self::VALUE_MUST_BE_FILE => 'Value must be an uploaded file info',
        self::VALUE_MUST_BE_JSON => 'Value must be of a json data type',
        self::VALUE_MUST_BE_IPV4_ADDRESS => 'Value must be an IPv4 address',
        self::VALUE_MUST_BE_EMAIL => 'Value must be an email',
        self::VALUE_MUST_BE_TIMEZONE_OFFSET => 'Value must be a valid timezone offset',
        self::VALUE_MUST_BE_TIMESTAMP => 'Value must be a valid timestamp',
        self::VALUE_MUST_BE_TIMESTAMP_WITH_TZ => 'Value must be a valid timestamp with time zone',
        self::VALUE_MUST_BE_TIME => 'Value must be a valid time',
        self::VALUE_MUST_BE_DATE => 'Value must be a valid date',
        self::VALUE_IS_NOT_ALLOWED => 'Value is not allowed',
        self::ONE_OF_VALUES_IS_NOT_ALLOWED => 'One of values in the received array is not allowed',
        self::VALUE_MUST_BE_STRING => 'Value must be a string',
        self::VALUE_MUST_BE_STRING_OR_NUMERIC => 'Value must be a string or a number',
    ];

    // params that can be set directly or calculated
    /**
     * @var TableStructure
     */
    protected $tableStructure;
    /**
     * @var Relation[]
     */
    protected $relations = [];
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
     * @var bool
     */
    protected $convertEmptyStringToNull = false;
    /**
     * @var array|\Closure
     */
    protected $allowedValues = [];
    /**
     * @var mixed - can be a \Closure
     */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    /**
     * @var bool
     */
    protected $isPrimaryKey = false;
    /**
     * @var bool
     */
    protected $isValueMustBeUnique = false;
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
     * Formats value. Used in default getter to add possibility to convert original value to specific format
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     * @var null|\Closure
     */
    protected $valueFormatter = null;
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
    protected $isForeignKey = false;

    // service params
    static public $fileTypes = array(
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    );

    static public $imageFileTypes = array(
        self::TYPE_IMAGE,
    );

    /**
     * @param string $name
     * @param string $type
     * @return $this
     */
    static public function create($type, $name = null) {
        $className = get_called_class();
        return new $className($name, $type);
    }

    /**
     * @param string $name
     * @param string $type
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function __construct($name, $type) {
        if (!empty($name)) {
            $this->setName($name);
        }
        $this->setType($type);
        $this->setDefaultClosures();
    }

    protected function setDefaultClosures() {
        $this->setValueGetter(function (RecordValue $valueContainer, $format = null) {
            $class = $this->getClosuresClass();
            return $class::valueGetter($valueContainer, $format);
        });
        $this->setValueExistenceChecker(function (RecordValue $valueContainer, $checkDefaultValue = false) {
            $class = $this->getClosuresClass();
            return $class::valueExistenceChecker($valueContainer, $checkDefaultValue);
        });
        $this->setValueSetter(function ($newValue, $isFromDb, RecordValue $valueContainer) {
            $class = $this->getClosuresClass();
            return $class::valueSetter($newValue, $isFromDb, $valueContainer);
        });
        $this->setValueValidator(function ($value, $isFromDb, Column $column) {
            $class = $this->getClosuresClass();
            return $class::valueValidator($value, $isFromDb, $column);
        });
        $this->setValueIsAllowedValidator(function ($value, $isFromDb, Column $column) {
            $class = $this->getClosuresClass();
            return $class::valueIsAllowedValidator($value, $isFromDb, $column);
        });
        $this->setValueValidatorExtender(function ($value, $isFromDb, Column $column) {
            $class = $this->getClosuresClass();
            return $class::valueValidatorExtender($value, $isFromDb, $column);
        });
        $this->setValueNormalizer(function ($value, $isFromDb, Column $column) {
            $class = $this->getClosuresClass();
            return $class::valueNormalizer($value, $isFromDb, $column);
        });
        $this->setValuePreprocessor(function ($newValue, $isFromDb, Column $column) {
            $class = $this->getClosuresClass();
            return $class::valuePreprocessor($newValue, $isFromDb, $column);
        });
        $this->setValueSavingExtender(function (RecordValue $valueContainer, $isUpdate, array $savedData) {
            $class = $this->getClosuresClass();
            return $class::valueSavingExtender($valueContainer, $isUpdate, $savedData);
        });
        $this->setValueDeleteExtender(function (RecordValue $valueContainer, $deleteFiles) {
            $class = $this->getClosuresClass();
            return $class::valueDeleteExtender($valueContainer, $deleteFiles);
        });
        $this->setValueFormatter(function (RecordValue $valueContainer, $format) {
            $class = $this->getClosuresClass();
            return $class::valueFormatter($valueContainer, $format);
        });
    }

    /**
     * @return array - 0 - \Closure $formatter; 1 - array $formats
     */
    protected function detectValueFormatterByType() {
        return RecordValueHelpers::getValueFormatterAndFormatsByType($this->getType());
    }

    /**
     * @return TableStructure
     */
    public function getTableStructure() {
        return $this->tableStructure;
    }

    /**
     * @param TableStructure $tableStructure
     * @return $this
     */
    public function setTableStructure(TableStructure $tableStructure) {
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
    public function setClosuresClass($class) {
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
    public function getClosuresClass() {
        return $this->defaultClosuresClass;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getName() {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('DB column name is not provided');
        }
        return $this->name;
    }

    /**
     * @return bool
     */
    public function hasName() {
        return !empty($this->name);
    }

    /**
     * @param string $name
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName($name) {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Column name alteration is forbidden');
        }
        if (!is_string($name)) {
            throw new \InvalidArgumentException('$name argument must be a string');
        }
        if (!preg_match(static::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: " . static::NAME_VALIDATION_REGEXP  . '. Example: snake_case1'
            );
        }
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isEnum() {
        return $this->getType() === static::TYPE_ENUM;
    }

    /**
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function setType($type) {
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
    public function isValueCanBeNull() {
        return $this->valueCanBeNull;
    }

    /**
     * @return $this
     */
    public function valueIsNullable() {
        $this->valueCanBeNull = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function valueIsNotNullable() {
        $this->valueCanBeNull = false;
        return $this;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setIsNullable($bool) {
        $this->valueCanBeNull = (bool)$bool;
        return $this;
    }

    /**
     * @return $this
     */
    public function mustTrimValue() {
        $this->trimValue = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValueTrimmingRequired() {
        return $this->trimValue;
    }

    /**
     * @return $this
     */
    public function mustLowercaseValue() {
        $this->lowercaseValue = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValueLowercasingRequired() {
        return $this->lowercaseValue;
    }

    /**
     * Get default value set via $this->setDefaultValue()
     * @return mixed - may be a \Closure: function() { return 'default value'; }
     * @throws \BadMethodCallException
     */
    public function getDefaultValueAsIs() {
        if (!$this->hasDefaultValue()) {
            throw new \BadMethodCallException("Default value for column '{$this->getName()}' is not set");
        }
        return $this->defaultValue;
    }

    /**
     * Get validated default value
     * @param mixed $fallbackValue - value to be returned when default value was not configured (may be a \Closure)
     * @return mixed - validated default value or $fallbackValue or return from $this->validDefaultValueGetter
     * @throws \UnexpectedValueException
     */
    public function getValidDefaultValue($fallbackValue = null) {
        if ($this->validDefaultValueGetter) {
            $defaultValue = call_user_func($this->validDefaultValueGetter, $fallbackValue, $this);
            $excPrefix = 'Default value received from validDefaultValueGetter closure';
        } else if ($this->hasDefaultValue()) {
            $defaultValue = $this->defaultValue;
            $excPrefix = 'Default value';
        } else {
            $defaultValue = $fallbackValue;
            $excPrefix = 'Fallback value of the default value';
        }
        if ($defaultValue instanceof \Closure) {
            $defaultValue = $defaultValue();
        }
        $errors = $this->validateValue($defaultValue, false);
        if (!($defaultValue instanceof DbExpr) && count($errors) > 0) {
            throw new \UnexpectedValueException(
                "{$excPrefix} for column '{$this->getName()}' is not valid. Errors: " . implode(', ', $errors)
            );
        }
        return $defaultValue;
    }

    /**
     * @param \Closure $validDefaultValueGetter - function ($fallbackValue, Column $column) { return 'default'; }
     * @return $this
     */
    public function setValidDefaultValueGetter(\Closure $validDefaultValueGetter) {
        $this->validDefaultValueGetter = $validDefaultValueGetter;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasDefaultValue() {
        return $this->defaultValue !== self::DEFAULT_VALUE_NOT_SET || $this->validDefaultValueGetter;
    }

    /**
     * @param mixed $defaultValue - may be a \Closure: function() { return 'default value'; }
     * @return $this
     */
    public function setDefaultValue($defaultValue) {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isEmptyStringMustBeConvertedToNull() {
        return $this->convertEmptyStringToNull;
    }

    /**
     * @return $this
     */
    public function convertsEmptyStringToNull() {
        $this->convertEmptyStringToNull = true;
        return $this;
    }

    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function getAllowedValues() {
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
    public function setAllowedValues($allowedValues) {
        if (!($allowedValues instanceof \Closure) && (!is_array($allowedValues) || empty($allowedValues))) {
            throw new \InvalidArgumentException('$allowedValues argument cannot be empty');
        }
        $this->allowedValues = $allowedValues;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isItPrimaryKey() {
        return $this->isPrimaryKey;
    }

    /**
     * @return $this
     */
    public function itIsPrimaryKey() {
        $this->isPrimaryKey = true;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isValueMustBeUnique() {
        return $this->isValueMustBeUnique;
    }

    /**
     * @param boolean $isUnique
     * @return $this
     */
    public function valueMustBeUnique($isUnique) {
        $this->isValueMustBeUnique = (bool)$isUnique;
        return $this;
    }

    /**
     * @return $this
     */
    public function itDoesNotExistInDb() {
        $this->existsInDb = false;
        return $this;
    }

    /**
     * Is this column exists in DB?
     * @return bool
     */
    public function isItExistsInDb() {
        return $this->existsInDb;
    }

    /**
     * @return $this
     */
    public function valueCannotBeSetOrChanged() {
        $this->isValueCanBeSetOrChanged = false;
        return $this;
    }

    /**
     * @return bool|string - one of self::ON_UPDATE, self::ON_CREATE, self::ON_ALL, self::ON_NONE
     */
    public function isValueCanBeSetOrChanged() {
        return $this->isValueCanBeSetOrChanged;
    }

    /**
     * @return Relation[]
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * @param string $relationName
     * @return Relation
     * @throws \InvalidArgumentException
     */
    public function getRelation($relationName) {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException("Relation '{$relationName}' does not exist");
        }
        return $this->relations[$relationName];
    }

    /**
     * @param $relationName
     * @return bool
     */
    public function hasRelation($relationName) {
        return !empty($this->relations[$relationName]);
    }

    /**
     * @param Relation $relation
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function addRelation(Relation $relation) {
        $colName = $this->getName();
        $relationName = $relation->getName();
        if ($relation->getLocalColumnName() !== $colName) {
            throw new \InvalidArgumentException("Relation '{$relationName}' is not connected to column '{$colName}'");
        }
        if (!empty($this->relations[$relationName])) {
            throw new \InvalidArgumentException("Relation '{$relationName}' already defined for column '{$colName}'");
        }
        $this->relations[$relationName] = $relation;
        if ($relation->getType() === Relation::BELONGS_TO) {
            $this->itIsForeignKey();
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function isItAFile() {
        return $this->isFile;
    }

    /**
     * @return $this
     */
    protected function itIsFile() {
        $this->isFile = true;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isItAnImage() {
        return $this->isImage;
    }

    /**
     * @return $this
     */
    protected function itIsImage() {
        $this->isImage = true;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isItAForeignKey() {
        return $this->isForeignKey;
    }

    /**
     * @return $this
     */
    protected function itIsForeignKey() {
        $this->isForeignKey = true;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isValuePrivate() {
        return $this->isPrivate;
    }

    /**
     * @return $this
     */
    public function valueIsPrivate() {
        $this->isPrivate = true;
        return $this;
    }

    /**
     * @return array
     */
    static public function getValidationErrorsLocalization() {
        return static::$validationErrorsLocalization;
    }

    /**
     * All errors are listed in static::$validationErrorsLocalization
     * @param array $validationErrorsLocalization
     */
    static public function setValidationErrorsLocalization(array $validationErrorsLocalization) {
        static::$validationErrorsLocalization = $validationErrorsLocalization;
    }

    /**
     * @return \Closure
     */
    public function getValueSetter() {
        return $this->valueSetter;
    }

    /**
     * Sets new value. Called after value validation
     * @param \Closure $valueSetter = function ($newValue, $isFromDb, RecordValue $valueContainer) { modify $valueContainer }
     * @return $this
     */
    public function setValueSetter(\Closure $valueSetter) {
        $this->valueSetter = $valueSetter;
        return $this;
    }



    /**
     * @return \Closure
     */
    public function getValuePreprocessor() {
        return $this->valuePreprocessor;
    }

    /**
     * Function to preprocess raw value for validation and normalization
     * @param \Closure $newValuePreprocessor = function ($value, $isFromDb, Column $column, $defaultProcessor) { return $value }
     * @return $this
     */
    public function setValuePreprocessor($newValuePreprocessor) {
        $this->valuePreprocessor = $newValuePreprocessor;
        return $this;
    }



    /**
     * Get function that returns a column value
     * @return \Closure
     */
    public function getValueGetter() {
        return $this->valueGetter;
    }

    /**
     * @param \Closure $valueGetter = function (RecordValue $value, $format = null) { return $value->getValue(); }
     * @return $this
     */
    public function setValueGetter(\Closure $valueGetter) {
        $this->valueGetter = $valueGetter;
        return $this;
    }

    /**
     * Get function that checks if column value is set
     * @return \Closure
     */
    public function getValueExistenceChecker() {
        return $this->valueExistenceChecker;
    }

    /**
     * Set function that checks if column value is set and returns boolean value (true: value is set)
     * Note: column value is set if it has any value (even null) or default value
     * @param \Closure $valueChecker = function (RecordValue $value, $checkDefaultValue = false) { return true; }
     * @return $this
     */
    public function setValueExistenceChecker(\Closure $valueChecker) {
        $this->valueExistenceChecker = $valueChecker;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getValueValidator() {
        return $this->valueValidator;
    }

    /**
     * @param \Closure $validator = function ($value, $isFromDb, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - value is mixed or a RecordValue instance. If value is mixed - it should be preprocessed
     * - defalut validator uses $this->getValueIsAllowedValidator() and  $this->getValueValidatorExtender(). Make sure
     * to use that additional validators if needed
     * @return $this
     */
    public function setValueValidator(\Closure $validator) {
        $this->valueValidator = $validator;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getValueIsAllowedValidator() {
        return $this->valueIsAllowedValidator;
    }

    /**
     * @param \Closure $validator = function ($value, $isFromDb, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - If you do not use not default value validator - you'll need to call this one manually
     * @return $this
     */
    public function setValueIsAllowedValidator(\Closure $validator) {
        $this->valueIsAllowedValidator = $validator;
        return $this;
    }

    /**
     * Note: it should not be used as public. Better use setValueValidator() method
     * @return \Closure
     */
    public function getValueValidatorExtender() {
        return $this->valueValidatorExtender;
    }

    /**
     * Additional validation called after
     * @param \Closure $extender - function ($value, $isFromDb, Column $column) { return ['validation error 1', ...]; }
     * Notes:
     * - Value has mixed type, not a RecordValue instance;
     * - If there is no errors - return empty array
     * @return $this
     */
    public function extendValueValidator(\Closure $extender) {
        $this->valueValidatorExtender = $extender;
        return $this;
    }

    /**
     * Alias for Column::extendValueValidator
     * @see Column::extendValueValidator
     * @param \Closure $validator
     * @return $this
     */
    public function setValueValidatorExtender(\Closure $validator) {
        return $this->extendValueValidator($validator);
    }

    /**
     * Validates a new value
     * @param mixed|RecordValue $value
     * @param bool $isFromDb - true: value received from DB
     * @return array
     * @throws \UnexpectedValueException
     */
    public function validateValue($value, $isFromDb = false) {
        $errors = call_user_func($this->getValueValidator(), $value, $isFromDb, $this);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Validator closure must return an array');
        }
        return $errors;
    }

    /**
     * @return \Closure
     */
    public function getValueNormalizer() {
        return $this->valueNormalizer;
    }

    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function ($value, $isFromDb, Column $column) { return 'normalized value'; }
     * @return $this
     */
    public function setValueNormalizer(\Closure $normalizer) {
        $this->valueNormalizer = $normalizer;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getValueSavingExtender() {
        return $this->valueSavingExtender;
    }

    /**
     * Additional processing for a column's value during a save.
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
    public function setValueSavingExtender(\Closure $valueSaver) {
        $this->valueSavingExtender = $valueSaver;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getValueDeleteExtender() {
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
    public function setValueDeleteExtender(\Closure $valueDeleteExtender) {
        $this->valueDeleteExtender = $valueDeleteExtender;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getValueFormatter() {
        return $this->valueFormatter;
    }

    /**
     * Function to transform original value into another format and return result. Used by default value getter
     * @param \Closure $valueFormatter - function (RecordValue $valueContainer, $format) { return 'formatted value'; }
     * @return $this
     */
    public function setValueFormatter(\Closure $valueFormatter) {
        $this->valueFormatter = $valueFormatter;
        return $this;
    }

    /**
     * @param \Closure $valueGenerator
     * @return $this
     */
    public function autoUpdateValueOnEachSaveWith(\Closure $valueGenerator) {
        $this->valueAutoUpdater = $valueGenerator;
        return $this;
    }

    /**
     * @return \Closure|null
     * @throws \UnexpectedValueException
     */
    public function getAutoUpdateForAValue() {
        if (empty($this->valueAutoUpdater)) {
            throw new \UnexpectedValueException('Value auto updater function is not set');
        }
        return call_user_func($this->valueAutoUpdater);
    }

    /**
     * @return bool
     */
    public function isAutoUpdatingValue() {
        return !empty($this->valueAutoUpdater);
    }

}