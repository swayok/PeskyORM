<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;
use PeskyORM\Core\DbExpr;

class CrossJoinInfo extends AbstractJoinInfo
{
    
    protected DbExpr $joinQuery;
    
    /**
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create(string $joinName, DbExpr $joinQuery)
    {
        return new static($joinName, $joinQuery);
    }
    
    public function __construct(string $joinName, DbExpr $joinQuery)
    {
        parent::__construct($joinName);
        $joinQuery->setWrapInBrackets(false);
        $this->joinQuery = $joinQuery;
    }
    
    /**
     * @return static
     */
    public function setForeignColumnsToSelect(...$columns)
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
