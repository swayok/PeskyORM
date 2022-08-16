<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use Swayok\Utils\StringUtils;

abstract class FakeRecord extends Record
{
    
    private static $fakesCreated = 0;
    protected static $table;
    
    /**
     * @return TableInterface
     */
    public static function getTable(): TableInterface
    {
        return static::$table;
    }
    
    public static function setTable(FakeTable $table)
    {
        static::$table = $table;
    }
    
    /**
     * @param FakeTable $table
     * @return string - class name of a fake record
     * @throws \BadMethodCallException
     */
    public static function makeNewFakeRecordClass(FakeTable $table)
    {
        static::$fakesCreated++;
        $suffixClass = StringUtils::classify(
            $table->getTableStructure()
                ->getTableName()
        );
        $className = 'FakeRecord' . static::$fakesCreated . 'For' . $suffixClass;
        $namespace = 'PeskyORM\ORM\Fakes';
        $class = <<<VIEW
namespace {$namespace};

use PeskyORM\ORM\FakeRecord;

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