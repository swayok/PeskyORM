<?php

namespace PeskyORM\Exception;

use PeskyORM\DbObjectField;

class DbFieldException extends DbObjectException {

    protected $dbField;

    public function __construct(DbObjectField $dbField, $message) {
        $this->dbField = $dbField;
        parent::__construct($dbField->getDbObject(), $message);
    }

    /**
     * @return DbObjectField
     */
    public function getDbField() {
        return $this->dbField;
    }


}