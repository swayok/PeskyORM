<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;
use PeskyORM\Core\DbExpr;

class CrossJoinInfo extends AbstractJoinInfo {

    protected $joinQuery;

    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function create(string $joinName, DbExpr $joinQuery) {
        return new static($joinName, $joinQuery);
    }

    public function __construct(string $joinName, DbExpr $joinQuery) {
        parent::__construct($joinName);
        $joinQuery->setWrapInBrackets(false);
        $this->joinQuery = $joinQuery;
    }

    public function setForeignColumnsToSelect(...$columns) {
        return $this;
    }
    
    public function getJoinQuery(): DbExpr {
        return $this->joinQuery;
    }
    
    public function isValid() {
        return true;
    }
}
