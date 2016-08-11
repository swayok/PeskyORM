<?php

namespace PeskyORMTest;

use PeskyORM\ORM\DbTable;
use PeskyORM\ORM\DbTableStructure;
use Swayok\Utils\StringUtils;

abstract class TestingBaseDbTable extends DbTable {

    /** @var null|string */
    static protected $recordClass = null;
    /** @var DbTableStructure */
    static protected $tableStructure;

    static public function newRecord() {
        if (!static::$recordClass) {
            $shortClassName = StringUtils::classify(StringUtils::singularize(static::getName()));
            static::$recordClass = preg_replace('%\\[^\\]+$%', '', get_called_class()) . '\\' . $shortClassName;
        }
        return call_user_func([static::$recordClass, 'create']);
    }

    static public function getStructure() {
        if (!static::$tableStructure) {
            static::$tableStructure = call_user_func([get_called_class() . 'Structure', 'getInstance']);
        }
        return static::$tableStructure;
    }


}