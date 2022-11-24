<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Config\Connection\DbConnectionsManager;
use PeskyORM\Exception\OrmException;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\TableDescription\TableDescribersRegistry;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\Utils\StringUtils;

abstract class TableStructure implements TableStructureInterface
{

    /**
     * Use table description from DB to automatically create missing column configs
     * @see TableDescribersRegistry::describeTable()
     */
    protected static bool $autodetectColumns = false;
    /**
     * Loads columns and relations configs from private methods of child class
     * Disable if you do not use private methods to define columns and relations
     */
    protected static bool $autoloadConfigsFromPrivateMethods = true;

    protected ?TableColumnInterface $pk = null;
    /**
     * @var TableColumnInterface[]
     */
    protected array $fileColumns = [];
    /**
     * @var TableColumnInterface[]
     */
    protected array $columsThatExistInDb = [];
    /**
     * @var TableColumnInterface[]
     */
    protected array $columsThatDoNotExistInDb = [];

    /**
     * It contains only ReflectionMethod objects after class instance created
     * Later it converts ReflectionMethod to TableColumn objects on demand
     * @var TableColumnInterface[]
     */
    protected array $columns = [];

    /**
     * Behaves like $columns
     * @var RelationInterface[]
     */
    protected array $relations = [];

    /**
     * @var RelationInterface[][]
     */
    protected array $columnsRelations = [];

    /** @var TableStructureInterface[] */
    private static array $instances = [];

