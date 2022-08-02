<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\JoinInfo;
use PeskyORM\Core\TableDescription;
use PeskyORM\Exception\OrmException;

abstract class TableStructure implements TableStructureInterface
{
    
    /**
     * Use table description from DB to automatically create missing column configs
     * It uses PeskyORM\Core\DbConnectionsManager::getConnection(static::$connectionName)->describeTable(static::$name)
     * @var bool
     */
    static protected $autodetectColumnsConfigs = false;
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
    
    /** @var TableStructureInterface[] */
    static private $instances = [];
    
    /**
     * @return $this
     */
    final static public function getInstance(): TableStructureInterface
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
            self::$instances[static::class]->loadConfigs();
        }
        return self::$instances[static::class];
    }
    
    /**
     * @return $this
     */
    final static public function i(): TableStructureInterface
    {
        return static::getInstance();
    }
    
    /**
     * Use loadConfigs() method to do anything you wanted to do via constructor overwrite
     * @throws \BadMethodCallException
     */
    final protected function __construct()
    {
        if (isset(self::$instances[static::class])) {
            throw new \BadMethodCallException('Attempt to create 2nd instance of class ' . static::class);
        }
    }
    
    /**
     * Load columns and relations configs
     */
    protected function loadConfigs()
    {
        if (static::$autoloadConfigsFromPrivateMethods) {
            $this->loadColumnsConfigsFromPrivateMethods();
        }
        if (static::$autodetectColumnsConfigs) {
            $this->createMissingColumnsConfigsFromDbTableDescription();
        }
        $this->loadColumnsConfigs(); //< this is correct place - here virtual columns may use real columns
        $this->loadRelationsConfigs();
        $this->analyze();
        $this->validate();
    }
    
    /**
     * Load columns configs that are not loaded from private methods
     * Use $this->addColumn() to add column config
     */
    protected function loadColumnsConfigs()
    {
    }
    
    /**
     * Load relations configs that are not loaded from private methods
     * Use $this->addRelation() to add relation config
     */
    protected function loadRelationsConfigs()
    {
    }
    
    /**
     * Analyze configs to collect some useful data
     */
    protected function analyze()
    {
        foreach ($this->relations as $relationConfig) {
            $this->_getColumn($relationConfig->getLocalColumnName()); //< validate local column existance
            $this->columnsRelations[$relationConfig->getLocalColumnName()][$relationConfig->getName()] = $relationConfig;
        }
    }
    
    /**
     * Validate configs
     * @throws OrmException
     */
    protected function validate()
    {
        if ($this->pk === null) {
            $class = static::class;
            throw new OrmException("TableStructure {$class} must contain primary key", OrmException::CODE_INVALID_TABLE_SCHEMA);
        }
    }
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     */
    static public function getConnectionName(bool $writable): string
    {
        return 'default';
    }
    
    static public function getSchema(): ?string
    {
        return null;
    }
    
    static public function hasColumn(string $columnName): bool
    {
        return static::getInstance()
            ->_hasColumn($columnName);
    }
    
    static public function getColumn(string $columnName): Column
    {
        return static::getInstance()
            ->_getColumn($columnName);
    }
    
    /**
     * @return Column[] - key = column name
     */
    static public function getColumns(): array
    {
        return static::getInstance()->columns;
    }
    
    static public function getPkColumnName(): ?string
    {
        $column = static::getPkColumn();
        return $column ? $column->getName() : null;
    }
    
    static public function getPkColumn(): ?Column
    {
        return static::getInstance()->pk;
    }
    
    static public function hasPkColumn(): bool
    {
        return static::getInstance()->pk !== null;
    }
    
    static public function hasFileColumns(): bool
    {
        return count(static::getInstance()->fileColumns) > 0;
    }
    
    static public function hasFileColumn(string $columnName): bool
    {
        return (
            static::getInstance()->_hasColumn($columnName)
            && static::getInstance()
                ->_getColumn($columnName)
                ->isItAFile()
        );
    }
    
    /**
     * @return Column[] = array('column_name' => Column)
     */
    static public function getFileColumns(): array
    {
        return static::getInstance()->fileColumns;
    }
    
    /**
     * @return Column[]
     */
    static public function getColumnsThatExistInDb(): array
    {
        return static::getInstance()->columsThatExistInDb;
    }
    
    /**
     * @return Column[]
     */
    static public function getColumnsThatDoNotExistInDb(): array
    {
        return static::getInstance()->columsThatDoNotExistInDb;
    }
    
    static public function hasRelation(string $relationName): bool
    {
        return static::getInstance()
            ->_hasRelation($relationName);
    }
    
    static public function getRelation(string $relationName): Relation
    {
        return static::getInstance()
            ->_getRelation($relationName);
    }
    
    /**
     * @return Relation[]
     */
    static public function getRelations(): array
    {
        return static::getInstance()->relations;
    }
    
    /**
     * @return Relation[]
     */
    static public function getColumnRelations(string $columnName): array
    {
        $instance = static::getInstance();
        $instance->_getColumn($columnName);
        return $instance->columnsRelations[$columnName] ?? [];
    }
    
    /**
     * Collects column configs from private methods where method name is column name or relation name.
     * Column name must be a lowercased string starting from letter: private function parent_id() {}
     * Relation name must start from upper case letter: private function RelationName() {}
     * Column method must return Column object
     * Relation method must return Relation object
     */
    protected function loadColumnsConfigsFromPrivateMethods()
    {
        $objectReflection = new \ReflectionObject($this);
        $methods = $objectReflection->getMethods(\ReflectionMethod::IS_PRIVATE);
        $relationsMethods = [];
        foreach ($methods as $method) {
            if ($method->isStatic()) {
                continue;
            }
            if (preg_match(Column::NAME_VALIDATION_REGEXP, $method->getName())) {
                $this->loadColumnConfigFromMethodReflection($method);
            } elseif (preg_match(JoinInfo::NAME_VALIDATION_REGEXP, $method->getName())) {
                $relationsMethods[] = $method;
            }
        }
        // relations must be loaded last to prevent possible issues when Relation requests column which id not loaded yet
        foreach ($relationsMethods as $method) {
            $this->loadRelationConfigFromMethodReflection($method);
        }
    }
    
    /**
     * Use table description received from DB to automatically create column configs
     */
    protected function createMissingColumnsConfigsFromDbTableDescription()
    {
        $description = $this->getTableDescription();
        foreach ($description->getColumns() as $columnName => $columnDescription) {
            if (!$this->_hasColumn($columnName)) {
                $column = Column::create($columnDescription->getOrmType(), $columnName)
                    ->setIsNullableValue($columnDescription->isNullable());
                if ($columnDescription->isPrimaryKey()) {
                    $column->primaryKey();
                }
                if ($columnDescription->isUnique()) {
                    $column->uniqueValues();
                }
                if ($columnDescription->getDefault() !== null) {
                    $column->setDefaultValue($columnDescription->getDefault());
                }
                $this->addColumn($column);
            }
        }
    }
    
    /**
     * Override this method if you want to cache table description.
     * Use serialize($tableDescription) to save it to cache as string.
     * Use unserialize($cachedTableDescription) to restore into TableDescription object.
     */
    protected function getTableDescription(): TableDescription
    {
        return DbConnectionsManager::getConnection(static::getConnectionName(false))
            ->describeTable(static::getTableName(), static::getSchema());
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function _getColumn(string $columnName): Column
    {
        if (!$this->_hasColumn($columnName)) {
            $class = static::class;
            throw new \InvalidArgumentException("{$class} does not know about column named '{$columnName}'");
        }
        return $this->columns[$columnName];
    }
    
    /**
     * Make Column object for private method (if not made yet) and return it
     * @throws OrmException
     */
    protected function loadColumnConfigFromMethodReflection(\ReflectionMethod $method)
    {
        $method->setAccessible(true);
        /** @var Column $column */
        $column = $method->invoke($this);
        $method->setAccessible(false);
        if (!($column instanceof Column)) {
            $class = static::class;
            throw new OrmException(
                "Method {$class}->{$method->getName()}() must return instance of \\PeskyORM\\ORM\\Column class",
                OrmException::CODE_INVALID_TABLE_COLUMN_CONFIG
            );
        }
        if (!$column->hasName()) {
            $column->setName($method->getName());
        }
        $this->addColumn($column);
    }
    
    /**
     * @return void
     * @throws OrmException
     */
    protected function addColumn(Column $column)
    {
        $column->setTableStructure($this);
        $this->columns[$column->getName()] = $column;
        if ($column->isItPrimaryKey()) {
            if (!empty($this->pk)) {
                $class = static::class;
                throw new OrmException(
                    "2 primary keys in one table is forbidden: '{$this->pk->getName()}' and '{$column->getName()}' (class: {$class})",
                    OrmException::CODE_INVALID_TABLE_COLUMN_CONFIG
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
    
    protected function _hasColumn(string $columnName): bool
    {
        return isset($this->columns[$columnName]);
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function _getRelation(string $relationName): Relation
    {
        if (!$this->_hasRelation($relationName)) {
            $class = static::class;
            throw new \InvalidArgumentException("{$class} does not know about relation named '{$relationName}'");
        }
        return $this->relations[$relationName];
    }
    
    protected function _hasRelation(string $relationName): bool
    {
        return isset($this->relations[$relationName]);
    }
    
    /**
     * Make Relation object for private method (if not made yet) and return it
     * @return void
     * @throws OrmException
     */
    protected function loadRelationConfigFromMethodReflection(\ReflectionMethod $method)
    {
        $method->setAccessible(true);
        /** @var Relation $config */
        $config = $method->invoke($this);
        $method->setAccessible(false);
        if (!($config instanceof Relation)) {
            $class = static::class;
            throw new OrmException(
                "Method {$class}->{$method->getName()}() must return instance of \\PeskyORM\\ORM\\Relation class",
                OrmException::CODE_INVALID_TABLE_RELATION_CONFIG
            );
        }
        if (!$config->hasName()) {
            $config->setName($method->getName());
        }
        $this->addRelation($config);
    }
    
    protected function addRelation(Relation $relation)
    {
        $this->relations[$relation->getName()] = $relation;
    }
    
    /**
     * @param string $name
     * @return Column|Relation
     */
    public function __get($name)
    {
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
    public function __set($name, $value)
    {
        throw new \BadMethodCallException('You need to use private methods to setup columns');
    }
    
    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->_hasColumn($name) || $this->_hasRelation($name);
    }
    
    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances()
    {
        self::$instances = [];
    }
    
}
