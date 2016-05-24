<?php

namespace PeskyORM\ORM;

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
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_TIMEZONE_OFFSET = 'timezone_offset';
    const TYPE_ENUM = 'enum';
    const TYPE_IPV4_ADDRESS = 'ip';
    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';

    const NAME_VALIDATION_REGEXP = '%^[a-z][a-z0-9_]*$%';

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
    const VALUE_MUST_BE_TIMEZONE = 'value_must_be_timezone';
    const VALUE_MUST_BE_TIME = 'value_must_be_time';
    const VALUE_MUST_BE_DATE = 'value_must_be_date';
    const VALUE_IS_NOT_ALLOWED = 'value_is_not_allowed';
    const VALUE_MUST_BE_STRING = 'value_is_not_allowed';
    
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
        self::VALUE_MUST_BE_TIMEZONE => 'Value must be a valid timestamp',
        self::VALUE_MUST_BE_TIME => 'Value must be a valid time',
        self::VALUE_MUST_BE_DATE => 'Value must be a valid date',
        self::VALUE_IS_NOT_ALLOWED => 'Value is not allowed',
        self::VALUE_MUST_BE_STRING => 'Value must be a string',
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
     * @var mixed
     */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    /**
     * @var bool
     */
    protected $convertEmptyValueToNull = false;
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
     * By default: $this->getDefaultValueGetter()
     * @var null|\Closure
     */
    protected $valueGetter = null;
    /**
     * Function to set new column value
     * By default: $this->getDefaultValueSetter()
     * @var null|\Closure
     */
    protected $valueSetter = null;
    /**
     * Function to normalize new validated column value
     * By default: $this->getDefaultValueNormalizer()
     * @var null|\Closure
     */
    protected $valueNormalizer = null;
    /**
     * Validate value for a column
     * By default: $this->getDefaultValueValidator()
     * @var null|\Closure
     */
    protected $valueValidator = null;
     /**
     * Extends default value validator.
     * Useful for additional validation like min/max length, min/max value, regex, etc
     * @var null|\Closure
     */
    protected $valueValidatorExtender = null;
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __construct($name, $type) {
        if (!empty($name)) {
            $this->setName($name);
        }
        $this->setType($type);
        $this->setDefaultClosures();
    }

    protected function setDefaultClosures() {
        $this->setValueGetter(function (DbRecordValue $value, $format = null) {
            return $this->defaultValueGetter($value, $format);
        });
        $this->setValueSetter(function ($newValue, $isFromDb, DbRecordValue $valueContainer) {
            return $this->defaultValueSetter($newValue, $isFromDb, $valueContainer);
        });
        $this->setValueValidator(function (DbRecordValue $value) {
            return $this->defaultValueValidator($value);
        });
        $this->setValueNormalizer(function ($value, $type) {
            return $this->defaultValueNormalizer($value, $type);
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
     * @throws \BadMethodCallException
     */
    public function getName() {
        if (empty($this->name)) {
            throw new \BadMethodCallException('DB column name is not provided');
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setName($name) {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Column name alteration is forbidden');
        }
        if (!preg_match(static::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: " . static::NAME_VALIDATION_REGEXP
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
     * @throws \InvalidArgumentException
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
     * @param boolean $isNullable
     * @return $this
     */
    public function valueCanBeNull($isNullable) {
        $this->valueCanBeNull = (bool)$isNullable;
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
    public function isEmptyValueMustBeConvertedToNull() {
        return $this->convertEmptyValueToNull;
    }

    /**
     * @return $this
     */
    public function convertsEmptyValueToNull() {
        $this->convertEmptyValueToNull = true;
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
        if ($relation->getLocalColumn() !== $this->name && $relation->getForeignColumn() !== $this->name) {
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
    public function isItForeignKey() {
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
     * @param array $validationErrorsLocalization
     * @return $this
     */
    static public function setValidationErrorsLocalization(array $validationErrorsLocalization) {
        static::$validationErrorsLocalization = $validationErrorsLocalization;
    }

    /**
     * @return callable|null
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getValueSetter() {
        return $this->valueSetter;
    }

    /**
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param DbRecordValue $valueContainer
     * @return DbRecordValue
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function defaultValueSetter($newValue, $isFromDb, DbRecordValue $valueContainer) {
        if (!$valueContainer->getColumn()->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "Column '{$valueContainer->getColumn()->getName()}' restrits value setting adn modification"
            );
        }
        $valueContainer->setRawValue($newValue, $isFromDb);
        $valueContainer->setValidationErrors(call_user_func($this->getValueValidator(), $valueContainer));
        if ($valueContainer->isValid()) {
            $processedValue = call_user_func(
                $valueContainer->getColumn()->getValueNormalizer(),
                $newValue
            );
            $valueContainer->setValidValue($processedValue, $newValue);
        }
        return $valueContainer;
    }

    /**
     * Sets ne value. Called after value validation
     * @param \Closure $valueSetter = function ($newValue, $isFromDb, DbRecordValue $valueContainer) { modify $$valueContainer }
     * @return $this
     */
    public function setValueSetter(\Closure $valueSetter) {
        $this->valueSetter = $valueSetter;
        return $this;
    }

    /**
     * Returns a value
     * @return \Closure
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\ValueNotFoundException
     * @throws \BadMethodCallException
     */
    public function getValueGetter() {
        return $this->valueGetter;
    }

    /**
     * @param DbRecordValue $value
     * @param null|string $format
     * @return \Closure
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
     * @return \Closure
     */
    public function getValueValidator() {
        return $this->valueValidator;
    }

    /**
     * @param DbRecordValue $value
     * @return \Closure
     */
    public function defaultValueValidator(DbRecordValue $value) {
        // todo: prepare value for validation (convert empty to null, trim, etc..)
        $isValid = DbRecordValueHelpers::isValidDbColumnValue(
            $value->getColumn(),
            $value->getRawValue(),
            static::getValidationErrorsLocalization()
        );
        if ($isValid && $this->hasValueValidatorExtender()) {
            $isValid = call_user_func($this->getValueValidatorExtender(), $value);
        }
        return $isValid;
    }

    /**
     * @param \Closure $validator = function (DbRecordValue $value) { return ['validation error 1', ...]; }
     * @return $this
     */
    public function setValueValidator(\Closure $validator) {
        $this->valueValidator = $validator;
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
     * @param \Closure $extender - function (DbRecordValue $value) { return ['validation error 1', ...]; }
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
     * @return \Closure
     */
    protected function getValueNormalizer() {
        return $this->valueNormalizer;
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function defaultValueNormalizer($value, $type) {
        return DbRecordValueHelpers::normalizeValue($value, $type);
    }

    /**
     * Function to process new value (for example: convert a value to proper data type)
     * @param \Closure $normalizer - function ($value, $type) { return 'normalized value'; }
     * @return $this
     */
    public function setValueNormalizer(\Closure $normalizer) {
        $this->valueNormalizer = $normalizer;
        return $this;
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

}