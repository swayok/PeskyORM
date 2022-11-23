<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\DbExpr;
use PeskyORM\Utils\ArgumentValidators;

class CrossJoinConfig implements CrossJoinConfigInterface
{
    protected string $joinName;
    protected DbExpr $joinQuery;

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
        ArgumentValidators::assertNotEmpty('$joinName', $joinName);
        ArgumentValidators::assertPascalCase('$joinName', $joinName);
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
