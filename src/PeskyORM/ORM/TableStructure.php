<?php

namespace PeskyORM\ORM;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\JoinInfo;
use PeskyORM\Exception\OrmException;

abstract class TableStructure implements TableStructureInterface {

    /**
     * Use table description from DB to automatically create missing column configs
     * It uses PeskyORM\Core\DbConnectionsManager::getConnection(static::$connectionName)->describeTable(static::$name)
     * @var bool
     */
    static protected $autodetectColumnConfigs = false;
    /**
     * Loads columns and relations configs from private methods of child class
     * Disable if you do not use private methods to define columns and relations
     * @var bool
     */
    static protected $autoloadConfigsFromPrivateMethods = true;
    /**
     * @var bool
     */
    protected $allColumnsProcessed = false;
    /**
     * @var bool
     */
    protected $allRelationsProcessed = false;
    /**
     * @var null|Column
     */
    protected $pk;
    /**
     * @var Column[]
     */
    protected $fileColumns = [];
    /**
     * @var Column[]
     */
    protected $columsThatExistInDb = [];
    /**
     * @var Column[]
     */
    protected $columsThatDoNotExistInDb = [];

    /**
     * At first it contains only ReflectionMethod objects and lazy-loads Column objects later
     * @var Column[]
     */
    protected $columns = [];

    /** @var Relation[] */
    protected $relations = [];

    /** @var array */
    protected $columnsRelations = [];

    /** @var TableStructure[]  */
    static private $instances = [];

    /**
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    final static public function getInstance() {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
            self::$instances[static::class]->loadConfigs();
        }
        return self::$instances[static::class];
    }

    /**
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    final static public function i() {
        return static::getInstance();
    }

    /**
     * Use loadConfigs() method to do anything you wanted to do via constructor overwrite
     * @throws \BadMethodCallException
     */
    final protected function __construct() {
        if (isset(self::$instances[static::class])) {
            throw new \BadMethodCallException('Attempt to create 2nd instance of class ' . __CLASS__);
        }
    }

    /**
     * Load columns and relations configs
     */
    protected function loadConfigs() {
        if (static::$autoloadConfigsFromPrivateMethods) {
            $this->loadColumnConfigsFromPrivateMethods();
        }
        if (static::$autodetectColumnConfigs) {
            $this->createMissingColumnConfigsFromDbTableDescription();
        }
        $this->analyze();
        $this->validate();
    }

    /**
     * Analyze configs to collect some useful data
     */
    protected function analyze() {
        foreach ($this->relations as $relationConfig) {
            $this->_getColumn($relationConfig->getLocalColumnName()); //< validate local column existance
            $this->columnsRelations[$relationConfig->getLocalColumnName()][] = $relationConfig;
        }
    }

    /**
     * Validate configs
     * @throws OrmException
     */
    protected function validate() {
        if ($this->pk === null) {
            throw new OrmException('Table schema must contain primary key', OrmException::CODE_INVALID_TABLE_SCHEMA);
        }
    }

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    static public function getConnectionName($writable) {
        return 'default';
    }

    /**
     * @return string
     */
    static public function getSchema() {
        return null;
    }

    /**
     * @param string $colName
     * @return bool
     */
    static public function hasColumn($colName) {
        return static::getInstance()->_hasColumn($colName);
    }

    /**
     * @param string $colName
     * @return Column
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($colName) {
        return static::getInstance()->_getColumn($colName);
    }

    /**
     * @return Column[] - key = column name
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getColumns() {
        return static::getInstance()->columns;
    }

    /**
     * @return string|null
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumnName() {
        return static::getPkColumn()->getName();
    }

    /**
     * @return Column
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumn() {
        return static::getInstance()->pk;
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::getInstance()->pk !== null;
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        return count(static::getInstance()->fileColumns) > 0;
    }

    /**
     * @param string $colName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumn($colName) {
        return static::getInstance()->_getColumn($colName)->isItAFile();
    }

    /**
     * @return Column[] = array('column_name' => Column)
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getFileColumns() {
        return static::getInstance()->fileColumns;
    }

    /**
     * @return Column[]
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatExistInDb() {
        return static::getInstance()->columsThatExistInDb;
    }

    /**
     * @return Column[]
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatDoNotExistInDb() {
        return static::getInstance()->columsThatDoNotExistInDb;
    }

    /**
     * @param $relationName
     * @return bool
     */
    static public function hasRelation($relationName) {
        return static::getInstance()->_hasRelation($relationName);
    }

    /**
     * @param string $relationName
     * @return Relation
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($relationName) {
        return static::getInstance()->_getRelation($relationName);
    }

    /**
     * @return Relation[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::getInstance()->relations;
    }

    /**
     * @param string $colName
     * @return Relation[]
     */
    static public function getColumnRelations($colName) {
        $instance = static::getInstance();
        $instance->_getColumn($colName);
        return isset($instance->columnsRelations[$colName]) ? $instance->columnsRelations[$colName] : [];
    }

