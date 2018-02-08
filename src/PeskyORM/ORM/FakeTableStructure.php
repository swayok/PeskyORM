<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use Swayok\Utils\StringUtils;

abstract class FakeTableStructure extends TableStructure {

    static private $fakesCreated = 0;
    protected $allColumnsProcessed = true;
    protected $allRelationsProcessed = true;
    protected $treatAnyColumnNameAsValid = true;
    protected $connectionName = 'default';
    protected $connectionNameWritable = 'default';

    /**
     * @param string $tableName
     * @param TableStructureInterface $tableStructureToCopy - use this table structure as parent class for a fake one
     *      but replace its table name
     * @return FakeTableStructure|TableStructureInterface
     * @throws \InvalidArgumentException
     */
    static public function makeNewFakeStructure($tableName, TableStructureInterface $tableStructureToCopy = null) {
        if (!is_string($tableName) || trim($tableName) === '' || !DbAdapter::isValidDbEntityName($tableName)) {
            throw new \InvalidArgumentException(
                '$tableName argument bust be a not empty string that matches DB entity naming rules (usually alphanumeric with underscores)'
            );
        }
        static::$fakesCreated++;
        if ($tableStructureToCopy) {
            $parentClassFullName = get_class($tableStructureToCopy);
            $classReflection = new \ReflectionClass($tableStructureToCopy);
            $namespace = $classReflection->getNamespaceName();
            $parentClassShortName = $classReflection->getShortName();
            $dbSchema = 'null';
        } else {
            $namespace = 'PeskyORM\ORM\Fakes';
            $parentClassFullName = FakeTableStructure::class;
            $parentClassShortName = 'FakeTableStructure';
            $dbSchema = 'parent::getSchema()';
        }
        $className = 'FakeTableStructure' . static::$fakesCreated . 'For' . StringUtils::classify($tableName);

        $class = <<<VIEW
namespace {$namespace};

use {$parentClassFullName};

class {$className} extends {$parentClassShortName} {
    /**
     * @return string
     */
    static public function getTableName() {
        return '{$tableName}';
    }
    
    /**
     * @return string
     */
    static public function getSchema() {
        return {$dbSchema};
    }
}
VIEW;
        eval($class);
        /** @var FakeTableStructure $fullClassName */
        $fullClassName = $namespace . '\\' . $className;
        return $fullClassName::getInstance();
    }

    protected function loadConfigs() {
        $this->pk = $this->columns['id'] = Column::create(Column::TYPE_INT, 'id')->primaryKey();
        parent::loadConfigs();
    }

    /**
     * @param array $columns - key-value array where key is column name and value is column type or
     *          key is int and value is column name or instance of Column class
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setTableColumns(array $columns) {
        $this->columns = [];
        foreach ($columns as $name => $typeOrColumnInstance) {
            if (is_int($name)) {
                $name = $typeOrColumnInstance;
                $typeOrColumnInstance = Column::TYPE_STRING;
            }
            if ($typeOrColumnInstance instanceof Column) {
                if ($typeOrColumnInstance->hasName()) {
                    $name = $typeOrColumnInstance->getName();
                } else {
                    $typeOrColumnInstance->setName($name);
                }
                $this->columns[$name] = $typeOrColumnInstance;
            } else {
                $this->columns[$name] = Column::create($typeOrColumnInstance, $name);
            }
            if ($this->columns[$name]->isItAFile()) {
                $this->fileColumns[] = $this->columns[$name];
            } else if ($this->columns[$name]->isItPrimaryKey()) {
                $this->pk = $this->columns[$name];
            }
        }
        $this->treatAnyColumnNameAsValid = count($this->columns) === 0;
        return $this;
    }

    /**
     * @param $columnName
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function markColumnAsPrimaryKey($columnName) {
        $this->pk = static::getColumn($columnName)->primaryKey();
        return $this;
    }

    /**
     * @param TableStructure $structure
     * @param bool $append - true: existing structure will be appended; false - replaced
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function mimicTableStructure(TableStructure $structure, $append = false) {
        if (!$append) {
            $this->columns = [];
            $this->relations = [];
            $this->fileColumns = [];
            $this->pk = null;
        }
        $this->columns = array_merge($this->columns, $structure::getColumns());
        $this->relations = array_merge($this->columns, $structure::getRelations());
        $this->fileColumns = array_merge($this->fileColumns, $structure::getFileColumns());
        $this->pk = $structure::getPkColumn();
        $this->connectionName = $structure::getConnectionName(false);
        $this->connectionNameWritable = $structure::getConnectionName(true);
        $this->treatAnyColumnNameAsValid = count($this->columns) === 0;
        return $this;
    }

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    static public function getConnectionName($writable) {
        return $writable ? static::getInstance()->connectionNameWritable : static::getInstance()->connectionName;
    }

    /**
     * @param string $colName
     * @return bool
     */
    static public function hasColumn($colName) {
        return static::getInstance()->treatAnyColumnNameAsValid || parent::hasColumn($colName);
    }

    /**
     * @param string $colName
     * @return Column
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumn($colName) {
        if (static::getInstance()->treatAnyColumnNameAsValid && !parent::hasColumn($colName)) {
            static::getInstance()->columns[$colName] = Column::create(Column::TYPE_STRING, $colName);
        }
        return parent::getColumn($colName);
    }

    protected function loadColumnConfigsFromPrivateMethods() {

    }

    protected function createMissingColumnConfigsFromDbTableDescription() {

    }
}