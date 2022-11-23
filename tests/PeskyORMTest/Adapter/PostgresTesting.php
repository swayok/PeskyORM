<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Adapter;

use PeskyORM\Adapter\Postgres;
use PeskyORM\DbExpr;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\DbAdapterMethodArgumentUtils;

class PostgresTesting extends Postgres
{

    public function guardPkNameArg(string $pkName): void
    {
        DbAdapterMethodArgumentUtils::guardPkNameArg($this, $pkName);
    }
    
    public function isValidJsonSelector(string $name): bool
    {
        return parent::isValidJsonSelector($name);
    }

    public function normalizeConditionOperator(
        string $operator,
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value
    ): string {
        return parent::normalizeConditionOperator($operator, $value);
    }

    public function assembleConditionValue(
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value,
        string $normalizedOperator,
        bool $valueAlreadyQuoted = false
    ): string {
        return parent::assembleConditionValue($value, $normalizedOperator, $valueAlreadyQuoted);
    }

    public function convertNormalizedConditionOperatorForDbQuery(string $normalizedOperator): string
    {
        return parent::convertNormalizedConditionOperatorForDbQuery($normalizedOperator);
    }
}