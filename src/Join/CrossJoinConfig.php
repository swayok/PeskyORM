<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\DbExpr;
use PeskyORM\Utils\ArgumentValidators;

/**
 * @see https://www.postgresql.org/docs/current/queries-table-expressions.html#QUERIES-JOIN
 */
class CrossJoinConfig implements CrossJoinConfigInterface
{
    protected string $joinName;
    protected DbExpr $joinQuery;

    public function __construct(string $joinName, DbExpr $joinQuery)
    {
        $this->setJoinName($joinName)
            ->setJoinQuery($joinQuery);
    }

    public function getJoinName(): ?string
    {
        return $this->joinName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setJoinName(string $joinName): static
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

    protected function setJoinQuery(DbExpr $joinQuery): static
    {
        $joinQuery->setWrapInBrackets(false);
        $this->joinQuery = $joinQuery;
        return $this;
    }

    public function isValid(): bool
    {
        return (
            $this->joinName
            && $this->joinQuery
        );
    }

    public function getJoinType(): ?string
    {
        return self::JOIN_CROSS;
    }
}
