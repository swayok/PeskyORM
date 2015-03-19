<?php

namespace ORM;

use ORM\Exception\DbColumnConfigException;
use ORM\Exception\DbTableConfigException;
use PeskyORM\DbObject;

class DbColumnConfig {

    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOL = 'boolean';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
    const TYPE_DB_ENTITY_NAME = 'db_entity_name';
    const TYPE_SHA1 = 'sha1';
    const TYPE_MD5 = 'md5';
    const TYPE_EMAIL = 'email';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_TIMEZONE_OFFSET = 'timezone_offset';
    const TYPE_ENUM = 'enum';
    const TYPE_IPV4_ADDRESS = 'ip';
    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';

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

    // service params
    static public $fileTypes = array(
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    );

    static public $imageFileTypes = array(
        self::TYPE_IMAGE,
    );

    static protected $maxLengthAllowedFor = array(
        self::TYPE_STRING,
        self::TYPE_SHA1,
        self::TYPE_MD5,
        self::TYPE_EMAIL,
        self::TYPE_ENUM,
    );

    static protected $maxLengthForcedValues = array(
        self::TYPE_SHA1 => 40,
        self::TYPE_MD5 => 32,
    );

    /**
     * @param string $name
     * @param string $type
     * @return DbColumnConfig
     */
    static public function create($name, $type) {
        $className = get_called_class();
        return new $className($name, $type);
    }

    /**
     * @param string $name
     * @param string $type
     */
    public function __construct($name, $type) {
        $this->setName($name);
        $this->setType($name);
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
     */
    protected function setType($type) {
        $this->type = strtolower($type);
        if (in_array($type, self::$fileTypes)) {
            $this->setIsFile(true);
            $this->setIsVirtual(true);
            $this->setIsExcluded(true);
            if (in_array($type, self::$imageFileTypes)) {
                $this->setIsImage(true);
            }
        } else if (in_array($type, self::$maxLengthForcedValues)) {
            $this->maxLength = self::$maxLengthForcedValues[$type];
        }
        return $this;
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
        if (!is_int($maxLength) && !preg_match('%^\d+$%', $maxLength) || intval($maxLength) < 0) {
            throw new DbColumnConfigException($this, "Invalid value provided for max length: {$maxLength}");
        }
        if (!in_array($this->getType(), self::$maxLengthAllowedFor)) {
            throw new DbColumnConfigException($this, "Max length option cannot be applied to column with type [{$this->getType()}]");
        } else if (in_array($this->getType(), self::$maxLengthForcedValues)) {
            throw new DbColumnConfigException($this, "Max length option value for column with type [{$this->getType()}] is fixed to [{$this->maxLength}]");
        }
        $this->maxLength = intval($maxLength);
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
     * @return $this
     * @throws DbColumnConfigException
     */
    public function addRelation(DbRelationConfig $relation) {
        if ($relation->getColumn() !== $this->name && $relation->getForeignColumn() !== $this->name) {
            throw new DbColumnConfigException($this, "Relation {$relation->getId()} is not connected to column {$this->name}");
        }
        if (!empty($this->relations[$relation->getId()])) {
            throw new DbColumnConfigException($this, "Relation {$relation->getId()} already defined");
        }
        $this->relations[$relation->getId()] = $relation;
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

}