<?php

namespace PeskyORM\ORM;

use Swayok\Utils\StringUtils;

abstract class FakeTableStructure extends TableStructure {

    static private $fakesCreated = 0;

    /**
     * @return static
     * @throws \BadMethodCallException
     */
    static public function getInstance() {
        throw new \BadMethodCallException('FakeTableStructure cannot be called by FakeTableStructure::getInstance()');
    }

    /**
     * @param array $columns - key-value array where key is column name and value is column type or
     *          key is int and value is column name
     * @return $this
     */
    public function setTableColumns(array $columns) {
        $this->columns = [];
        foreach ($columns as $name => $type) {
            if (is_int($name)) {
                $name = $type;
                $type = Column::TYPE_STRING;
            }
            $this->columns[$name] = Column::create($type, $name);
        }
        return $this;
    }

    /**
     * @param string $tableName
     * @return FakeTableStructure
     */
    static public function makeNewFakeStructure($tableName) {
        static::$fakesCreated++;
        $suffix = static::$fakesCreated;
        $tableNameClass = StringUtils::classify($tableName);
        $class = <<<VIEW
namespace PeskyORM\ORM\Fakes;

use PeskyORM\ORM\FakeTableStructure;

class FakeTableStructure{$suffix}For{$tableNameClass} extends FakeTableStructure {
    /**
     * @return string
     */
    static public function getTableName() {
        return '{$tableName}';
    }
}
VIEW;
        eval($class);
        /** @var FakeTableStructure $class */
        return $class::getInstance();
    }
}