<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Config\Connection\DbConnectionsManager;
use PeskyORM\Exception\OrmException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\TableDescription\ColumnDescriptionInterface;
use PeskyORM\TableDescription\TableDescribersRegistry;
use PeskyORM\TableDescription\TableDescriptionInterface;
use PeskyORM\Utils\StringUtils;

abstract class TableStructure implements TableStructureInterface
{
    /**
     * Use table description from DB to automatically create missing column configs
     * @see TableDescribersRegistry::describeTable()
     */
    protected bool $autodetectColumns = false;
    /**
     * Loads columns and relations configs from private methods of child class
     * Disable if you do not use private methods to define columns and relations
     */
    protected bool $autoloadConfigsFromPrivateMethods = true;

    protected ?TableColumnInterface $pk = null;
    /**
     * @var TableColumnInterface[]
     */
    protected array $realColumns = [];
    /**
     * @var TableColumnInterface[]
     */
    protected array $virtualColumns = [];

    /**
     * It contains only ReflectionMethod objects after class instance created
     * Later it converts ReflectionMethod to TableColumnInterface instances on demand
     * @var TableColumnInterface[]
     */
    protected array $columns = [];

    /**
     * Behaves like $columns
     * @var RelationInterface[]
     */
    protected array $relations = [];

    /** @var TableStructureInterface[] */
    private static array $instances = [];

    final public static function getInstance(): TableStructureInterface
    {
        if (!isset(self::$instances[static::class])) {
            new static();
        }
        return self::$instances[static::class];
    }

    /**
     * Resets class instances (used for testing only, that's why it is private)
     * @noinspection PhpUnusedPrivateMethodInspection
     * todo: move to instances container
     */
    private static function resetInstances(): void
    {
        self::$instances = [];
    }

    /**
     * @throws \BadMethodCallException
     */
    final private function __construct()
    {
        if (isset(self::$instances[static::class])) {
            throw new \BadMethodCallException(
                'Attempt to create 2nd instance of class ' . static::class
            );
        }
        self::$instances[static::class] = $this;
        $this->loadColumnsAndRelations();
    }

    /**
     * Load columns and relations configs
     */
    protected function loadColumnsAndRelations(): void
    {
        $this->loadColumnsAndRelationsFromPrivateMethods();
        $this->registerColumns();
        $this->createMissingColumnsConfigsFromDbTableDescription();
        $this->registerRelations();
        $this->registerVirtualColumns(); // virtual columns may use real columns
        $this->analyze();
        $this->validate();
    }

    /**
     * Register real columns that are not loaded from private methods
     * Use $this->addColumn() to add column
     */
    protected function registerColumns(): void
    {
    }

    /**
     * Register columns that do not exist in DB (virtual columns)
     * and are not loaded from private methods
     * Use $this->addColumn() to add column
     */
    protected function registerVirtualColumns(): void
    {
    }

    /**
     * Register relations that are not loaded from private methods
     * Use $this->addRelation() to add relation
     */
    protected function registerRelations(): void
    {
    }

    /**
     * Analyze configs to collect some useful data
     */
    protected function analyze(): void
    {
    }

    /**
     * Validate consistency of table structure
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

    public static function getRealColumns(): array
    {
        return static::getInstance()->realColumns;
    }

    public static function getVirtualColumns(): array
    {
        return static::getInstance()->virtualColumns;
    }

    public static function hasRelation(string $relationName): bool
    {
        return static::getInstance()->_hasRelation($relationName);
    }

    public static function getRelation(string $relationName): RelationInterface
    {
        return static::getInstance()->_getRelation($relationName);
    }

    public static function getRelations(): array
    {
        return static::getInstance()->relations;
    }

    /**
     * Collects column configs from private methods where method name is column name or relation name.
     * Column name must be a lowercased string starting from letter: private function parent_id() {}
     * Relation name must start from upper case letter: private function RelationName() {}
     * Column method must return TableColumnInterface object
     * Relation method must return RelationInterface object
     */
    protected function loadColumnsAndRelationsFromPrivateMethods(): void
    {
        if (!$this->autoloadConfigsFromPrivateMethods) {
            return;
        }
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
        $this->createMissingColumnsConfigsFromDbTableDescription();
        // relations must be loaded last to prevent possible issues when Relation requests column which id not loaded yet
        foreach ($relationsMethods as $method) {
            $this->loadRelationConfigFromMethodReflection($method);
        }
        $this->autoloadConfigsFromPrivateMethods = false;
    }

    /**
     * Use table description received from DB to automatically create column configs
     */
    protected function createMissingColumnsConfigsFromDbTableDescription(): void
    {
        if (!$this->autodetectColumns) {
            return;
        }
        $description = $this->getTableDescription();
        foreach ($description->getColumns() as $columnName => $columnDescription) {
            if (!$this->_hasColumn($columnName)) {
                $this->addColumnFromColumnDescription($columnName, $columnDescription);
            }
        }
        $this->autodetectColumns = false;
    }

    protected function addColumnFromColumnDescription(
        string $columnName,
        ColumnDescriptionInterface $columnDescription
    ): void {
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

    /**
     * Override this method if you want to cache table description.
     * Use serialize($tableDescription) to save it to cache as string.
     * Use unserialize($cachedTableDescription) to restore into TableDescription object.
     */
    protected function getTableDescription(): TableDescriptionInterface
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
            throw new \InvalidArgumentException(
                static::class . " does not know about column named '{$columnName}'"
            );
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
            throw new TableColumnConfigException(
                "Method {$class}->{$method->getName()}() must return an instance of class that implements "
                . TableColumnInterface::class,
                null
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
        $this->columns[$column->getName()] = $column;
        if ($column->isPrimaryKey()) {
            if (!empty($this->pk)) {
                $class = static::class;
                throw new TableColumnConfigException(
                    '2 primary keys in one table is forbidden:'
                    . " '{$this->pk->getName()}' and '{$column->getName()}' (class: {$class})",
                    $column
                );
            }
            $this->pk = $column;
        }
        if ($column->isReal()) {
            $this->realColumns[$column->getName()] = $column;
        } else {
            $this->virtualColumns[$column->getName()] = $column;
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
            throw new \InvalidArgumentException(
                static::class . " does not know about relation named '{$relationName}'"
            );
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
                "Method {$class}->{$method->getName()}() must return an instance of class that implements "
                . RelationInterface::class,
                OrmException::CODE_INVALID_TABLE_RELATION_CONFIG
            );
        }
        $config->setName($method->getName());
        $this->addRelation($config);
    }

    protected function addRelation(RelationInterface $relation): void
    {
        // validate local column existance and attach relation to it
        $column = $this->_getColumn($relation->getLocalColumnName());
        $column->addRelation($relation);
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
