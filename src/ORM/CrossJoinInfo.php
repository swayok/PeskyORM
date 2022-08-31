<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;
use PeskyORM\Core\DbExpr;

class CrossJoinInfo extends AbstractJoinInfo
{
    
    protected DbExpr $joinQuery;
    
    public static function create(string $joinName, DbExpr $joinQuery): static
    {
        return new static($joinName, $joinQuery);
    }
    
    public function __construct(string $joinName, DbExpr $joinQuery)
    {
        parent::__construct($joinName);
        $joinQuery->setWrapInBrackets(false);
        $this->joinQuery = $joinQuery;
    }
    
    public function setForeignColumnsToSelect(...$columns): static
    {
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
}
