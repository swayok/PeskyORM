<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;

class EnumColumnConfig extends DbColumnConfig {

    static public function create($name, $allowedValues) {
        return new EnumColumnConfig($name, $allowedValues);
    }

    public function __construct($name, $allowedValues) {
        parent::__construct($name, self::TYPE_ENUM);
        $this->setAllowedValues($allowedValues);
    }


}