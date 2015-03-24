<?php

namespace ORM\Exception;

use ORM\DbColumnConfig;
use ORM\DbTableConfig;

class DbTableConfigException extends DbConfigException {

    protected $dbTableConfig;

    public function __construct(DbTableConfig $dbTableConfig, $message) {
        $this->dbTableConfig = $dbTableConfig;
        parent::__construct($message, 500);
    }

    /**
     * @return DbTableConfig
     */
    public function getDbColumnConfig() {
        return $this->dbTableConfig;
    }

}