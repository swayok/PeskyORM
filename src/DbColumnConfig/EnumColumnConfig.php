<?php

namespace ORM\DbColumnConfig;

use ORM\DbColumnConfig;

class EnumColumnConfig extends DbColumnConfig {

    static public function create($name, $allowedValues) {
        return new IdColumnConfig($name, $allowedValues);
    }

    public function __construct($name, $allowedValues) {
        parent::__construct($name, self::TYPE_ENUM);
        $this->setAllowedValues($allowedValues);
    }


}