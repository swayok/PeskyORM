<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Fakes;

use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\Utils\StringUtils;

abstract class FakeRecord extends Record
{
    
    private static int $fakesCreated = 0;
    protected static TableInterface $table;
    
    /**
     * @return \PeskyORM\ORM\Table\TableInterface
     */
    public static function getTable(): TableInterface
    {
        return static::$table;
    }
    
    public static function setTable(FakeTable $table): void
    {
        static::$table = $table;
    }
    
    /**
     * @param FakeTable $table
     * @return string - class name of a fake record
     * @throws \BadMethodCallException
     */
    public static function makeNewFakeRecordClass(FakeTable $table): string
    {
        static::$fakesCreated++;
        $suffixClass = StringUtils::toPascalCase($table->getTableStructure()->getTableName());
        $className = 'FakeRecord' . static::$fakesCreated . 'For' . $suffixClass;
        $namespace = 'PeskyORM\ORM\Fakes';
        $class = <<<VIEW
namespace {$namespace};

use PeskyORM\ORM\Fakes\FakeRecord;

class {$className} extends FakeRecord {
    
}
VIEW;
        eval($class);
        /** @var FakeRecord|string $fullClassName */
        $fullClassName = $namespace . '\\' . $className;
        $fullClassName::setTable($table);
        return $fullClassName;
    }
}