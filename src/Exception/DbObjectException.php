<?php

namespace PeskyORM\Exception;

use PeskyORM\DbObject;

class DbObjectException extends DbModelException {

    protected $dbObject;

    public function __construct(DbObject $dbObject, $message, $errorCode = null) {
        $this->dbObject = $dbObject;
        parent::__construct($dbObject->_getModel(), $message, $errorCode);
    }

    /**
     * @return DbObject
     */
    public function getDbObject() {
        return $this->dbObject;
    }


}