    /**
     * Collects column configs from private methods where method name is column name or relation name.
     * Column name must be a lowercased string starting from letter: private function parent_id() {}
     * Relation name must start from upper case letter: private function RelationName() {}
     * Column method must return Column object
     * Relation method must return Relation object
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function loadColumnConfigsFromPrivateMethods() {
        $objectReflection = new \ReflectionObject($this);
        $methods = $objectReflection->getMethods(\ReflectionMethod::IS_PRIVATE);
        foreach ($methods as $method) {
            if ($method->isStatic()) {
                continue;
            }
            if (preg_match(Column::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->loadColumnConfigFromMethodReflection($method);
            } else if (preg_match(JoinInfo::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->loadRelationConfigFromMethodReflection($method);
            }
        }
    }

    /**
     * Use table description received from DB to automatically create column configs
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function createMissingColumnConfigsFromDbTableDescription() {
        $description = DbConnectionsManager::getConnection(static::getConnectionName(false))
            ->describeTable(static::getTableName(), static::getSchema());
        foreach ($description->getColumns() as $columnName => $columnDescription) {
            if (!$this->_hasColumn($columnName)) {
                $column = Column::create($columnDescription->getOrmType(), $columnName)
                    ->setIsNullableValue($columnDescription->isNullable());
                if ($columnDescription->isPrimaryKey()) {
                    $column->primaryKey();
                    $this->pk = $column;
                }
                if ($columnDescription->isUnique()) {
                    $column->uniqueValues();
                }
                if ($columnDescription->getDefault() !== null) {
                    $column->setDefaultValue($columnDescription->getDefault());
                }
                $this->columns[$columnName] = $column;
            }
        }
        // todo: add possibility to cache table description
    }

    /**
     * @param string $colName
     * @return Column
     */
    protected function _getColumn($colName) {
        if (!$this->_hasColumn($colName)) {
            throw new \InvalidArgumentException("Table does not contain column named '{$colName}'");
        }
        return $this->columns[$colName];
    }

    /**
     * Make Column object for private method (if not made yet) and return it
     * @param \ReflectionMethod $method
     */
    protected function loadColumnConfigFromMethodReflection(\ReflectionMethod $method) {
        $method->setAccessible(true);
        /** @var Column $column */
        $column = $method->invoke($this);
        $method->setAccessible(false);
        if (!($column instanceof Column)) {
            throw new \UnexpectedValueException(
                "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\Column class"
            );
        }
        if (!$column->hasName()) {
            $column->setName($method->getName());
        }
        $this->addColumn($column);
    }

    /**
     * @param Column $column
     */
    protected function addColumn(Column $column) {
        $column->setTableStructure($this);
        $this->columns[$column->getName()] = $column;
        if ($column->isItPrimaryKey()) {
            if (!empty($this->pk)) {
                throw new \UnexpectedValueException(
                    '2 primary keys in one table is forbidden: \'' . $this->pk->getName() . " and '{$column->getName()}'"
                );
            }
            $this->pk = $column;
        }
        if ($column->isItAFile()) {
            $this->fileColumns[$column->getName()] = $column;
        }
        if ($column->isItExistsInDb()) {
            $this->columsThatExistInDb[$column->getName()] = $column;
        } else {
            $this->columsThatDoNotExistInDb[$column->getName()] = $column;
        }
    }

    /**
     * @param string $colName
     * @return bool
     */
    protected function _hasColumn($colName) {
        return isset($this->columns[$colName]);
    }

    /**
     * @param $relationName
     * @return Relation
     */
    protected function _getRelation($relationName) {
        if (!$this->_hasRelation($relationName)) {
            throw new \InvalidArgumentException("There is no relation '{$relationName}' in " . static::class);
        }
        return $this->relations[$relationName];
    }

    /**
     * @param string $relationName
     * @return bool
     */
    protected function _hasRelation($relationName) {
        return isset($this->relations[$relationName]);
    }

    /**
     * Make Relation object for private method (if not made yet) and return it
     * @param \ReflectionMethod $method
     */
    protected function loadRelationConfigFromMethodReflection(\ReflectionMethod $method) {
        /** @var \ReflectionMethod $method */
        $method->setAccessible(true);
        /** @var Relation $config */
        $config = $method->invoke($this);
        $method->setAccessible(false);
        if (!($config instanceof Relation)) {
            throw new \UnexpectedValueException(
                "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\Relation class"
            );
        }
        if ($config->hasName()) {
            $config->setName($method->getName());
        }
        $this->addRelation($config);
    }

    /**
     * @param Relation $relation
     */
    protected function addRelation(Relation $relation) {
        $this->relations[$relation->getName()] = $relation;
    }

    /**
     * @param string $name
     * @return Column|Relation
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function __get($name) {
        if ($this->_hasRelation($name)) {
            return static::getRelation($name);
        } else {
            return static::getColumn($name);
        }
    }

    /**
     * @param string $name
     * @param Column|Relation $value
     * @throws \BadMethodCallException
     */
    public function __set($name, $value) {
        throw new \BadMethodCallException('You need to use private methods to setup columns');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        return $this->_hasColumn($name) || $this->_hasRelation($name);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances() {
        self::$instances = [];
    }

}