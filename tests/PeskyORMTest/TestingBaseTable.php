<?php

namespace Tests\PeskyORMTest;

use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;
use Swayok\Utils\StringUtils;

abstract class TestingBaseTable extends Table
{
    
    /** @var null|string */
    protected $recordClass = null;
    
    public function newRecord()
    {
        if (!$this->recordClass) {
            $class = new \ReflectionClass(get_called_class());
            $this->recordClass = $class->getNamespaceName() . '\\'
                . StringUtils::singularize(str_replace('Table', '', $class->getShortName()));
        }
        return new $this->recordClass();
    }
    
    public function getTableStructure()
    {
        /** @var TableStructure $class */
        $class = get_called_class() . 'Structure';
        return $class::getInstance();
    }
    
}