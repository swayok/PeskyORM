<?php

namespace PeskyORM;

use PeskyORM\ORM\TableStructure;

abstract class DbTableConfig extends TableStructure {

    public static function getTableName() {
        return static::TABLE_NAME;
    }
}