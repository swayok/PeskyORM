<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\Exception\TableStructureConfigException;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\TableDescription\TableDescriptionFacade;
use PeskyORM\TableDescription\TableDescriptionInterface;
use PeskyORM\Utils\ServiceContainer;
use Psr\Cache\CacheItemPoolInterface;

abstract class TableStructure implements TableStructureInterface
{
    protected const CACHE_SUBJECT_DESCRIPTION = 'description';

    /**
     * @var TableColumnInterface[]
     */
    protected array $columns = [];

    /**
     * @var RelationInterface[]
     */
    protected array $relations = [];

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
     * @var TableColumnInterface[]
     */
    protected array $notPrivateColumns = [];

    /**
     * @var TableColumnInterface[]
     */
    protected array $notHeavyColumns = [];

    /**
     * @var TableColumnInterface[]
     */
    protected array $realAutoupdatingColumns = [];

    /**
     * @var TableColumnInterface[]
     */
    protected array $realColumnsWhichValuesCanBeSavedToDb = [];

    /**
     * Contains all aliases for columns.
     * @see TableColumnInterface::getValueFormatersNames()
     * Structure: [
     *  'column_alias' => [
     *      'column' => TableColumnInterface,
     *      'format' => string
     *  ],
     *  ...
     * ]
     */
    protected array $columnsAliases = [];

    protected ?TableDescriptionInterface $tableDescription = null;

    public function __construct(
        protected ?CacheItemPoolInterface $cachePool = null,
        protected int $cacheDuration = 86400
    ) {
        $this->loadColumnsAndRelations();
    }

    /**
     * Load columns and relations configs
     */
    protected function loadColumnsAndRelations(): void
    {
        $this->registerColumns();
        $this->registerRelations();
        $this->analyze();
        $this->validate();
    }

    /**
     * Register real columns that are not loaded from private methods
     * Use $this->addColumn() to add column.
     * Use $this->importMissingColumnsConfigsFromDbTableDescription() to
     * import columns based on table schema received from DB.
     * @see self::addColumn()
     * @see self::importMissingColumnsConfigsFromDbTableDescription()
     */
    abstract protected function registerColumns(): void;

    /**
     * Register relations that are not loaded from private methods
     * Use $this->addRelation() to add relation.
     * @see self::addRelation()
     */
    abstract protected function registerRelations(): void;

    /**
     * Analyze configs to collect some useful data
     */
    protected function analyze(): void
    {
    }

    /**
     * Validate consistency of table structure
     * @throws TableStructureConfigException
     */
    protected function validate(): void
    {
        if ($this->pk === null) {
            $class = static::class;
            throw new TableStructureConfigException(
                "TableStructureOld {$class} must contain primary key",
                $this
            );
        }
    }

    protected function getConnectionName(bool $writable): string
    {
        return 'default';
    }

    public function getConnection(bool $writable = false): DbAdapterInterface
    {
        return DbConnectionsFacade::getConnection(
            $this->getConnectionName($writable)
        );
    }

    public function getSchema(): ?string
    {
        return null;
    }

