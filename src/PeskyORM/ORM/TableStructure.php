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

    /** @var TableStructure[]  */
    static private $instances = [];

    /**
     * @return $this
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function getInstance() {
        $class = get_called_class();
        if (!array_key_exists($class, self::$instances)) {
            new static();
        }
        return self::$instances[$class];
    }

    /**
     * @return $this
     */
    static public function i() {
        return static::getInstance();
    }

    protected function __construct() {
        $class = get_class($this);
        if (array_key_exists($class, self::$instances)) {
            throw new \BadMethodCallException('Attempt to create 2nd instance of class ' . __CLASS__);
        }
        self::$instances[$class] = $this;
        $this->loadColumnConfigsFromPrivateMethods();
        if (static::$autodetectColumnConfigs) {
            $this->createMissingColumnConfigsFromDbTableDescription();
        }
        $this->_loadAllRelationsConfigs();
        if (!$this->_findPkColumn(false)) {
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
        return is_string($colName) && !empty(static::i()->columns[$colName]);
    }

    /**
     * @param string $colName
     * @return Column
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($colName) {
        return static::i()->_loadColumnConfig($colName);
    }

    /**
     * @return Column[] - key = column name
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getColumns() {
        return static::i()->_loadAllColumnsConfigs();
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
        return static::i()->_findPkColumn();
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::i()->_findPkColumn(false) !== null;
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        static::i()->_loadAllColumnsConfigs();
        return count(static::i()->fileColumns) > 0;
    }

    /**
     * @param string $colName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumn($colName) {
        return static::hasColumn($colName) && static::i()->_loadColumnConfig($colName)->isItAFile();
    }

    /**
     * @return Column[] = array('column_name' => Column)
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getFileColumns() {
        static::i()->_loadAllColumnsConfigs();
        return static::i()->fileColumns;
    }

    /**
     * @return Column[]
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatExistInDb() {
        static::i()->_loadAllColumnsConfigs();
        return static::i()->columsThatExistInDb;
    }

    /**
     * @return Column[]
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatDoNotExistInDb() {
        static::i()->_loadAllColumnsConfigs();
        return static::i()->columsThatDoNotExistInDb;
    }

    /**
     * @param $relationName
     * @return bool
     */
    static public function hasRelation($relationName) {
        return is_string($relationName) && !empty(static::i()->relations[$relationName]);
    }

    /**
     * @param string $relationName
     * @return Relation
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($relationName) {
        return static::i()->_loadRelationConfig($relationName);
    }

    /**
     * @return Relation[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::i()->_loadAllRelationsConfigs();
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
        //$relations = array();
        foreach ($methods as $method) {
            if ($method->isStatic()) {
                continue;
            }
            if (preg_match(Column::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->columns[$method->getName()] = $method;
            } else if (preg_match(JoinInfo::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->relations[$method->getName()] = $method;
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
            if (!static::hasColumn($columnName)) {
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
     * Make Column object for private method (if not made yet) and return it
     * @param string $colName
     * @return Column
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadColumnConfig($colName) {
        if (!static::hasColumn($colName)) {
            throw new \InvalidArgumentException("Table does not contain column named '{$colName}'");
        }
        if ($this->columns[$colName] instanceof \ReflectionMethod) {
            /** @var \ReflectionMethod $method */
            $method = $this->columns[$colName];
            $method->setAccessible(true);
            $config = $method->invoke($this);
            $method->setAccessible(false);
            if (!($config instanceof Column)) {
                throw new \UnexpectedValueException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\Column class"
                );
            }
            if (!$config->hasName()) {
                $config->setName($method->getName());
            }
            /** @var Column $config */
            $config->setTableStructure($this);
            unset($method);
            if ($colName !== $config->getName()) {
                unset($this->columns[$colName]);
            }
            $this->columns[$config->getName()] = $config;
            if ($config->isItPrimaryKey()) {
                if (!empty($this->pk)) {
                    throw new \UnexpectedValueException(
                        '2 primary keys in one table is forbidden: \'' . $this->pk->getName() . " and '{$config->getName()}'"
                    );
                }
                $this->pk = $config;
            }
            if ($config->isItAFile()) {
                $this->fileColumns[$config->getName()] = $config;
            }
            if ($config->isItExistsInDb()) {
                $this->columsThatExistInDb[$config->getName()] = $config;
            } else {
                $this->columsThatDoNotExistInDb[$config->getName()] = $config;
            }
        }
        return $this->columns[$colName];
    }

    /**
     * Run static::_loadColumnConfig() method for all $this->columns and return updated $this->columns
     * @return Column[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadAllColumnsConfigs() {
        if (!$this->allColumnsProcessed) {
            foreach ($this->columns as $columnName => $column) {
                if (!($column instanceof Column)) {
                    $this->_loadColumnConfig($columnName);
                }
            }
            $this->allColumnsProcessed = true;
        }
        return $this->columns;
    }

    /**
     * Make Relation object for private method (if not made yet) and return it
     * @param string $relationName
     * @return Relation
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadRelationConfig($relationName) {
        if (!static::hasRelation($relationName)) {
            throw new \InvalidArgumentException("There is no relation '{$relationName}' in " . get_class($this));
        }
        if ($this->relations[$relationName] instanceof \ReflectionMethod) {
            /** @var \ReflectionMethod $method */
            $method = $this->relations[$relationName];
            $method->setAccessible(true);
            $config = $method->invoke($this);
            $method->setAccessible(false);
            if (!($config instanceof Relation)) {
                throw new \UnexpectedValueException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\Relation class"
                );
            }
            /** @var Relation $config */
            if (!static::hasColumn($config->getLocalColumnName())) {
                $tableName = static::getTableName();
                throw new \UnexpectedValueException(
                    "Table '{$tableName}' has no column '{$config->getLocalColumnName()}' or column is not defined yet"
                );
            }
            if (!$config->getForeignTable()->getStructure()->hasColumn($config->getForeignColumnName())) {
                throw new \UnexpectedValueException(
                    "Table '{$config->getForeignTable()->getName()}' has no column '{$config->getForeignColumnName()}' or column is not defined yet"
                );
            }
            $config->setName($relationName);
            $this->relations[$relationName] = $config;
            $this->_loadColumnConfig($config->getLocalColumnName())->addRelation($config);
        }
        return $this->relations[$relationName];
    }

    /**
     * Run static::_loadRelationConfig() method for all $this->relations and return updated $this->relations
     * @return Relation[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadAllRelationsConfigs() {
        if (!$this->allRelationsProcessed) {
            foreach ($this->relations as $relationName => $relation) {
                if (!($relation instanceof Relation)) {
                    $this->_loadRelationConfig($relationName);
                }
            }
            $this->allRelationsProcessed = true;
        }
        return $this->relations;
    }

    /**
     * Find PK column config (if not known yet) by loading all not-loaded column configs
     * @param bool $throwExceptionIfNotFound
     * @return null|Column
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _findPkColumn($throwExceptionIfNotFound = true) {
        if ($this->pk === null) {
            foreach ($this->columns as $columnName => $column) {
                if (!($column instanceof Column) && $this->_loadColumnConfig($columnName)->isItPrimaryKey()) {
                    return $this->pk;
                }
            }
            if ($throwExceptionIfNotFound) {
                throw new \UnexpectedValueException('Table has no primary key column or it is not defined yet');
            }
        }
        return $this->pk;
    }

    /**
     * @param string $name
     * @return Column|Relation
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function __get($name) {
        if (static::hasRelation($name)) {
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
        throw new \BadMethodCallException('You need to use private methods to setup xolumns');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        return static::hasColumn($name) || static::hasRelation($name);
    }

    /**
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances() {
        self::$instances = [];
    }

}