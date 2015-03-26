<?php

namespace PeskyORM\Exception;

use PeskyORM\DbObjectField;

class DbFieldException extends DbObjectException {

    protected $dbField;

    public function __construct(DbObjectField $dbField, $message, $errorCode = null) {
        $this->dbField = $dbField;
        parent::__construct($dbField->getDbObject(), $message, $errorCode);
    }

    /**
     * @return DbObjectField
     */
    public function getDbField() {
        return $this->dbField;
    }


}