    /**
     * @return static
     */
    final public static function getInstance(): TableStructureInterface
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
            self::$instances[static::class]->loadConfigs();
        }
        return self::$instances[static::class];
    }

    /**
     * Resets class instances (used for testing only, that's why it is private)
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function resetInstances(): void
    {
        self::$instances = [];
    }

    /**
     * @return static
     */
    final public static function i(): TableStructureInterface
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
    protected function loadConfigs(): void
    {
        if (static::$autoloadConfigsFromPrivateMethods) {
            $this->loadColumnsAndRelationsFromPrivateMethods();
        }
        if (static::$autodetectColumns) {
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
    protected function loadColumnsConfigs(): void
    {
    }

    /**
     * Load relations configs that are not loaded from private methods
     * Use $this->addRelation() to add relation config
     */
    protected function loadRelationsConfigs(): void
    {
    }

    /**
     * Analyze configs to collect some useful data
     */
    protected function analyze(): void
    {
        foreach ($this->relations as $relationConfig) {
            $this->_getColumn($relationConfig->getColumnName()); //< validate local column existance
            $this->columnsRelations[$relationConfig->getColumnName()][$relationConfig->getName(
            )] = $relationConfig;
        }
    }

    /**
     * Validate configs
     * @throws OrmException
     */
    protected function validate(): void
    {
        if ($this->pk === null) {
            $class = static::class;
            throw new OrmException(
                "TableStructure {$class} must contain primary key",
                OrmException::CODE_INVALID_TABLE_SCHEMA
            );
        }
    }

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     */
    public static function getConnectionName(bool $writable): string
    {
        return 'default';
    }

    public static function getSchema(): ?string
    {
        return null;
    }

    public static function hasColumn(string $columnName): bool
    {
        return static::getInstance()->_hasColumn($columnName);
    }

    public static function getColumn(string $columnName): TableColumnInterface
    {
        return static::getInstance()->_getColumn($columnName);
    }

    /**
     * @return TableColumnInterface[] - key = column name
     */
    public static function getColumns(): array
    {
        return static::getInstance()->columns;
    }

    public static function getPkColumnName(): ?string
    {
        return static::getPkColumn()?->getName();
    }

    public static function getPkColumn(): ?TableColumnInterface
    {
        return static::getInstance()->pk;
    }

    public static function hasPkColumn(): bool
    {
        return static::getInstance()->pk !== null;
    }

    public static function hasFileColumns(): bool
    {
        return count(static::getInstance()->fileColumns) > 0;
    }

    public static function hasFileColumn(string $columnName): bool
    {
        return (
            static::getInstance()->_hasColumn($columnName)
            && static::getInstance()
                     ->_getColumn($columnName)
                     ->isItAFile()
        );
    }

    /**
     * @return TableColumnInterface[] = array('column_name' => TableColumn)
     */
    public static function getFileColumns(): array
    {
        return static::getInstance()->fileColumns;
    }

    /**
     * @return TableColumnInterface[]
     */
    public static function getColumnsThatExistInDb(): array
    {
        return static::getInstance()->columsThatExistInDb;
    }

    /**
     * @return TableColumnInterface[]
     */
    public static function getColumnsThatDoNotExistInDb(): array
    {
        return static::getInstance()->columsThatDoNotExistInDb;
    }

    public static function hasRelation(string $relationName): bool
    {
        return static::getInstance()->_hasRelation($relationName);
    }

    public static function getRelation(string $relationName): RelationInterface
    {
        return static::getInstance()->_getRelation($relationName);
    }

    /**
     * @return RelationInterface[]
     */
    public static function getRelations(): array
    {
        return static::getInstance()->relations;
    }

    /**
     * @return RelationInterface[][]
     */
    public static function getColumnsRelations(): array
    {
        return static::getInstance()->columnsRelations;
    }

    /**
     * @return RelationInterface[]
     */
    public static function getColumnRelations(string $columnName): array
    {
        $instance = static::getInstance();
        $instance->_getColumn($columnName);
        return $instance->columnsRelations[$columnName] ?? [];
    }

    /**
     * Collects column configs from private methods where method name is column name or relation name.
     * TableColumn name must be a lowercased string starting from letter: private function parent_id() {}
     * Relation name must start from upper case letter: private function RelationName() {}
     * TableColumn method must return TableColumn object
     * Relation method must return Relation object
     */
    protected function loadColumnsAndRelationsFromPrivateMethods(): void
    {
        $objectReflection = new \ReflectionObject($this);
        $methods = $objectReflection->getMethods(\ReflectionMethod::IS_PRIVATE);
        $relationsMethods = [];
        foreach ($methods as $method) {
            if ($method->isStatic()) {
                continue;
            }
            $methodName = $method->getName();
            if (StringUtils::isSnakeCase($methodName)) {
                $this->loadColumnConfigFromMethodReflection($method);
            } elseif (StringUtils::isPascalCase($methodName)) {
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
    protected function createMissingColumnsConfigsFromDbTableDescription(): void
    {
        $description = $this->getTableDescription();
        foreach ($description->getColumns() as $columnName => $columnDescription) {
            if (!$this->_hasColumn($columnName)) {
                $column = TableColumn::create($columnDescription->getOrmType(), $columnName)
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
        return TableDescribersRegistry::describeTable(
            DbConnectionsManager::getConnection(static::getConnectionName(false)),
            static::getTableName(),
            static::getSchema()
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function _getColumn(string $columnName): TableColumnInterface
    {
        if (!$this->_hasColumn($columnName)) {
            $class = static::class;
            throw new \InvalidArgumentException("{$class} does not know about column named '{$columnName}'");
        }
        return $this->columns[$columnName];
    }

    /**
     * Make TableColumn object for private method (if not made yet) and return it
     * @throws OrmException
     */
    protected function loadColumnConfigFromMethodReflection(\ReflectionMethod $method): void
    {
        $method->setAccessible(true);
        /** @var TableColumnInterface $column */
        $column = $method->invoke($this);
        $method->setAccessible(false);
        if (!($column instanceof TableColumn)) {
            $class = static::class;
            throw new OrmException(
                "Method {$class}->{$method->getName()}() must return instance of \\PeskyORM\\ORM\\TableStructure\\TableColumn\\TableColumn class",
                OrmException::CODE_INVALID_TABLE_COLUMN_CONFIG
            );
        }
        if (!$column->hasName()) {
            $column->setName($method->getName());
        }
        $this->addColumn($column);
    }

    /**
     * @throws OrmException
     */
    protected function addColumn(TableColumnInterface $column): void
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
    protected function _getRelation(string $relationName): RelationInterface
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
     * @throws OrmException
     */
    protected function loadRelationConfigFromMethodReflection(\ReflectionMethod $method): void
    {
        $method->setAccessible(true);
        /** @var RelationInterface $config */
        $config = $method->invoke($this);
        $method->setAccessible(false);
        if (!($config instanceof RelationInterface)) {
            $class = static::class;
            throw new OrmException(
                "Method {$class}->{$method->getName()}() must return instance of \\PeskyORM\\ORM\\TableStructure\\Relation class",
                OrmException::CODE_INVALID_TABLE_RELATION_CONFIG
            );
        }
        if (!$config->hasName()) {
            $config->setName($method->getName());
        }
        $this->addRelation($config);
    }

    protected function addRelation(RelationInterface $relation): void
    {
        $this->relations[$relation->getName()] = $relation;
    }

    public function __get(string $name): TableColumnInterface|RelationInterface
    {
        if ($this->_hasRelation($name)) {
            return static::getRelation($name);
        }

        return static::getColumn($name);
    }

    /**
     * @throws \BadMethodCallException
     */
    public function __set(string $name, mixed $value)
    {
        throw new \BadMethodCallException('You need to use private methods to setup columns');
    }

    public function __isset(string $name): bool
    {
        return $this->_hasColumn($name) || $this->_hasRelation($name);
    }

}
