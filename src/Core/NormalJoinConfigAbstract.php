<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\Core\Utils\DbAdapterMethodArgumentUtils;

abstract class NormalJoinConfigAbstract implements NormalJoinConfigInterface
{
    protected string $joinName;
    protected ?string $tableName = null;
    protected ?string $tableSchema = null;
    protected ?string $tableAlias = null;
    protected ?string $columnName = null;
    protected ?string $joinType = null;
    protected ?string $foreignTableName = null;
    protected ?string $foreignTableSchema = null;
    protected ?string $foreignColumnName = null;
    protected array $additionalJoinConditions = [];
    protected array $foreignColumnsToSelect = ['*'];

    public function __construct(string $joinName)
    {
        $this->setJoinName($joinName);
    }

    /**
     * Get source table column name
     */
    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    /**
     * Set source table column name
     * @throws \InvalidArgumentException
     */
    public function setColumnName(string $columnName): static
    {
        if (empty($columnName)) {
            throw new \InvalidArgumentException('$columnName argument must be a not-empty string');
        }
        $this->columnName = $columnName;
        return $this;
    }

    /**
     * Get foreign table column name
     */
    public function getForeignColumnName(): ?string
    {
        return $this->foreignColumnName;
    }

    /**
     * Set foreign table column name
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnName(string $foreignColumnName): static
    {
        if (empty($foreignColumnName)) {
            throw new \InvalidArgumentException('$foreignColumnName argument must be a not-empty string');
        }
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }

    /**
     * @return string|null
     */
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
     * Set name that will be used in SQL query to address joined table columns
     * Example: INNER JOIN foreign_table_schema.foreign_table_name as ForeignTableAlias ON ($conditions) AS $joinName
     * @throws \InvalidArgumentException
     */
    public function setJoinName(string $joinName): static
    {
        if (empty($joinName)) {
            throw new \InvalidArgumentException('$joinName argument must be a not-empty string');
        }

        if (!preg_match(static::NAME_VALIDATION_REGEXP, $joinName)) {
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
     * @param string $joinType - one of self::JOIN_*
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType): static
    {
        if (empty($joinType)) {
            throw new \InvalidArgumentException('$joinType argument must be a not-empty string');
        }

        $joinType = strtolower($joinType);

        $joinTypes = $this->getJoinTypes();
        if (!in_array($joinType, $joinTypes, true)) {
            throw new \InvalidArgumentException(
                '$joinType argument must be one of: ' . implode(',', $joinTypes)
            );
        }

        $this->joinType = $joinType;
        return $this;
    }

    protected function getJoinTypes(): array
    {
        return [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT, self::JOIN_FULL];
    }

    /**
     * Get source table name
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    protected function hasTableName(): bool
    {
        return !empty($this->tableName);
    }

    /**
     * Get source table schema
     */
    public function getTableSchema(): ?string
    {
        return $this->tableSchema;
    }

    /**
     * Get source table alias
     */
    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }

    /**
     * Set source table alias
     * For example when "table_join_name AS $tableAlias" is used
     * @throws \InvalidArgumentException
     */
    public function setTableAlias(string $alias): static
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
     * Add more join conditions.
     * By default, join adds only one condition:
     * "ON LocalTableAlias.local_column_name = ForeignTableAlias.foreign_column_name".
     * This way you can add more conditions to JOIN.
     */
    public function setAdditionalJoinConditions(array $conditions): static
    {
        $this->additionalJoinConditions = $conditions;
        return $this;
    }

    public function getForeignColumnsToSelect(): array
    {
        return $this->foreignColumnsToSelect;
    }

    /**
     * @param array $columns - use '*' or ['*'] to select all columns and empty array to select none
     */
    public function setForeignColumnsToSelect(...$columns): static
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        DbAdapterMethodArgumentUtils::guardColumnsListArg($columns, true, true);
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
