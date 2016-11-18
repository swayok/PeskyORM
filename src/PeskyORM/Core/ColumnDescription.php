<?php

namespace PeskyORM\Core;

use PeskyORM\ORM\Column;

class ColumnDescription implements \Serializable {

    const NOT_SET = '___NOT_SET__';

    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $dbType;
    /**
     * @var string
     */
    protected $ormType;
    /**
     * @var integer
     */
    protected $limit;
    /**
     * @var integer
     */
    protected $numberPrecision;
    /**
     * @var bool
     */
    protected $isNullable = true;
    /**
     * @var mixed
     */
    protected $default = self::NOT_SET;
    /**
     * @var bool
     */
    protected $isPrimaryKey = false;
    /**
     * @var bool
     */
    protected $isForeignKey = false;
    /**
     * @var bool
     */
    protected $isUnique = false;

    /**
     * @param string $name
     * @param string $dbType
     * @param string $ormType
     */
    public function __construct($name, $dbType, $ormType) {
        $this->name = $name;
        $this->dbType = $dbType;
        $this->ormType = $ormType;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDbType() {
        return $this->dbType;
    }

    /**
     * @return string
     */
    public function getOrmType() {
        return $this->ormType;
    }

    /**
     * @param int|null $limit
     * @param int|null $numberPrecision
     * @return $this
     */
    public function setLimitAndPrecision($limit, $numberPrecision = null) {
        $this->limit = $limit ? (int)$limit : null;
        if ($this->getOrmType() === Column::TYPE_FLOAT) {
            $this->numberPrecision = $numberPrecision ? (int)$numberPrecision : null;
        }
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLimit() {
        return $this->limit;
    }

    /**
     * @return int|null
     */
    public function getNumberPrecision() {
        return $this->numberPrecision;
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
        $this->isNullable = (bool)$isNullable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDefault() {
        return $this->default;
    }

    /**
     * @param mixed $default
     * @return $this
     */
    public function setDefault($default) {
        $this->default = $default;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isPrimaryKey() {
        return $this->isPrimaryKey;
    }

    /**
     * @param boolean $isPrimaryKey
     * @return $this
     */
    public function setIsPrimaryKey($isPrimaryKey) {
        $this->isPrimaryKey = (bool)$isPrimaryKey;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isForeignKey() {
        return $this->isForeignKey;
    }

    /**
     * @param boolean $isForeignKey
     * @return $this
     */
    public function setIsForeignKey($isForeignKey) {
        $this->isForeignKey = (bool)$isForeignKey;
        return $this;
    }

    /**
     * @return mixed
     */
    public function isUnique() {
        return $this->isUnique;
    }

    /**
     * @param mixed $isUnique
     * @return $this
     */
    public function setIsUnique($isUnique) {
        $this->isUnique = (bool)$isUnique;
        return $this;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize() {
        return json_encode(get_object_vars($this));
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @throws \InvalidArgumentException
     * @since 5.1.0
     */
    public function unserialize($serialized) {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
    }

}