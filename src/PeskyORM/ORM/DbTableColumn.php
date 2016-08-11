<?php

namespace PeskyORM\ORM;

/**
 * Value setter workflow:
 * $this->valueSetter is called and it calls
 * 1. $this->valuePreprocessor (result and original value saved to DbRecordValue object)
 * 2. $this->valueValidator (validation errors saved to DbRecordValue->setRawValue(...))
 * 2.1. $this->valueValidatorExtender (if $this->defaultValueValidator() is used and value is still valid)
 * 3. (if value is valid) $this->valueNormalizer (processed value saved to DbRecordValue->setValidValue(....))
 *
 * Value getter workflow:
 * $this->valueGetter is called and it will possibly call $this->valueFormatter
 */
class DbTableColumn {

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
    const VALUE_MUST_BE_STRING = 'value_must_be_string';
    const VALUE_IS_REQUIRED = 'value_is_required';

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
        self::VALUE_MUST_BE_STRING => 'Value must be a string',
        self::VALUE_IS_REQUIRED => 'Value is required',
    ];

    // params that can be set directly or calculated
    /**
     * @var DbTableStructure
     */
    protected $tableStructure;
    /**
     * @var DbTableRelation[]
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
     * @var mixed
     */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    /**
     * @var bool
     */
    protected $convertEmptyStringToNull = false;
    /**
     * @var array
     */
    protected $allowedValues = [];
    /**
     * @var bool
     */
    protected $requireValue = false;
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
     * DbRecord will not save columns that does not exist in DB
     * @var bool
     */
    protected $existsInDb = false;
    /**
     * Allow/disallow value setting and modification
     * DbRecord will not save columns that cannot be set or modified
     * @var int
     */
    protected $isValueCanBeSetOrChanged = true;
    /**
     * Function to return column value. Useful for virtual columns
     * By default: $this->defaultValueGetter()
     * @var null|\Closure
     */
    protected $valueGetter = null;
    /**
     * Function to check if column value is set
     * By default: $this->defaultValueExistenceChecker()
     * @var null|\Closure
     */
    protected $valueExistenceChecker = null;
    /**
     * Function to set new column value
     * By default: $this->defaultValueSetter()
     * @var null|\Closure
     */
    protected $valueSetter = null;
    /**
     * Function to preprocess raw value.
     * Default: $this->defaultNewValuePreprocessor() - uses $this->convertEmptyValueToNull,
     *      $this->trimValue, $this->lowercaseValue params to make value more reliable for validation
     * @var null|\Closure
     */
    protected $newValuePreprocessor = null;
    /**
     * Function to preprocess value received from DB.
     * Default: $this->getNewValuePreprocessor() - uses $this->convertEmptyValueToNull,
     *      $this->trimValue, $this->lowercaseValue params to make value more reliable for validation
     * @var null|\Closure
     */
    protected $dbValuePreprocessor = null;
    /**
     * Function to normalize new validated column value
     * By default: $this->defaultNewValueNormalizer()
     * @var null|\Closure
     */
    protected $newValueNormalizer = null;
    /**
     * Function to normalize column value received for DB
     * By default: $this->getNewValueNormalizer()
     * @var null|\Closure
     */
    protected $dbValueNormalizer = null;
    /**
     * Validate new column value
     * By default: $this->defaultNewValueValidator()
     * @var null|\Closure
     */
    protected $newValueValidator = null;
    /**
     * Validate column value received from DB
     * By default: $this->getNewValueValidator()
     * @var null|\Closure
     */
    protected $dbValueValidator = null;
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
    protected $valueSaver = null;
    /**
     * Deletes value stored somewhere except DB. Used only with columns that are not present in DB
     * For example: deletes files and images from file system
     * @var null|\Closure
     */
    protected $valueDeleter = null;
    /**
     * Formats value. Used in default getter to add possibility to convert original value to specific format
     * For example: convert json to array, or timestamp like 2016-05-24 17:24:00 to unix timestamp
     * @var null|\Closure
     */
    protected $valueFormatter = null;
    /**
     * @var array
     */
    protected $valueFormatterFormats = [];
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
        $this->setValueGetter(function (DbRecordValue $valueContainer, $format = null) {
            return $valueContainer->getColumn()->defaultValueGetter($valueContainer, $format);
        });
        $this->setValueExistenceChecker(function (DbRecordValue $valueContainer) {
            return $valueContainer->getColumn()->defaultValueExistenceChecker($valueContainer);
        });
        $this->setValueSetter(function ($newValue, $isFromDb, DbRecordValue $valueContainer) {
            return $valueContainer->getColumn()->defaultValueSetter($newValue, $isFromDb, $valueContainer);
        });
        $this->setNewValueValidator(function ($value, DbTableColumn $column) {
            return $column->defaultNewValueValidator($value);
        });
        $this->setNewValueNormalizer(function ($value, DbTableColumn $column) {
            return $column->defaultNewValueNormalizer($value);
        });
        $this->setNewValuePreprocessor(function ($newValue, DbTableColumn $column) {
            $column->defaultNewValuePreprocessor($newValue);
        });
        list ($formatter, $formats) = DbRecordValueHelpers::getValueFormatterAndFormatsByType($this->getType());
        if (!empty($formatter)) {
            $this->setValueFormatter($formatter, $formats);
        }
    }

    /**
     * @return DbTableStructure
     */
    public function getTableStructure() {
        return $this->tableStructure;
    }

    /**
     * @param DbTableStructure $tableStructure
     * @return $this
     */
    public function setTableStructure(DbTableStructure $tableStructure) {
        $this->tableStructure = $tableStructure;
        return $this;
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
     * @param string $type
     * @return $this
     */
    protected function setType($type) {
        $type = strtolower($type);
        $this->type = $type;
        if (in_array($type, self::$fileTypes, true)) {
            $this->itIsFile();
            $this->itDoesNotExistInDb();
            $this->valueCannotBeSetOrChanged();
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
     * @return mixed
     */
    public function getDefaultValue() {
        return $this->defaultValue !== self::DEFAULT_VALUE_NOT_SET ? $this->defaultValue : null;
    }

    /**
     * @return bool
     */
    public function hasDefaultValue() {
        return $this->defaultValue !== self::DEFAULT_VALUE_NOT_SET;
    }

    /**
     * @param mixed $defaultValue
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
     */
    public function getAllowedValues() {
        return $this->allowedValues;
    }

    /**
     * @param array $allowedValues
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAllowedValues(array $allowedValues) {
        if (!is_array($allowedValues) || empty($allowedValues)) {
            throw new \InvalidArgumentException('$allowedValues argument cannot be empty');
        }
        $this->allowedValues = $allowedValues;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValueRequired() {
        return $this->requireValue;
    }

    /**
     * @return $this
     */
    public function valueIsRequired() {
        $this->requireValue = true;
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
        $this->isValueCanBeSetOrChanged = true;
        return $this;
    }

    /**
     * @return bool|string - one of self::ON_UPDATE, self::ON_CREATE, self::ON_ALL, self::ON_NONE
     */
    public function isValueCanBeSetOrChanged() {
        return $this->isValueCanBeSetOrChanged;
    }

    /**
     * @return DbTableRelation[]
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * @param string $relationName
     * @return DbTableRelation
     * @throws \InvalidArgumentException
     */
    public function getRelation($relationName) {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException("Relation '$relationName' does not exist");
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
     * @param DbTableRelation $relation
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function addRelation(DbTableRelation $relation) {
        $relationName = $relation->getName();
        if ($relation->getLocalColumnName() !== $this->name && $relation->getForeignColumnName() !== $this->name) {
            throw new \InvalidArgumentException("Relation '{$relationName}' is not connected to column '{$this->name}'");
        }
        if (!empty($this->relations[$relationName])) {
            throw new \InvalidArgumentException("Relation {$relationName} already defined");
        }
        $this->relations[$relationName] = $relation;
        if ($relation->getType() === DbTableRelation::BELONGS_TO) {
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
     * @return callable|null
     */
    public function getValueSetter() {
        return $this->valueSetter;
    }

    /**
     * Sets ne value. Called after value validation
     * @param \Closure $valueSetter = function ($newValue, $isFromDb, DbRecordValue $valueContainer) { modify $valueContainer }
     * @return $this
     */
    public function setValueSetter(\Closure $valueSetter) {
        $this->valueSetter = $valueSetter;
        return $this;
    }

    /**
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param DbRecordValue $valueContainer
     * @return DbRecordValue
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function defaultValueSetter($newValue, $isFromDb, DbRecordValue $valueContainer) {
        if (!$this->isValueCanBeSetOrChanged()) {
            throw new \UnexpectedValueException(
                "Column '{$this->getName()}' restricts value setting and modification"
            );
        }
        $preprocessedValue = $isFromDb ? $this->preprocessDbValue($newValue) : $this->preprocessNewValue($newValue);
        $valueContainer->setRawValue($newValue, $preprocessedValue, $isFromDb);
        $errors = $isFromDb ? $this->validateDbValue($valueContainer) : $this->validateNewValue($valueContainer);
        $valueContainer->setValidationErrors($errors);
        if ($valueContainer->isValid()) {
            $processedValue = call_user_func(+
                $isFromDb ? $this->getDbValueNormalizer() : $this->getNewValueNormalizer(),
                $preprocessedValue,
                $this
            );
            $valueContainer->setValidValue($processedValue, $newValue);
        }
        return $valueContainer;
    }

    /**
     * @return \Closure
     */
    public function getNewValuePreprocessor() {
        return $this->newValuePreprocessor;
    }

    /**
     * Function to preprocess raw value for validation and normalization
     * @param \Closure $newValuePreprocessor = function ($value, DbTableColumn $column) { return $value }
     * @return $this
     */
    public function setNewValuePreprocessor($newValuePreprocessor) {
        $this->newValuePreprocessor = $newValuePreprocessor;
        return $this;
    }

    /**
     * Uses $column->convertsEmptyValueToNull(), $column->mustTrimValue() and $column->mustLowercaseValue()
     * @param mixed $value
     * @return mixed
     */
    public function defaultNewValuePreprocessor($value) {
        if (is_string($value)) {
            if ($this->mustTrimValue()) {
                $value = trim($value);
            }
            if ($this->mustLowercaseValue()) {
                $value = mb_strtolower($value);
            }
            if (empty($value) && $this->isEmptyStringMustBeConvertedToNull()) {
                $value = null;
            }
        }
        return $value;
    }

    /**
     * Preprocess raw value for validation and normalization
     * @param mixed $value
     * @return mixed
     */
    private function preprocessNewValue($value) {
        return call_user_func($this->getNewValuePreprocessor(), $value, $this);
    }

    /**
     * @return \Closure
     */
    public function getDbValuePreprocessor() {
        return $this->dbValuePreprocessor ?: $this->getNewValuePreprocessor();
    }

    /**
     * Function to preprocess DB value for validation and normalization
     * @param \Closure $dbValuePreprocessor = function ($value, DbTableColumn $column) { return $value }
     * @return $this
     */
    public function setDbValuePreprocessor($dbValuePreprocessor) {
        $this->dbValuePreprocessor = $dbValuePreprocessor;
        return $this;
    }

    /**
     * Preprocess raw value for validation and normalization
     * @param mixed $value
     * @return mixed
     */
    private function preprocessDbValue($value) {
        return call_user_func($this->getDbValuePreprocessor(), $value, $this);
    }

    /**
     * Get function that returns a column value
     * @return \Closure
     */
    public function getValueGetter() {
        return $this->valueGetter;
    }

    /**
     * @param DbRecordValue $value
     * @param null|string $format
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function defaultValueGetter(DbRecordValue $value, $format = null) {
        if ($format) {
            if (!$this->hasValueFormatter()) {
                throw new \InvalidArgumentException(
                    '$format argument is not supported. You need to provide a value formatter in DbTableColumn.'
                );
            } else if (empty($this->valueFormatterFormats) || in_array($format, $this->valueFormatterFormats, true)) {
                return call_user_func($this->getValueFormatter(), $value, $format);
            } else {
                throw new \InvalidArgumentException(
                    "Value format named '$format' is not supported. Supported formats: "
                    . implode(', ', $this->valueFormatterFormats)
                );
            }
        } else {
            return $value->getValue();
        }
    }

    /**
     * @param \Closure $valueGetter = function (DbRecordValue $value, $format = null) { return $value->getValue(); }
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
     * @param DbRecordValue $value
     * @return mixed
     */
    public function defaultValueExistenceChecker(DbRecordValue $value) {
        return $value->hasValue();
    }

    /**
     * Set function that checks if column value is set and returns boolean value (true: value is set)
     * Note: column value is set if it has any value (even null) or default value
     * @param \Closure $valueChecker = function (DbRecordValue $value) { return true; }
     * @return $this
     */
    public function setValueExistenceChecker(\Closure $valueChecker) {
        $this->valueExistenceChecker = $valueChecker;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getNewValueValidator() {
        return $this->newValueValidator;
    }

    /**
     * @param DbRecordValue|mixed $value
     * @return array
     * @throws \BadMethodCallException
     */
    public function defaultNewValueValidator($value) {
        if ($value instanceof DbRecordValue) {
            $value = $value->getValue();
        }
        $errors = DbRecordValueHelpers::isValidDbColumnValue(
            $this,
            $value,
            static::getValidationErrorsLocalization()
        );
        if (empty($errors) && $this->hasValueValidatorExtender()) {
            $errors = call_user_func($this->getValueValidatorExtender(), $value, $this);
        }
        return $errors;
    }

    /**
     * @param \Closure $validator = function ($value, DbTableColumn $column) { return ['validation error 1', ...]; }
     * Note: value is mixed or a DbRecordValue instance. If value is mixed - it should be preprocessed
     * @return $this
     */
    public function setNewValueValidator(\Closure $validator) {
        $this->newValueValidator = $validator;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getDbValueValidator() {
        return $this->dbValueValidator ?: $this->getNewValueValidator();
    }

    /**
     * @param \Closure $validator = function ($value, DbTableColumn $column) { return ['validation error 1', ...]; }
     * Note: value is mixed or a DbRecordValue instance. If value is mixed - it should be preprocessed
     * @return $this
     */
    public function setDbValueValidator(\Closure $validator) {
        $this->dbValueValidator = $validator;
        return $this;
    }

    /**
     * Note: it should not be used as public. Better use setValueValidator() method
     * @return \Closure|null
     */
    protected function getValueValidatorExtender() {
        return $this->valueValidatorExtender;
    }

    /**
     * Additional validation called after
     * @param \Closure $extender - function ($value, DbTableColumn $column) { return ['validation error 1', ...]; }
     * Notes:
     * - Value has mixed type, not a DbRecordValue instance;
     * - If there is no errors - return empty array
     * @return $this
     */
    public function extendValueValidator(\Closure $extender) {
        $this->valueValidatorExtender = $extender;
        return $this;
    }

    /**
     * @return bool
     */
    protected function hasValueValidatorExtender() {
        return !empty($this->valueValidatorExtender);
    }

    /**
     * Validates a new value
     * @param mixed|DbRecordValue $value
     * @return mixed
     */
    public function validateNewValue($value) {
        if (!($value instanceof DbRecordValue)) {
            $value = $this->preprocessNewValue($value);
        }
        return call_user_func($this->getNewValueValidator(), $value, $this);
    }

    /**
     * Validates a value received form DB
     * @param mixed|DbRecordValue $value
     * @return mixed
     */
    public function validateDbValue($value) {
        if (!($value instanceof DbRecordValue)) {
            $value = $this->preprocessDbValue($value);
        }
        return call_user_func($this->getDbValueValidator(), $value, $this);
    }

    /**
     * @return \Closure
     */
    protected function getNewValueNormalizer() {
        return $this->newValueNormalizer;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function defaultNewValueNormalizer($value) {
        return DbRecordValueHelpers::normalizeValue($value, $this->getType());
    }

    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function ($value, DbTableColumn $column) { return 'normalized value'; }
     * @return $this
     */
    public function setNewValueNormalizer(\Closure $normalizer) {
        $this->newValueNormalizer = $normalizer;
        return $this;
    }

    /**
     * @return \Closure
     */
    protected function getDbValueNormalizer() {
        return $this->dbValueNormalizer ?: $this->getNewValueNormalizer();
    }

    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function ($value, DbTableColumn $column) { return 'normalized value'; }
     * @return $this
     */
    public function setDbValueNormalizer(\Closure $normalizer) {
        $this->dbValueNormalizer = $normalizer;
        return $this;
    }

    /**
     * @return \Closure|null
     */
    public function getValueSaver() {
        return $this->valueSaver;
    }

    /**
     * @param \Closure $valueSaver function (DbRecordValue $valueContainer) { save value somewhere }
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function setValueSaver(\Closure $valueSaver) {
        if ($this->isItExistsInDb()) {
            throw new \UnexpectedValueException(
                'Value saver function is not allowed for columns that are present in DB'
            );
        }
        $this->valueSaver = $valueSaver;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasValueSaver() {
        return $this->valueSaver !== null;
    }

    /**
     * @return \Closure|null
     */
    public function getValueDeleter() {
        return $this->valueDeleter;
    }

    /**
     * @param \Closure $valueDeleter function (DbRecordValue $valueContainer) { save value somewhere }
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function setValueDeleter(\Closure $valueDeleter) {
        if ($this->isItExistsInDb()) {
            throw new \UnexpectedValueException(
                'Value deleter function is not allowed for columns that are present in DB'
            );
        }
        $this->valueDeleter = $valueDeleter;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasValueDeleter() {
        return $this->valueDeleter !== null;
    }

    /**
     * @return \Closure|null
     */
    public function getValueFormatter() {
        return $this->valueFormatter;
    }

    /**
     * Function to transform original value into another format and return result. Used by default value getter
     * @param \Closure $valueFormatter - function (DbRecordValue $valueContainer, $format) { return 'formatted value'; }
     * @param array $formats - list of value format names. Used to validate if format passed to getter is valid before
     *      calling formatter. If empty - this validation is disabled.
     *      Example: ['ts', 'date', 'time'] for timestamp column.
     * @return $this
     */
    public function setValueFormatter(\Closure $valueFormatter, array $formats = []) {
        $this->valueFormatter = $valueFormatter;
        $this->valueFormatterFormats = $formats;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasValueFormatter() {
        return !empty($this->valueFormatter);
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