    public function hasColumn(string $columnNameOrAlias): bool
    {
        return (
            isset($this->columns[$columnNameOrAlias])
            || isset($this->columnsAliases[$columnNameOrAlias])
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getColumn(string $columnNameOrAlias): TableColumnInterface
    {
        if (!$this->hasColumn($columnNameOrAlias)) {
            throw new \InvalidArgumentException(
                static::class . " does not know about column named '{$columnNameOrAlias}'"
            );
        }
        return $this->columns[$columnNameOrAlias]
            ?? $this->columnsAliases[$columnNameOrAlias]['column'];
    }

    #[ArrayShape([
        'column' => TableColumnInterface::class,
        'format' => 'null|string',
    ])]
    public function getColumnAndFormat(string $columnNameOrAlias): array
    {
        return [
            'column' => $this->getColumn($columnNameOrAlias),
            'format' => isset($this->columnsAliases[$columnNameOrAlias])
                ? $this->columnsAliases[$columnNameOrAlias]['format']
                : null,
        ];
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getPkColumnName(): string
    {
        return $this->pk->getName();
    }

    public function getPkColumn(): TableColumnInterface
    {
        return $this->pk;
    }

    public function getRealColumns(): array
    {
        return $this->realColumns;
    }

    public function getVirtualColumns(): array
    {
        return $this->virtualColumns;
    }

    public function getNotPrivateColumns(): array
    {
        return $this->notPrivateColumns;
    }

    public function getNotHeavyColumns(): array
    {
        return $this->notHeavyColumns;
    }

    public function getRealAutoupdatingColumns(): array
    {
        return $this->realAutoupdatingColumns;
    }

    public function getColumnsWhichValuesCanBeSavedToDb(): array
    {
        return $this->realColumnsWhichValuesCanBeSavedToDb;
    }

    public function hasRelation(string $relationName): bool
    {
        return isset($this->relations[$relationName]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getRelation(string $relationName): RelationInterface
    {
        if (!$this->hasRelation($relationName)) {
            throw new \InvalidArgumentException(
                static::class . " does not know about relation named '{$relationName}'"
            );
        }
        return $this->relations[$relationName];
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Use table description received from DB to automatically create column configs
     * Note: make sure to use it only once
     */
    protected function importMissingColumnsConfigsFromDbTableDescription(): void
    {
        $columnsFactory = ServiceContainer::getInstance()
            ->make(TableColumnFactory::class);
        foreach ($this->getTableDescription()->getColumns() as $columnDescription) {
            if (!$this->hasColumn($columnDescription->getName())) {
                $this->addColumn(
                    $columnsFactory->createFromDescription($columnDescription)
                );
            }
        }
    }

    /**
     * Override this method if you want to cache table description.
     * Use serialize($tableDescription) to save it to cache as string.
     * Use unserialize($cachedTableDescription) to restore into TableDescription object.
     */
    protected function getTableDescription(): TableDescriptionInterface
    {
        if (!$this->tableDescription) {
            $cacheKey = $this->getCacheKey(static::CACHE_SUBJECT_DESCRIPTION);
            $cacheItem = null;
            if ($this->cachePool) {
                $cacheItem = $this->cachePool->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $this->tableDescription = unserialize(
                        $this->cachePool->getItem($cacheKey)->get(),
                        [TableDescriptionInterface::class]
                    );
                    return $this->tableDescription;
                }
            }
            $this->tableDescription = TableDescriptionFacade::describeTable(
                $this->getConnection(false),
                $this->getTableName(),
                $this->getSchema()
            );
            $cacheItem?->expiresAfter($this->cacheDuration)
                ->set(serialize($this->tableDescription));
        }
        return $this->tableDescription;
    }

    protected function getCacheKey(string $subject): string
    {
        return 'db/table/' . $this->getTableName() . '/' . $subject;
    }

    public function cleanCache(): void
    {
        $this->cachePool?->deleteItem(
            $this->getCacheKey(static::CACHE_SUBJECT_DESCRIPTION)
        );
    }

    /**
     * @throws TableStructureConfigException
     */
    protected function addColumn(TableColumnInterface $column): void
    {
        $this->columns[$column->getName()] = $column;
        if ($column->isPrimaryKey()) {
            if (!empty($this->pk)) {
                $class = static::class;
                throw new TableStructureConfigException(
                    '2 primary keys in one table is forbidden:'
                    . " '{$this->pk->getName()}' and '{$column->getName()}' (class: {$class})",
                    $this
                );
            }
            $this->pk = $column;
        }
        if ($column->isReal()) {
            $this->realColumns[$column->getName()] = $column;
            if ($column->isAutoUpdatingValues()) {
                $this->realAutoupdatingColumns[$column->getName()] = $column;
            }
            if (!$column->isReadonly()) {
                $this->realColumnsWhichValuesCanBeSavedToDb[$column->getName()] = $column;
            }
        } else {
            $this->virtualColumns[$column->getName()] = $column;
        }
        if (!$column->isPrivateValues()) {
            $this->notPrivateColumns[$column->getName()] = $column;
        }
        if (!$column->isHeavyValues()) {
            $this->notHeavyColumns[$column->getName()] = $column;
        }

        foreach ($column->getValueFormatersNames() as $columnNameAlias => $format) {
            $this->columnsAliases[$columnNameAlias] = [
                'column' => $column,
                'format' => $format,
            ];
        }
    }

    protected function addRelation(RelationInterface $relation): void
    {
        // validate local column existance and attach relation to it
        $column = $this->getColumn($relation->getLocalColumnName());
        $column->addRelation($relation);
        $this->relations[$relation->getName()] = $relation;
    }
}
