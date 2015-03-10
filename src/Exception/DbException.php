<?php

namespace ORM\Exception;

use ORM\Db;

class DbException extends \Exception {

    /** @var Db */
    protected $db;

    public function __construct(Db $db, $message) {
        $this->db = $db;
        parent::__construct($message, 500);
    }

    /**
     * @return Db
     */
    public function getDb() {
        return $this->db;
    }

}