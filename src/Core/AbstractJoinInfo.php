<?php

declare(strict_types=1);

namespace PeskyORM\Core;

abstract class AbstractJoinInfo
{
    
    public const JOIN_LEFT = 'left';
    public const JOIN_RIGHT = 'right';
    public const JOIN_INNER = 'inner';
    
    public const NAME_VALIDATION_REGEXP = '%^[A-Z][a-zA-Z0-9]*$%';   //< CamelCase
    
    /** @var string */
    protected $joinName;
    /** @var string */
    protected $tableName;
    /** @var string|null */
    protected $tableSchema;
    /** @var string|null */
    protected $tableAlias;
    /** @var string */
    protected $columnName;
    /** @var string */
    protected $joinType;
    /** @var string */
    protected $foreignTableName;
    /** @var string|null */
    protected $foreignTableSchema;
    /** @var string */
    protected $foreignColumnName;
    /** @var array */
    protected $additionalJoinConditions = [];
    /** @var array */
    protected $foreignColumnsToSelect = ['*'];
    
    public function __construct(string $joinName)
    {
        $this->setJoinName($joinName);
    }
    
    public function getColumnName(): ?string
    {
        return $this->columnName;
    }
    
    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setColumnName(string $columnName)
    {
        if (empty($columnName)) {
            throw new \InvalidArgumentException('$columnName argument must be a not-empty string');
        }
        $this->columnName = $columnName;
        return $this;
    }
    
    public function getForeignColumnName(): ?string
    {
        return $this->foreignColumnName;
    }
    
    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnName(string $foreignColumnName)
    {
        if (empty($foreignColumnName)) {
            throw new \InvalidArgumentException('$foreignColumnName argument must be a not-empty string');
        }
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }
    
    public function getForeignTableName(): ?string
    {
        return $this->foreignTableName;
    }
    
    public function getForeignTableSchema(): ?string
    {
        return $this->foreignTableSchema;
    }
    
    public function getJoinName(): ?string
    {
        return $this->joinName;
    }
    
    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinName(string $joinName)
    {
        if (empty($joinName)) {
            throw new \InvalidArgumentException('$joinName argument must be a not-empty string');
        } elseif (!preg_match(static::NAME_VALIDATION_REGEXP, $joinName)) {
            throw new \InvalidArgumentException(
                "\$joinName argument contains invalid value: '$joinName'. Pattern: "
                . static::NAME_VALIDATION_REGEXP . '. Example: CamelCase1'
            );
        }
        $this->joinName = $joinName;
        return $this;
    }
    
    public function getJoinType(): ?string
    {
        return $this->joinType;
    }
    
    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType)
    {
        if (empty($joinType)) {
            throw new \InvalidArgumentException('$joinType argument must be a not-empty string');
        } elseif (!in_array(strtolower($joinType), [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT], true)) {
            throw new \InvalidArgumentException(
                '$joinType argument must be one of: ' . implode(',', [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT])
            );
        }
        $this->joinType = strtolower($joinType);
        return $this;
    }
    
    public function getTableName(): ?string
    {
        return $this->tableName;
    }
    
    protected function hasTableName(): bool
    {
        return !empty($this->tableName);
    }
    
    public function getTableSchema(): ?string
    {
        return $this->tableSchema;
    }
    
    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }
    
    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableAlias(string $alias)
    {
        if (empty($alias)) {
            throw new \InvalidArgumentException('$alias argument must be a not-empty string');
        }
        $this->tableAlias = $alias;
        return $this;
    }
    
    public function getAdditionalJoinConditions(): array
    {
        return $this->additionalJoinConditions;
    }
    
    /**
     * @return $this
     */
    public function setAdditionalJoinConditions(array $additionalJoinConditions)
    {
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }
    
    public function getForeignColumnsToSelect(): array
    {
        return $this->foreignColumnsToSelect;
    }
    
    /**
     * @param array $columns - use '*' or ['*'] to select all columns and empty array to select none
     * @return $this
     */
    public function setForeignColumnsToSelect(...$columns)
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $this->foreignColumnsToSelect = $columns;
        return $this;
    }
    
    public function isValid(): bool
    {
        return (
            $this->hasTableName()
            && $this->getColumnName()
            && $this->getForeignTableName()
            && $this->getForeignColumnName()
            && $this->getJoinType()
            && $this->getJoinName()
        );
    }
    
}
