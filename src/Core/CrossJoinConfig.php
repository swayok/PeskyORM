<?php

declare(strict_types=1);

namespace PeskyORM\Core;

class CrossJoinConfig implements CrossJoinConfigInterface
{
    protected string $joinName;
    protected DbExpr $joinQuery;

    public static function create(string $joinName, DbExpr $joinQuery): static
    {
        return new static($joinName, $joinQuery);
    }

    public function __construct(string $joinName, DbExpr $joinQuery)
    {
        $this->joinName = $joinName;
        $joinQuery->setWrapInBrackets(false);
        $this->joinQuery = $joinQuery;
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

    public function getJoinQuery(): DbExpr
    {
        return $this->joinQuery;
    }

    public function isValid(): bool
    {
        return true;
    }

    public function getJoinType(): ?string
    {
        return self::JOIN_CROSS;
    }
}
