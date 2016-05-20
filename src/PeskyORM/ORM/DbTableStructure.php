<?php

namespace PeskyORM\ORM;
use PeskyORM\Core\DbConnectionsManager;

abstract class DbTableStructure {

    static protected $connectionName = 'default';
    static protected $schema = 'public';
    static protected $name;
    /**
     * Use table description from DB to automatically create missing column configs
     * It uses PeskyORM\Core\DbConnectionsManager::getConnection(static::$connectionName)->describeTable(static::$name)
     * @var bool
     */
    static protected $autodetectColumnConfigs = false;
    /**
     * @var null|DbTableColumn
     */
    static protected $pk = null;
    /**
     * @var DbTableColumn[]
     */
    static protected $fileColumns = [];

    /**
     * At first it contains only ReflectionMethod objects and lazy-loads DbTableColumn objects later
     * @var DbTableColumn[]
     */
    static protected $columns = [];

    /** @var DbTableRelation[] */
    static protected $relations = [];

    /** @var DbTableStructure  */
    static private $instance = null;

    /**
     * @return $this
     */
    static public function getInstance() {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function __construct() {
        $this->loadColumnConfigsFromPrivateMethods();
        if (static::$autodetectColumnConfigs) {
            $this->createMissingColumnConfigsFromDbTableDescription();
        }
    }

    /**
     * @param string $colName
     * @return bool
     */
    static public function hasColumn($colName) {
        return is_string($colName) && !empty(static::$columns[$colName]);
    }

    /**
     * @param string $colName
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($colName) {
        return static::_loadColumnConfig($colName);
    }

    /**
     * @param $relationName
     * @return bool
     */
    static public function hasRelation($relationName) {
        return is_string($relationName) && !empty(static::$relations[$relationName]);
    }

    /**
     * @param string $relationName
     * @return DbTableRelation
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($relationName) {
        return static::_loadRelationConfig($relationName);
    }

    /**
     * @return string
     */
    static public function getConnectionName() {
        return static::$connectionName;
    }

    /**
     * @return string
     */
    static public function getSchema() {
        return static::$schema;
    }

    /**
     * @return string
     */
    static public function getName() {
        return static::$name;
    }

    /**
     * @return DbTableColumn[]
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumns() {
        return static::_loadAllColumnsConfigs();
    }

    /**
     * @return DbTableRelation[]
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::_loadAllRelationsConfigs();
    }

    /**
     * @return string|null
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumnName() {
        return static::getPkColumn()->getName();
    }

    /**
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumn() {
        return static::_findPkColumn();
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::_findPkColumn(false) !== null;
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        static::_loadAllColumnsConfigs();
        return count(static::$fileColumns) > 0;
    }

    /**
     * @param string $colName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumn($colName) {
        return static::_loadColumnConfig($colName)->isFile();
    }

    /**
     * @return DbTableColumn[] = array('column_name' => DbTableColumn)
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getFileColumns() {
        static::_loadAllColumnsConfigs();
        return static::$fileColumns;
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
            if (preg_match('%^[a-z][a-z0-9_]*$%', $method)) {
                static::$columns[$method->getName()] = $method;
            } else if (preg_match('%^[A-Z][a-z0-9_]*$%', $method)) {
                static::$relations[$method->getName()] = $method;
            }
        }
    }

    /**
     * Use table description received from DB to automatically create column configs
     * @throws \InvalidArgumentException
     */
    protected function createMissingColumnConfigsFromDbTableDescription() {
        DbConnectionsManager::getConnection(static::$connectionName)->describeTable(static::$name);
        // todo: implement this
    }

    /**
     * Make DbTableColumn object for private method (if not made yet) and return it
     * @param string $colName
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static protected function _loadColumnConfig($colName) {
        if (!static::hasColumn($colName)) {
            throw new \InvalidArgumentException("Table does not contain column named '{$colName}'");
        }
        if (static::$columns[$colName] instanceof \ReflectionMethod) {
            $tableStruct = static::getInstance();
            /** @var \ReflectionMethod $method */
            $method = static::$columns[$colName];
            $method->setAccessible(true);
            $config = $method->invoke($tableStruct);
            $method->setAccessible(false);
            if (!($config instanceof DbTableColumn)) {
                throw new \BadMethodCallException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\DbTableColumn class"
                );
            }
            if (!$config->hasName()) {
                $config->setName($method->getName());
            }
            $config->setTableStructure($tableStruct);
            static::$columns[$config->getName()] = $config;
            if ($config->isPk()) {
                if (!empty(static::$pk)) {
                    throw new \InvalidArgumentException(
                        '2 primary keys in one table is forbidden: \'' . static::$pk->getName() . " and '{$config->getName()}'"
                    );
                }
                static::$pk = $config;
            }
            if ($config->isFile()) {
                static::$fileColumns[$config->getName()] = $config;
            }
        }
        return static::$columns[$colName];
    }

    /**
     * Run static::_loadColumnConfig() method for all static::$columns and return updated static::$columns
     * @return DbTableColumn[]
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static protected function _loadAllColumnsConfigs() {
        foreach (static::$columns as $columnName => $column) {
            if (!($column instanceof DbTableColumn)) {
                static::_loadColumnConfig($columnName);
            }
        }
        return static::$columns;
    }

    /**
     * Make DbTableRelation object for private method (if not made yet) and return it
     * @param string $relationName
     * @return DbTableRelation
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static protected function _loadRelationConfig($relationName) {
        if (!static::hasRelation($relationName)) {
            throw new \InvalidArgumentException("Table has no relation named '{$relationName}'");
        }
        if (static::$relations[$relationName] instanceof \ReflectionMethod) {
            $tableStruct = static::getInstance();
            /** @var \ReflectionMethod $method */
            $method = static::$relations[$relationName];
            $method->setAccessible(true);
            $config = $method->invoke($tableStruct);
            $method->setAccessible(false);
            if (!($config instanceof DbTableRelation)) {
                throw new \BadMethodCallException(
                    "Method '{$method->getName()}' must return instance of \\PeskyORM\\ORM\\DbTableRelation class"
                );
            }
            if ($config->getLocalTableName() !== static::getName()) {
                throw new \InvalidArgumentException(
                    "Relation '{$relationName}': local table must be '" . static::getName()
                        . "'. Current value is '{$config->getLocalTableName()}'"
                );
            }
            if (!static::hasColumn($config->getLocalColumn())) {
                throw new \InvalidArgumentException(
                    "Table has no column '{$config->getLocalColumn()}' or column not defined yet"
                );
            }
            if (empty($relationName)) {
                $relationName = $config->getName();
            }
            static::$relations[$relationName] = $config;
            static::_loadColumnConfig($config->getLocalColumn())->addRelation($config, $relationName);
        }
        return static::$relations[$relationName];
    }

    /**
     * Run static::_loadRelationConfig() method for all static::$relations and return updated static::$relations
     * @return DbTableRelation[]
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static protected function _loadAllRelationsConfigs() {
        foreach (static::$relations as $relationName => $relation) {
            if (!($relation instanceof DbTableRelation)) {
                static::_loadRelationConfig($relationName);
            }
        }
        return static::$relations;
    }

    /**
     * Find PK column config (if not known yet) by loading all not-loaded column configs
     * @param bool $throwExceptionIfNotFound
     * @return null|DbTableColumn
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static protected function _findPkColumn($throwExceptionIfNotFound = true) {
        if (static::$pk === null) {
            foreach (static::$columns as $columnName => $column) {
                if (!($column instanceof DbTableColumn) && static::_loadColumnConfig($columnName)->isPk()) {
                    return static::$pk;
                }
            }
            if ($throwExceptionIfNotFound) {
                throw new \BadMethodCallException('Table has no primary key column or it is not defuned yet');
            }
        }
        return static::$pk;
    }

}