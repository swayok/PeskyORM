<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\ORM\Column;

class ColumnDescription implements \Serializable
{
    
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
    protected $default = null;
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
    
    public function __construct(string $name, string $dbType, string $ormType)
    {
        $this->name = $name;
        $this->dbType = $dbType;
        $this->ormType = $ormType;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDbType(): string
    {
        return $this->dbType;
    }
    
    public function getOrmType(): string
    {
        return $this->ormType;
    }
    
    /**
     * @param int|null $limit
     * @param int|null $numberPrecision
     * @return $this
     */
    public function setLimitAndPrecision(?int $limit, ?int $numberPrecision = null)
    {
        $this->limit = $limit;
        if ($this->getOrmType() === Column::TYPE_FLOAT) {
            $this->numberPrecision = $numberPrecision;
        }
        return $this;
    }
    
    public function getLimit(): ?int
    {
        return $this->limit;
    }
    
    public function getNumberPrecision(): ?int
    {
        return $this->numberPrecision;
    }
    
    public function isNullable(): bool
    {
        return $this->isNullable;
    }
    
    /**
     * @param boolean $isNullable
     * @return $this
     */
    public function setIsNullable(bool $isNullable)
    {
        $this->isNullable = $isNullable;
        return $this;
    }
    
    /**
     * @return string|int|float|bool|DbExpr|null
     */
    public function getDefault()
    {
        return $this->default;
    }
    
    /**
     * @param string|int|float|bool|DbExpr|null $default
     * @return $this
     */
    public function setDefault($default)
    {
        $this->default = $default;
        return $this;
    }
    
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }
    
    /**
     * @param boolean $isPrimaryKey
     * @return $this
     */
    public function setIsPrimaryKey(bool $isPrimaryKey)
    {
        $this->isPrimaryKey = $isPrimaryKey;
        return $this;
    }
    
    public function isForeignKey(): bool
    {
        return $this->isForeignKey;
    }
    
    /**
     * @param boolean $isForeignKey
     * @return $this
     */
    public function setIsForeignKey(bool $isForeignKey)
    {
        $this->isForeignKey = $isForeignKey;
        return $this;
    }
    
    public function isUnique(): bool
    {
        return $this->isUnique;
    }
    
    /**
     * @param bool $isUnique
     * @return $this
     */
    public function setIsUnique(bool $isUnique)
    {
        $this->isUnique = $isUnique;
        return $this;
    }
    
    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
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
    public function unserialize($serialized)
    {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
    }
    
}