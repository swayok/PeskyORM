<?php

namespace ORM\DbColumnConfig;

use ORM\DbColumnConfig;

class IdColumnConfig extends DbColumnConfig {

    protected $name = 'id';
    protected $type = self::TYPE_INT;
    protected $isPk = true;
    protected $isNullable = true;
    protected $isRequired = self::ON_UPDATE;
    protected $isExcluded = self::ON_CREATE;

    static public function create($name = 'id', $type = self::TYPE_INT) {
        return new IdColumnConfig($name, $type);
    }

    public function __construct($name = 'id', $type = self::TYPE_INT) {
        parent::__construct($name, $type);
    }


}