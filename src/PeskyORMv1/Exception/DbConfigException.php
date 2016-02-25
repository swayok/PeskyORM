<?php

namespace PeskyORM\Exception;

class DbConfigException extends \Exception {

    /** @var null|string|int */
    private $errorCode = null;

    public function __construct($message, $errorCode = null) {
        $this->errorCode = $errorCode;
        parent::__construct($message, 500);
    }

    /**
     * @return int|null|string
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

}