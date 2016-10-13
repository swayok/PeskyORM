<?php

namespace PeskyORM\ORM;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\Exception\OrmException;

abstract class DbTableStructure implements DbTableStructureInterface {

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
     * @var null|DbTableColumn
     */
    protected $pk = null;
    /**
     * @var DbTableColumn[]
     */
    protected $fileColumns = [];

    /**
     * At first it contains only ReflectionMethod objects and lazy-loads DbTableColumn objects later
     * @var DbTableColumn[]
     */
    protected $columns = [];

    /** @var DbTableRelation[] */
    protected $relations = [];

    /** @var DbTableStructure[]  */
    static private $instances = [];

    /**
     * @return $this
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
        if (!$this->_findPkColumn(false)) {
            throw new OrmException('Table schema must contain primary key', OrmException::CODE_INVALID_TABLE_SCHEMA);
        }
    }

    /**
     * @return string
     */
    static public function getConnectionName() {
        return 'default';
    }

    /**
     * @return string
     */
    static public function getSchema() {
        return 'public';
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
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($colName) {
        return static::i()->_loadColumnConfig($colName);
    }

    /**
     * @return DbTableColumn[]
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
     * @return DbTableColumn
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
     * @return DbTableColumn[] = array('column_name' => DbTableColumn)
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getFileColumns() {
        static::i()->_loadAllColumnsConfigs();
        return static::i()->fileColumns;
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
     * @return DbTableRelation
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($relationName) {
        return static::i()->_loadRelationConfig($relationName);
    }

    /**
     * @return DbTableRelation[]
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
     * Column method must return DbTableColumn object
     * Relation method must return DbTableRelation object
     */
    protected function loadColumnConfigsFromPrivateMethods() {
        $objectReflection = new \ReflectionObject($this);
        $methods = $objectReflection->getMethods(\ReflectionMethod::IS_PRIVATE);
        //$relations = array();
        foreach ($methods as $method) {
            if ($method->isStatic()) {
                continue;
            }
            if (preg_match(DbTableColumn::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->columns[$method->getName()] = $method;
            } else if (preg_match(DbTableRelation::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->relations[$method->getName()] = $method;
            }
        }
    }

    /**
     * Use table description received from DB to automatically create column configs
     * @throws \InvalidArgumentException
     */
    protected function createMissingColumnConfigsFromDbTableDescription() {
        DbConnectionsManager::getConnection(static::getConnectionName())->describeTable(static::getTableName());
        // todo: implement this
    }

    /**
     * Make DbTableColumn object for private method (if not made yet) and return it
     * @param string $colName
     * @return DbTableColumn
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
            if (!($config instanceof DbTableColumn)) {
                throw new \UnexpectedValueException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\DbTableColumn class"
                );
            }
            if (!$config->hasName()) {
                $config->setName($method->getName());
            }
            /** @var DbTableColumn $config */
            $config->setTableStructure($this);
            unset($method, $this->columns[$colName]);
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
        }
        return $this->columns[$colName];
    }

    /**
     * Run static::_loadColumnConfig() method for all $this->columns and return updated $this->columns
     * @return DbTableColumn[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadAllColumnsConfigs() {
        if (!$this->allColumnsProcessed) {
            foreach ($this->columns as $columnName => $column) {
                if (!($column instanceof DbTableColumn)) {
                    $this->_loadColumnConfig($columnName);
                }
            }
            $this->allColumnsProcessed = true;
        }
        return $this->columns;
    }

    /**
     * Make DbTableRelation object for private method (if not made yet) and return it
     * @param string $relationName
     * @return DbTableRelation
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadRelationConfig($relationName) {
        if (!static::hasRelation($relationName)) {
            throw new \InvalidArgumentException("Table has no relation named '{$relationName}'");
        }
        if ($this->relations[$relationName] instanceof \ReflectionMethod) {
            /** @var \ReflectionMethod $method */
            $method = $this->relations[$relationName];
            $method->setAccessible(true);
            $config = $method->invoke($this);
            $method->setAccessible(false);
            if (!($config instanceof DbTableRelation)) {
                throw new \UnexpectedValueException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\DbTableRelation class"
                );
            }
            /** @var DbTableRelation $config */
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
     * @return DbTableRelation[]
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _loadAllRelationsConfigs() {
        if (!$this->allRelationsProcessed) {
            foreach ($this->relations as $relationName => $relation) {
                if (!($relation instanceof DbTableRelation)) {
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
     * @return null|DbTableColumn
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function _findPkColumn($throwExceptionIfNotFound = true) {
        if ($this->pk === null) {
            foreach ($this->columns as $columnName => $column) {
                if (!($column instanceof DbTableColumn) && $this->_loadColumnConfig($columnName)->isItPrimaryKey()) {
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
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances() {
        self::$instances = [];
    }

}