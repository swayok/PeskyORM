<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Adapter;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\RecordInterface;

class PostgresTesting extends Postgres
{
    
    public function guardTableNameArg(string $table): void
    {
        parent::guardTableNameArg($table);
    }
    
    public function guardConditionsArg(DbExpr|array $conditions): void
    {
        parent::guardConditionsArg($conditions);
    }
    
    public function guardReturningArg(bool|array $returning): void
    {
        parent::guardReturningArg($returning);
    }
    
    public function guardPkNameArg(string $pkName): void
    {
        parent::guardPkNameArg($pkName);
    }
    
    public function guardDataArg(array $data): void
    {
        parent::guardDataArg($data);
    }
    
    public function guardColumnsListArg(array $columns, bool $allowDbExpr = true): void
    {
        parent::guardColumnsListArg($columns, $allowDbExpr);
    }
    
    public function quoteJsonSelectorExpression(array $sequence): string
    {
        return parent::quoteJsonSelectorExpression($sequence);
    }

    public function isValidJsonSelector(string $name): bool
    {
        return parent::isValidJsonSelector($name);
    }

    public function normalizeConditionOperator(
        string $operator,
        DbExpr|float|RecordInterface|int|bool|array|string|AbstractSelect|null $value
    ): string {
        return parent::normalizeConditionOperator($operator, $value);
    }

    public function assembleConditionValue(
        DbExpr|float|RecordInterface|int|bool|array|string|AbstractSelect|null $value,
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