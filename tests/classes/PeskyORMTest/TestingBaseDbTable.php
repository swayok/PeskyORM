<?php

namespace PeskyORMTest;

use PeskyORM\ORM\DbTable;
use PeskyORM\ORM\DbTableStructure;
use Swayok\Utils\StringUtils;

abstract class TestingBaseDbTable extends DbTable {

    /** @var null|string */
    protected $recordClass = null;

    public function newRecord() {
        if (!$this->recordClass) {
            $shortClassName = StringUtils::classify(StringUtils::singularize(static::getName()));
            $this->recordClass = preg_replace('%\\[^\\]+$%', '', get_called_class()) . '\\' . $shortClassName;
        }
        return new $this->recordClass();
    }

    public function getTableStructure() {
        /** @var DbTableStructure $class */
        $class = get_called_class() . 'Structure';
        return $class::getInstance();
    }

}