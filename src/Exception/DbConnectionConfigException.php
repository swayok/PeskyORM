<?php

namespace ORM\Exception;

use PeskyORM\DbConnectionConfig;

class DbConnectionConfigException extends DbConfigException {

    /** @var DbConnectionConfig */
    protected $dbConnectionConfig;

    public function __construct(DbConnectionConfig $dbConnectionConfig, $message) {
        $this->dbConnectionConfig = $dbConnectionConfig;
        parent::__construct($message, 500);
    }

    /**
     * @return DbConnectionConfig
     */
    public function getDbConnectionConfig() {
        return $this->dbConnectionConfig;
    }


}