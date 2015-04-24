<?php

namespace PeskyORM\Exception;

use PeskyORM\Db;

class DbException extends \Exception {

    /** @var Db|null */
    protected $db;
    /** @var null|string|int */
    protected $errorCode = null;

    /**
     * @param Db|null $db
     * @param int $message
     * @param null $errorCode
     */
    public function __construct($db, $message, $errorCode = null) {
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
     * @return Db|null
     */
    public function getDb() {
        return $this->db;
    }

}