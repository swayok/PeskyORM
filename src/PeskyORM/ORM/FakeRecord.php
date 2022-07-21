<?php

namespace PeskyORM\ORM;

use Swayok\Utils\StringUtils;

abstract class FakeRecord extends Record {

    static private $fakesCreated = 0;
    static protected $table;

    /**
     * @return TableInterface
     */
    static public function getTable() {
        return static::$table;
    }

    static public function setTable(FakeTable $table) {
        static::$table = $table;
    }

    /**
     * @param FakeTable $table
     * @return string - class name of a fake record
     * @throws \BadMethodCallException
     */
    static public function makeNewFakeRecordClass(FakeTable $table) {
        static::$fakesCreated++;
        $suffixClass = StringUtils::classify($table->getTableStructure()->getTableName());
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