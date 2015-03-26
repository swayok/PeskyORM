<?php

namespace ORM\Exception;

use ORM\DbColumnConfig;
use ORM\DbTableConfig;

class DbTableConfigException extends DbConfigException {

    /** @var DbTableConfig  */
    protected $dbTableConfig;

    public function __construct(DbTableConfig $dbTableConfig, $message, $errorCode = null) {
        $this->dbTableConfig = $dbTableConfig;
        parent::__construct($message, $errorCode);
    }

    /**
     * @return DbTableConfig
     */
    public function getDbTableConfig() {
        return $this->dbTableConfig;
    }

}