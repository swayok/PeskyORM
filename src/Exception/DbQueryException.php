<?php

namespace ORM\Exception;

use ORM\DbQuery;

class DbQueryException extends DbException {

    protected $dbQuery;

    public function __construct(DbQuery $dbQuery, $message) {
        $this->dbQuery = $dbQuery;
        parent::__construct($dbQuery->getDb(), $message);
    }

    /**
     * @return DbQuery
     */
    public function getDbQuery() {
        return $this->dbQuery;
    }


}