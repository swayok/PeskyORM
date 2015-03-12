<?php

namespace ORM\Exception;

use ORM\DbColumnConfig;

class DbColumnConfigException extends \Exception {

    protected $dbColumnConfig;

    public function __construct(DbColumnConfig $dbColumnConfig, $message) {
        $this->dbColumnConfig = $dbColumnConfig;
        parent::__construct($message, 500);
    }

    /**
     * @return DbColumnConfig
     */
    public function getDbColumnConfig() {
        return $this->dbColumnConfig;
    }

}