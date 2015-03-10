<?php

namespace PeskyORM\Exception;

use PeskyORM\DbField;

class DbFieldException extends DbObjectException {

    protected $dbField;

    public function __construct(DbField $dbField, $message) {
        $this->dbField = $dbField;
        parent::__construct($dbField->getDbObject(), $message);
    }

    /**
     * @return DbField
     */
    public function getDbField() {
        return $this->dbField;
    }


}