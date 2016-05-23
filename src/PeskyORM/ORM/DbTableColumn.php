<?php

namespace PeskyORM\ORM;

use PeskyORM\ORM\Exception\ValueNotFoundException;

class DbTableColumn {

    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOL = 'boolean';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
    const TYPE_JSONB = 'jsonb';
    const TYPE_SHA1 = 'sha1';
    const TYPE_MD5 = 'md5';
    const TYPE_PASSWORD = 'password';
    const TYPE_EMAIL = 'email';
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
     * function (array $values, DbRecord $record, DbTableColumn $column) { return 'some_value'; }
     * @var null|\Closure
     */
    protected $valueGetter = null;
    /**
     * Function to process new column value
     * function ($newValue, DbTableColumn $column, DbRecord $record) { return 'some_value'; }
     * By default: DbTableColumnHelpers::getNewValueProcessorForType($this->getType())
     * @var null|\Closure
     */
    protected $newValueProcessor = null;
    /**
     * Validate value for a column - function ($value, $isFromDb, DbTableColumn $column, DbRecord $record) { return true; }
     * By default: DbTableColumnHelpers::getValueValidatorForType($this->getType()
     * @var null|\Closure
     */
    protected $valueValidator = null;

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
     * @return bool|string - false: don't import
     * @throws \BadMethodCallException
     */
    public function getColumnNameToImportValueFrom() {
        if (!empty($this->importValueFromColumn)) {
            if (!$this->tableStructure->hasColumn($this->importValueFromColumn)) {
                throw new \BadMethodCallException("Column '{$this->importValueFromColumn}' is not defined");
            } else if ($this->getName() === $this->importValueFromColumn) {
                throw new \BadMethodCallException(
                    '$this->importVirtualColumnValueFrom is same as this column name: ' . $this->getName()
                );
            }
        }
        return $this->importValueFromColumn;
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
     * @return callable|null
     */
    public function getNewValueProcessor() {
        return $this->newValueProcessor;
    }

    /**
     * @param \Closure $newValueProcessor
     * @return $this
     */
    public function setNewValueProcessor(\Closure $newValueProcessor) {
        $this->newValueProcessor = $newValueProcessor;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasNewValueProcessor() {
        return !empty($this->newValueProcessor);
    }

    /**
     * @return \Closure|null
     * @throws \PeskyORM\ORM\Exception\ValueNotFoundException
     * @throws \BadMethodCallException
     */
    public function getValueGetter() {
        return $this->valueGetter ?: $this->getDefaultValueGetter();
    }

    /**
     * @return \Closure
     * @throws \PeskyORM\ORM\Exception\ValueNotFoundException
     * @throws \BadMethodCallException
     */
    protected function getDefaultValueGetter() {
        return function (array $values, DbRecord $record, DbTableColumn $column) {
            if (array_key_exists($column->getName(), $values)) {
                return $values[$column->getName()];
            } else if ($column->hasDefaultValue()) {
                return $column->getDefaultValue();
            } else {
                throw new ValueNotFoundException(
                    "Record has no value for column {$column->getName()} and default value is not provided"
                );
            }
        };
    }

    /**
     * @param \Closure|null $valueGetter
     * @return $this
     */
    public function setValueGetter($valueGetter) {
        $this->valueGetter = $valueGetter;
        return $this;
    }

    /**
     * @return \Closure|null
     */
    public function getValueValidator() {
        // todo: return some default value on $this->valueValidator === null
        return $this->valueValidator;
    }

    /**
     * @param \Closure|null $valueValidator
     * @return $this
     */
    public function setValueValidator($valueValidator) {
        $this->valueValidator = $valueValidator;
        return $this;
    }

}