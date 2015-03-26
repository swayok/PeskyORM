<?php

namespace PeskyORM\Exception;

use PeskyORM\DbQuery;

class DbQueryException extends DbException {

    protected $dbQuery;

    public function __construct(DbQuery $dbQuery, $message, $errorCode = null) {
        $this->dbQuery = $dbQuery;
        parent::__construct($dbQuery->getDb(), $message, $errorCode);
    }

    /**
     * @return DbQuery
     */
    public function getDbQuery() {
        return $this->dbQuery;
    }


}