<?php

namespace PeskyORM;

use PeskyORM\Exception\DbColumnConfigException;
use PeskyORM\Lib\ValidateValue;
use PeskyORM\Lib\StringUtils;

class DbColumnConfig {

    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOL = 'boolean';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
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

    const DB_TYPE_VARCHAR = 'varchar';
    const DB_TYPE_TEXT = 'text';
    const DB_TYPE_INT = 'integer';
    const DB_TYPE_FLOAT = 'numeric';
    const DB_TYPE_BOOL = 'boolean';
    const DB_TYPE_TIMESTAMP = 'timestamp';
    const DB_TYPE_DATE = 'date';
    const DB_TYPE_TIME = 'time';
    const DB_TYPE_IP_ADDRESS = 'ip';

    /**
     * Contains map where keys are column types and values are real DB types
     * If value is NULL then type is virtual and has no representation in DB
     * @var array
     */
    static protected $typeToDbType = array(
        self::TYPE_INT => self::DB_TYPE_INT,
        self::TYPE_FLOAT => self::DB_TYPE_FLOAT,
        self::TYPE_BOOL => self::DB_TYPE_BOOL,
        self::TYPE_STRING => self::DB_TYPE_VARCHAR,
        self::TYPE_TEXT => self::DB_TYPE_TEXT,
        self::TYPE_JSON => self::DB_TYPE_TEXT,
        self::TYPE_SHA1 => self::DB_TYPE_VARCHAR,
        self::TYPE_MD5 => self::DB_TYPE_VARCHAR,
        self::TYPE_PASSWORD => self::DB_TYPE_VARCHAR,
        self::TYPE_EMAIL => self::DB_TYPE_VARCHAR,
        self::TYPE_TIMESTAMP => self::DB_TYPE_TIMESTAMP,
        self::TYPE_DATE => self::DB_TYPE_DATE,
        self::TYPE_TIME => self::DB_TYPE_TIME,
        self::TYPE_TIMEZONE_OFFSET => self::DB_TYPE_INT,
        self::TYPE_ENUM => self::DB_TYPE_VARCHAR,
        self::TYPE_IPV4_ADDRESS => self::DB_TYPE_IP_ADDRESS,
        self::TYPE_FILE => null,
        self::TYPE_IMAGE => null,
    );

    /**
     * Contains map where keys are column types and values are names of classes that extend DbObjectField class
     * Class names provided here used only for cusom data types registered via DbColumnConfig::registerType() method
     * Class names must be provided with namespace.
     * For hardcoded data types class names generated automatically. For example:
     * type = timezone_offset, class_name = TimezoneOffsetField
     * Namespace for hardcoded types provided in argument of method DbColumnConfig->getClassName()
     * @var array
     */
    static protected $typeToDbObjectFieldClass = array(

    );

    const DEFAULT_VALUE_NOT_SET = '___NOT_SET___';

    const ON_NONE = false;
    const ON_ALL = true;
    const ON_CREATE = 'create';
    const ON_UPDATE = 'update';

    // params that can be set directly or calculated
    /** @var DbTableConfig */
    protected $dbTableConfig;
    /** @var string */
    protected $name;
    /** @var string */
    protected $type;
    /** @var string */
    protected $dbType;
    /** @var int */
    protected $minLength = 0;
    /** @var int */
    protected $maxLength = 0;
    /** @var bool */
    protected $isNullable = true;
    /** @var mixed */
    protected $defaultValue = self::DEFAULT_VALUE_NOT_SET;
    /** @var array */
    protected $allowedValues = array();
    /**
     * self::ON_NONE - allows field to be 'null' (if $null == true), unset or empty string
     * self::ON_ALL - field is required for both creation and update
     * self::ON_CREATE - field is required only for creation
     * self::ON_UPDATE - field is required only for update
     * @var bool|string
     */
    protected $isRequired = self::ON_NONE;
    /** @var bool */
    protected $isPk = false;
    /** @var bool */
    protected $isUnique = false;
    /** @var bool */
    protected $isVirtual = false;
    /**
     * Value for this virtual column must be imported from another column if this option is string and already defined column
     * @var bool|string - false: don't import
     */
    protected $importVirtualColumnValueFrom = false;
    /**
     * self::ON_NONE - forced skip disabled
     * self::ON_ALL - forced skip enabled for any operation
     * self::ON_CREATE - forced skip enabled for record creation only
     * self::ON_UPDATE - forced skip enable for record update only
     * @var int
     */
    protected $isExcluded = self::ON_NONE;
    /** @var DbRelationConfig[] */
    protected $relations = array();

