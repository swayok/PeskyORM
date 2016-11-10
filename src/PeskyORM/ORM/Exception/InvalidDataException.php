<?php

namespace PeskyORM\ORM\Exception;

class InvalidDataException extends OrmException {

    protected $errors = [];

    public function __construct(array $errors) {
        $message = [];
        foreach ($errors as $key => $error) {
            $errorMsg = '';
            if (!is_int($key)) {
                $errorMsg = '[' . $key . '] ';
            }
            if (is_array($error)) {
                $error = implode(', ', $error);
            }
            $errorMsg .= $error;
            $message[] = $errorMsg;
        }
        parent::__construct(static::MESSAGE_INVALID_DATA . implode('; ', $message), static::CODE_INVALID_DATA);
        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

}