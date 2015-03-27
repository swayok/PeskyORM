<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;

class IdColumnConfig extends DbColumnConfig {

    static public function create($name = 'id', $type = self::TYPE_INT) {
        return new IdColumnConfig($name, $type);
    }

    public function __construct($name = 'id', $type = self::TYPE_INT) {
        parent::__construct($name, $type);
        $this->setIsRequired(self::ON_UPDATE);
        $this->setIsExcluded(self::ON_CREATE);
        $this->setIsNullable(true);
        $this->setIsPk(true);
    }


}