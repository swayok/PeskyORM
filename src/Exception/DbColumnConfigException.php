<?php

namespace PeskyORM\Exception;

use PeskyORM\DbColumnConfig;

class DbColumnConfigException extends DbConfigException {

    protected $dbColumnConfig;

    public function __construct(DbColumnConfig $dbColumnConfig, $message, $errorCode = null) {
        $this->dbColumnConfig = $dbColumnConfig;
        parent::__construct($message, $errorCode);
    }

    /**
     * @return DbColumnConfig
     */
    public function getDbColumnConfig() {
        return $this->dbColumnConfig;
    }

}