    // calculated params (not allowed to be set directly)
    /** @var bool */
    protected $isFile = false;
    /** @var bool */
    protected $isImage = false;
    /** @var bool */
    protected $isFk = false;
    /** @var array */
    protected $customData = array();
    /** @var array */
    protected $customValidators = array();

    // service params
    static public $fileTypes = array(
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    );

    static public $imageFileTypes = array(
        self::TYPE_IMAGE,
    );

    static protected $valueLengthOptionsAllowedForDbTypes = array(
        self::DB_TYPE_VARCHAR
    );

    static protected $valueLengthOptionsNotAllowedForTypes = array(
        self::TYPE_SHA1,
        self::TYPE_MD5,
        self::TYPE_IPV4_ADDRESS,
    );

    /**
     * @param string $name
     * @param string $type
     * @return $this
     */
    static public function create($name, $type) {
        $className = get_called_class();
        return new $className($name, $type);
    }

    /**
     * Add custom column type
     * @param string $type - name of type
     * @param string $dbType - name of type in DB (usually one of DbColumnConfig::DB_TYPE_*)
     * @param $dbObjectFieldClass - full name of class that extends DbObjectFieldClass
     */
    static public function registerType($type, $dbType, $dbObjectFieldClass) {
        $type = strtolower($type);
        self::$typeToDbType[$type] = strtolower($dbType);
        self::$typeToDbObjectFieldClass[$type] = $dbObjectFieldClass;
    }

    /**
     * @param string $name
     * @param string $type
     */
    public function __construct($name, $type) {
        $this->setName($name);
        $this->setType($type);
    }

    /**
     * @throws DbColumnConfigException
     */
    public function validateConfig() {

    }

    /**
     * @return DbTableConfig
     */
    public function getDbTableConfig() {
        return $this->dbTableConfig;
    }

