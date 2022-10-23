<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Adapter;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Core\DbExpr;

class MysqlTesting extends Mysql
{
    
    public function guardTableNameArg(string $table): void
    {
        parent::guardTableNameArg($table);
    }
    
    public function guardConditionsArg(DbExpr|string $conditions): void
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
    
    public function guardColumnsArg(array $columns, bool $allowDbExpr = true): void
    {
        parent::guardColumnsArg($columns, $allowDbExpr);
    }
    
    public function guardTransaction(string $action): void
    {
        parent::guardTransaction($action);
    }
    
    public function quoteJsonSelectorValue(string $key): string
    {
        return parent::quoteJsonSelectorValue($key);
    }
    
    public function quoteJsonSelectorExpression(array $sequence): string
    {
        return parent::quoteJsonSelectorExpression($sequence);
    }
}