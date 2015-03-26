<?php

namespace ORM\Exception;

use PeskyORM\DbConnectionConfig;

class DbConnectionConfigException extends DbConfigException {

    /** @var DbConnectionConfig */
    protected $dbConnectionConfig;

    public function __construct(DbConnectionConfig $dbConnectionConfig, $message, $errorCode = null) {
        $this->dbConnectionConfig = $dbConnectionConfig;
        parent::__construct($message, $errorCode);
    }

    /**
     * @return DbConnectionConfig
     */
    public function getDbConnectionConfig() {
        return $this->dbConnectionConfig;
    }


}