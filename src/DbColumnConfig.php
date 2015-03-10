<?php

namespace ORM;

use ORM\Exception\DbConfigException;

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
    const TYPE_IP_ADDRESS = 'ip';
    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';

    const DEFAULT_VALUE_NOT_SET = '___NOT_SET___';

    const ON_NONE = 0;
    const ON_ALL = 1;
    const ON_CREATE = 2;
    const ON_UPDATE = 3;

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
     * @var int
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
     */
    protected function setName($name) {
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
        $this->type = $type;
        // todo: analyze type and setup computed fields
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxLength() {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength
     * @return $this
     */
    public function setMaxLength($maxLength) {
        $this->maxLength = $maxLength;
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
     * @throws DbConfigException
     */
    public function setAllowedValues($allowedValues) {
        if (!is_array($allowedValues) || empty($allowedValues)) {
            throw new DbConfigException($this, 'Allowed values have to be not empty array');
        }
        $this->allowedValues = $allowedValues;
        return $this;
    }

    /**
     * @return int
     */
    public function isRequired() {
        return $this->isRequired;
    }

    /**
     * @param int|bool $isRequired
     * @return $this
     * @throws DbConfigException
     */
    public function setIsRequired($isRequired) {
        if (is_bool($isRequired)) {
            $this->isRequired = $isRequired ? 1 : 0;
        } else if ($isRequired >= self::ON_NONE && $isRequired <= self::ON_UPDATE) {
            $this->isRequired = $isRequired;
        } else {
            throw new DbConfigException($this, "Invalid value [$isRequired] passed to [setIsRequired]");
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
     * @return int
     */
    public function isExcluded() {
        return $this->isExcluded;
    }

    /**
     * @param int|bool $isExcluded
     * @return $this
     * @throws DbConfigException
     */
    public function setIsExcluded($isExcluded) {
        if (is_bool($isExcluded)) {
            $this->isExcluded = $isExcluded ? 1 : 0;
        } else if ($isExcluded >= self::ON_NONE && $isExcluded <= self::ON_UPDATE) {
            $this->isExcluded = $isExcluded;
        } else {
            throw new DbConfigException($this, "Invalid value [$isExcluded] passed to [setIsExcluded]");
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
     * @throws DbConfigException
     */
    public function getRelation($relationName) {
        if (empty($this->relations[$relationName])) {
            throw new DbConfigException($this, "Relation $relationName not exists");
        }
        return $this->relations[$relationName];
    }

    /**
     * @param string $relationName
     * @param DbRelationConfig $relation
     * @return $this
     * @throws DbConfigException
     */
    public function addRelation($relationName, DbRelationConfig $relation) {
        $relationInfo = $relation->getTable() . '.' . $relation->getColumn();
        if ($relation->getColumn() !== $this->name && $relation->getForeignColumn() !== $this->name) {
            throw new DbConfigException($this, "Relation $relationName to $relationInfo is not connected to column {$this->name}");
        }
        if (!empty($this->relations[$relationName])) {
            throw new DbConfigException($this, "Relation $relationName to $relationInfo already defined");
        }
        $this->relations[$relationName] = $relation;
        if ($relation->getType() === DbRelationConfig::BELONGS_TO) {
            $this->isFk = true;
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
     * @return boolean
     */
    public function isImage() {
        return $this->isImage;
    }

    /**
     * @return boolean
     */
    public function isFk() {
        return $this->isFk;
    }

}