<?php

namespace PeskyORM\ORM\Exception;

class InvalidDataException extends OrmException {

    protected $errors = [];

    public function __construct(array $errors) {
        parent::__construct(static::MESSAGE_INVALID_DATA, static::CODE_INVALID_DATA);
        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

}