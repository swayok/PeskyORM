<?php


namespace PeskyORM\Exception;


use PeskyORM\DbObject;

class DbObjectValidationException extends DbObjectException {

    private $validationErrors = array();

    /**
     * @param DbObject $dbObject
     * @param null|array $validationErrors
     */
    public function __construct(DbObject $dbObject, $validationErrors = null) {
        $this->validationErrors = empty($validationErrors) ? $dbObject->getValidationErrors() : $validationErrors;
        parent::__construct($dbObject, 'Validation errors: ' . json_encode($this->validationErrors, JSON_UNESCAPED_UNICODE), DbExceptionCode::VALIDATION_ERRORS);
    }

    /**
     * @return array
     */
    public function getValidationErrors() {
        return $this->validationErrors;
    }




}