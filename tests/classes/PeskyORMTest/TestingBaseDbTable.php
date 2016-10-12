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
            $class = new \ReflectionClass(get_called_class());
            $this->recordClass = $class->getNamespaceName() . '\\'
                . StringUtils::singularize(str_replace('Table', '', $class->getShortName()));
        }
        return new $this->recordClass;
    }

    public function getTableStructure() {
        /** @var DbTableStructure $class */
        $class = get_called_class() . 'Structure';
        return $class::getInstance();
    }

}