    /**
     * @param DbTableConfig $dbTableConfig
     * @return $this
     */
    public function setDbTableConfig(DbTableConfig $dbTableConfig) {
        $this->dbTableConfig = $dbTableConfig;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     * @throws DbColumnConfigException
     */
    protected function setName($name) {
        $pattern = '^[a-zA-Z][a-zA-Z0-9_]*$';
        if (!preg_match("%^{$pattern}$%is", $name)) {
            throw new DbColumnConfigException($this, "Invalid DB column name [$name]. Column name pattern: $pattern");
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
     * @throws DbColumnConfigException
     */
    protected function setType($type) {
        $type = strtolower($type);
        if (!array_key_exists($type, self::$typeToDbType)) {
            throw new DbColumnConfigException($this, "Unknown column type [$type]");
        }
        $this->type = $type;
        $this->dbType = self::$typeToDbType[$this->type];
        if (in_array($type, self::$fileTypes)) {
            $this->setIsFile(true);
            $this->setIsVirtual(true);
            $this->setIsExcluded(true);
            if (in_array($type, self::$imageFileTypes)) {
                $this->setIsImage(true);
            }
        } else {
            $this->setIsFile(false);
            $this->setIsImage(false);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getDbType() {
        return $this->dbType;
    }

    /**
     * Get full name of class that extends DbObjectField class
     * @param string $defaultNamespace - namespace for hardcoded column types
     * @return string
     */
    public function getClassName($defaultNamespace) {
        if (isset(self::$typeToDbObjectFieldClass[$this->getType()])) {
            return self::$typeToDbObjectFieldClass[$this->getType()];
        } else {
            return rtrim($defaultNamespace, '\\') . '\\' . StringUtils::classify($this->getType()) . 'Field';
        }
    }

    /**
     * @return int
     */
    public function getMaxLength() {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength - 0 = unlimited
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setMaxLength($maxLength) {
        if (!in_array($this->getDbType(), self::$valueLengthOptionsAllowedForDbTypes)) {
            throw new DbColumnConfigException($this, "Max length option cannot be applied to column with type [{$this->getType()}]");
        }
        if (in_array($this->getType(), self::$valueLengthOptionsNotAllowedForTypes)) {
            throw new DbColumnConfigException($this, "Max length option value for column with type [{$this->getType()}] is fixed to [{$this->maxLength}]");
        }
        if (!ValidateValue::isInteger($maxLength, true) && $maxLength < 0) {
            throw new DbColumnConfigException($this, "Invalid value provided for max length: {$maxLength}");
        }
        if ($this->getMinLength() > 0 && $maxLength < $this->getMinLength()) {
            throw new DbColumnConfigException($this, "Max length [{$maxLength}] cannot be lower then min length [{$this->getMinLength()}]");
        }
        $this->maxLength = $maxLength;
        return $this;
    }

    /**
     * @return int
     */
    public function getMinLength() {
        return $this->maxLength;
    }

    /**
     * @param int $minLength - 0 = unlimited
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setMinLength($minLength) {
        if (!in_array($this->getDbType(), self::$valueLengthOptionsAllowedForDbTypes)) {
            throw new DbColumnConfigException($this, "Min length option cannot be applied to column with type [{$this->getType()}]");
        }
        if (in_array($this->getType(), self::$valueLengthOptionsNotAllowedForTypes)) {
            throw new DbColumnConfigException($this, "Min length option value for column with type [{$this->getType()}] is fixed to [{$this->maxLength}]");
        }
        if (!ValidateValue::isInteger($minLength, true) || $minLength < 0) {
            throw new DbColumnConfigException($this, "Invalid value provided for min length: {$minLength}");
        }
        if ($this->getMaxLength() > 0 && $minLength > $this->getMaxLength()) {
            throw new DbColumnConfigException($this, "Min length [{$minLength}] cannot be higher then max length [{$this->getMaxLength()}]");
        }
        $this->minLength = $minLength;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isNullable() {
        return $this->isNullable;
    }

    /**
     * @param boolean $isNullable
     * @return $this
     */
    public function setIsNullable($isNullable) {
        $this->isNullable = !!$isNullable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue() {
        return $this->defaultValue;
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
     * @return array
     */
    public function getAllowedValues() {
        return $this->allowedValues;
    }

    /**
     * @param array $allowedValues
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setAllowedValues($allowedValues) {
        if (!is_array($allowedValues) || empty($allowedValues)) {
            throw new DbColumnConfigException($this, 'Allowed values have to be not empty array');
        }
        $this->allowedValues = $allowedValues;
        return $this;
    }

    /**
     * @param string $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     * @throws DbColumnConfigException
     */
    public function isRequiredOn($action) {
        if (!is_string($action) || !in_array(strtolower($action), array(self::ON_UPDATE, self::ON_CREATE))) {
            throw new DbColumnConfigException($this, "Invalid argument \$forAction = [{$action}] passed to isRequired()");
        }
        return $this->isRequired === self::ON_ALL || $this->isRequired === strtolower($action);
    }

    /**
     * @return bool
     */
    public function isRequiredOnAnyAction() {
        return $this->isRequired !== self::ON_NONE;
    }

    /**
     * @return bool|string - one of self::ON_UPDATE, self::ON_CREATE, self::ON_ALL, self::ON_NONE
     */
    public function getIsRequired() {
        return $this->isRequired;
    }

    /**
     * @param int|bool $isRequired
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setIsRequired($isRequired) {
        if (is_bool($isRequired)) {
            $this->isRequired = $isRequired;
        } else if ($isRequired === 1 || $isRequired === '1' || $isRequired === 0 || $isRequired === '0') {
            $this->isRequired = intval($isRequired) === 1;
        } else if (in_array(strtolower($isRequired), array(self::ON_UPDATE, self::ON_CREATE))) {
            $this->isRequired = strtolower($isRequired);
        } else {
            throw new DbColumnConfigException($this, "Invalid value [$isRequired] passed to [setIsRequired]");
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function isPk() {
        return $this->isPk;
    }

    /**
     * @param boolean $isPk
     * @return $this
     */
    public function setIsPk($isPk) {
        $this->isPk = !!$isPk;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isUnique() {
        return $this->isUnique;
    }

    /**
     * @param boolean $isUnique
     * @return $this
     */
    public function setIsUnique($isUnique) {
        $this->isUnique = !!$isUnique;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isVirtual() {
        return $this->isVirtual;
    }

    /**
     * @return bool|string - false: don't import
     * @throws DbColumnConfigException
     */
    public function importVirtualColumnValueFrom() {
        if (!$this->dbTableConfig->hasColumn($this->importVirtualColumnValueFrom)) {
            throw new DbColumnConfigException($this, "Column [{$this->importVirtualColumnValueFrom}] is not defined");
        }
        return $this->importVirtualColumnValueFrom;
    }

    /**
     * @param string $columnName
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setImportVirtualColumnValueFrom($columnName) {
        if (!is_string($columnName)) {
            throw new DbColumnConfigException($this, "Argument \$columnName in setImportVirtualColumnValueFrom() must be a string. Passed value: [{$columnName}]");
        }
        if (!empty($this->dbTableConfig) && !$this->dbTableConfig->hasColumn($columnName)) {
            throw new DbColumnConfigException($this, "Column [{$columnName}] is not defined");
        }
        $this->importVirtualColumnValueFrom = $columnName;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontImportVirtualColumnValue() {
        $this->importVirtualColumnValueFrom = false;
        return $this;
    }

    /**
     * @param boolean $isVirtual
     * @return $this
     */
    public function setIsVirtual($isVirtual) {
        $this->isVirtual = !!$isVirtual;
        return $this;
    }

    /**
     * @param string $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     * @throws DbColumnConfigException
     */
    public function isExcludedOn($action) {
        if (!is_string($action) || !in_array(strtolower($action), array(self::ON_UPDATE, self::ON_CREATE))) {
            throw new DbColumnConfigException($this, "Invalid argument \$forAction = [{$action}] passed to isExcluded()");
        }
        return $this->isExcluded === self::ON_ALL || $this->isExcluded === strtolower($action);
    }

    /**
     * @return bool
     */
    public function isExcludedOnAnyAction() {
        return $this->isExcluded !== self::ON_NONE;
    }

    /**
     * @return bool|string - one of self::ON_UPDATE, self::ON_CREATE, self::ON_ALL, self::ON_NONE
     */
    public function getIsExcluded() {
        return $this->isExcluded;
    }

    /**
     * @param int|bool $isExcluded
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setIsExcluded($isExcluded) {
        if (is_bool($isExcluded)) {
            $this->isExcluded = $isExcluded;
        } else if ($isExcluded === 1 || $isExcluded === '1' || $isExcluded === 0 || $isExcluded === '0') {
            $this->isExcluded = intval($isExcluded) === 1;
        } else if (in_array(strtolower($isExcluded), array(self::ON_UPDATE, self::ON_CREATE))) {
            $this->isExcluded = strtolower($isExcluded);
        } else {
            throw new DbColumnConfigException($this, "Invalid value [$isExcluded] passed to [setIsExcluded]");
        }
        return $this;
    }

    /**
     * @return DbRelationConfig[]
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * @param string $relationName
     * @return DbRelationConfig
     * @throws DbColumnConfigException
     */
    public function getRelation($relationName) {
        if (empty($this->relations[$relationName])) {
            throw new DbColumnConfigException($this, "Relation $relationName not exists");
        }
        return $this->relations[$relationName];
    }

    /**
     * @param DbRelationConfig $relation
     * @param null $relationAlias
     * @return $this
     * @throws DbColumnConfigException
     */
    public function addRelation(DbRelationConfig $relation, $relationAlias = null) {
        if ($relation->getColumn() !== $this->name && $relation->getForeignColumn() !== $this->name) {
            throw new DbColumnConfigException($this, "Relation {$relation->getId()} is not connected to column {$this->name}");
        }
        if (empty($relationAlias)) {
            $relationAlias = $relation->getId();
        }
        if (!empty($this->relations[$relation->getId()])) {
            throw new DbColumnConfigException($this, "Relation {$relationAlias} already defined");
        }
        $this->relations[$relationAlias] = $relation;
        if ($relation->getType() === DbRelationConfig::BELONGS_TO) {
            $this->setIsFk(true);
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function isFile() {
        return $this->isFile;
    }

    /**
     * @param boolean $isFile
     */
    protected function setIsFile($isFile) {
        $this->isFile = $isFile;
    }

    /**
     * @return boolean
     */
    public function isImage() {
        return $this->isImage;
    }

    /**
     * @param boolean $isImage
     */
    protected function setIsImage($isImage) {
        $this->isImage = $isImage;
    }

    /**
     * @return boolean
     */
    public function isFk() {
        return $this->isFk;
    }

    /**
     * @param boolean $isFk
     */
    protected function setIsFk($isFk) {
        $this->isFk = $isFk;
    }

    /**
     * Is this column exists in DB?
     * @return bool
     */
    public function isExistsInDb() {
        return !$this->isVirtual();
    }

    /**
     * @param null|string $key
     * @return array|null
     */
    public function getCustomData($key = null) {
        if (empty($key)) {
            return $this->customData;
        } else if (is_array($this->customData) && array_key_exists($key, $this->customData)) {
            return $this->customData[$key];
        } else {
            return null;
        }
    }

    /**
     * @param array $customData
     * @return $this
     */
    public function customData($customData) {
        $this->customData = $customData;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomValidators() {
        return $this->customValidators;
    }

    /**
     * @param array $customValidators - should contain only callable values
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setCustomValidators($customValidators) {
        if (!is_array($customValidators)) {
            throw new DbColumnConfigException($this, '$customValidators arg should be an array');
        }
        foreach ($customValidators as $validator) {
            if (!is_callable($validator)) {
                throw new DbColumnConfigException($this, '$customValidators should contain only functions');
            }
        }
        $this->customValidators = $customValidators;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasCustomValidators() {
        return !empty($this->customValidators);
    }

}