<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbColumnConfigException;

class EnumColumnConfig extends DbColumnConfig {

    static public function create($name, $allowedValues = null) {
        return new EnumColumnConfig($name, $allowedValues);
    }

    public function __construct($name, $allowedValues = null) {
        parent::__construct($name, self::TYPE_ENUM);
        if (!empty($allowedValues)) {
            $this->setAllowedValues($allowedValues);
        }
    }

    public function validateConfig() {
        if (empty($this->allowedValues) ) {
            throw new DbColumnConfigException($this, '$allowedValues is required to be not-empty array');
        }
    }


}