<?php

namespace PeskyORM\Exception;

use PeskyORM\Db;

class DbException extends \Exception {

    /** @var Db */
    protected $db;
    /** @var null|string|int */
    protected $errorCode = null;

    public function __construct(Db $db, $message, $errorCode = null) {
        $this->db = $db;
        $this->errorCode = $errorCode;
        parent::__construct($message, 500);
    }

    /**
     * @return null|string|int
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     * @return Db
     */
    public function getDb() {
        return $this->db;
    }

}