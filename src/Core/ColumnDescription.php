<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\ORM\Column;

class ColumnDescription implements \Serializable
{
    
    protected string $name;
    protected string $dbType;
    protected string $ormType;
    
    protected ?int $limit = null;
    protected ?int $numberPrecision = null;
    protected bool $isNullable = true;
    protected mixed $default = null;
    protected bool $isPrimaryKey = false;
    protected bool $isForeignKey = false;
    protected bool $isUnique = false;
    
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
    
    public function setLimitAndPrecision(?int $limit, ?int $numberPrecision = null): static
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
    
    public function setIsNullable(bool $isNullable): static
    {
        $this->isNullable = $isNullable;
        return $this;
    }
    
    public function getDefault(): DbExpr|float|bool|int|string|null
    {
        return $this->default;
    }
    
    public function setDefault(DbExpr|float|bool|int|string|null $default): static
    {
        $this->default = $default;
        return $this;
    }
    
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }
    
    public function setIsPrimaryKey(bool $isPrimaryKey): static
    {
        $this->isPrimaryKey = $isPrimaryKey;
        return $this;
    }
    
    public function isForeignKey(): bool
    {
        return $this->isForeignKey;
    }
    
    public function setIsForeignKey(bool $isForeignKey): static
    {
        $this->isForeignKey = $isForeignKey;
        return $this;
    }
    
    public function isUnique(): bool
    {
        return $this->isUnique;
    }
    
    public function setIsUnique(bool $isUnique): static
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
    public function serialize(): string
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
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
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function unserialize(string $serialized): void
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