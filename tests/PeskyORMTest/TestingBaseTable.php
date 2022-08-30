<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;
use PeskyORM\ORM\TableStructureInterface;
use Swayok\Utils\StringUtils;

abstract class TestingBaseTable extends Table
{
    
    protected ?string $recordClass = null;
    
    public function newRecord(): RecordInterface
    {
        if (!$this->recordClass) {
            $class = new \ReflectionClass(static::class);
            $this->recordClass = $class->getNamespaceName() . '\\'
                . StringUtils::singularize(str_replace('Table', '', $class->getShortName()));
        }
        return new $this->recordClass();
    }
    
    public function getTableStructure(): TableStructureInterface
    {
        /** @var TableStructure $class */
        $class = static::class . 'Structure';
        return $class::getInstance();
    }
    
}