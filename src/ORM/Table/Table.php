<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use PeskyORM\DbExpr;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\RecordsCollection\SelectedRecordsCollectionInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\OrmSelectQueryBuilderInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\PdoUtils;
use PeskyORM\Utils\ServiceContainer;
use PeskyORM\Utils\StringUtils;

class Table implements TableInterface, TableStructureInterface
{
    use DelegateTableStructureMethods;

    protected ?string $tableAlias = null;

    protected TableStructureInterface $tableStructure;
    protected \Closure $recordFactory;

    public static function getInstance(): static
    {
        $container = ServiceContainer::getInstance();
        if (!$container->has(static::class)) {
            $instance = new static();
            $container->instance(static::class, $instance);
            return $instance;
        }
        return $container->make(static::class);
    }

    public function __construct(
        TableStructureInterface $tableStructure,
        string $recordClass,
        ?string $tableAlias = null
    ) {
        $this->tableStructure = $tableStructure;
        ArgumentValidators::assertClassImplementsInterface(
            '$recordClass',
            $recordClass,
            RecordInterface::class
        );
        if ($recordClass === Record::class) {
            // Record class constructor require table instance argument
            $this->recordFactory = function () use ($recordClass): RecordInterface {
                return ServiceContainer::getInstance()->make($recordClass, [$this]);
            };
        } else {
            // Specific record class should override constructor to exclude
            // table instance argument.
            $this->recordFactory = static function () use ($recordClass): RecordInterface {
                return ServiceContainer::getInstance()->make($recordClass);
            };
        }
        $this->tableAlias = $tableAlias
            ?? StringUtils::toPascalCase($tableStructure->getTableName());
    }

    public function getTableStructure(): TableStructureInterface
    {
        return $this->tableStructure;
    }

    public function newRecord(): RecordInterface
    {
        return ($this->recordFactory)();
    }

    public function getTableAlias(): string
    {
        return $this->tableAlias;
    }

    public function getPkColumnName(): string
    {
        return $this->getTableStructure()->getPkColumnName();
    }

    public function getPkColumn(): TableColumnInterface
    {
        return $this->getTableStructure()->getPkColumn();
    }

    public function getJoinConfigForRelation(
        string|RelationInterface $relation,
        string $alterLocalTableAlias = null,
        string $joinName = null
    ): NormalJoinConfigInterface {
        if (is_string($relation)) {
            $relation = $this->getRelation($relation);
        }
        return $relation->toJoinConfig($alterLocalTableAlias, $joinName);
    }

    public function getExpressionToSetDefaultValueForAColumn(): DbExpr
    {
        return $this->getConnection(true)
            ->getExpressionToSetDefaultValueForAColumn();
    }

    public static function makeQueryBuilder(
        array|string $columns,
        array $conditionsAndOptions = [],
        ?\Closure $configurator = null
    ): OrmSelectQueryBuilderInterface {
        /** @var OrmSelectQueryBuilderInterface $select */
        $select = ServiceContainer::getInstance()->make(
            OrmSelectQueryBuilderInterface::class,
            [static::getInstance()]
        );
        $select->fromConfigsArray($conditionsAndOptions);
        if ($configurator !== null) {
            $configurator($select);
        }
        $select->columns($columns);
        return $select;
    }

    public static function select(
        string|array $columns = '*',
        array $conditions = [],
        ?\Closure $configurator = null
    ): SelectedRecordsCollectionInterface {
        return ServiceContainer::getInstance()->make(
            SelectedRecordsCollectionInterface::class,
            [
                static::getInstance(),
                static::makeQueryBuilder($columns, $conditions, $configurator),
            ]
        );
    }

    public static function selectColumn(
        string|DbExpr $column,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array {
        return static::makeQueryBuilder([], $conditions, $configurator)
            ->fetchColumn($column);
    }

    public static function selectAssoc(
        string|DbExpr|null $keysColumn,
        string|DbExpr|null $valuesColumn,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array {
        return static::makeQueryBuilder([], $conditions, $configurator)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    public static function selectOne(
        string|array $columns,
        array $conditions,
        ?\Closure $configurator = null
    ): array {
        return static::makeQueryBuilder($columns, $conditions, $configurator)
            ->fetchOne();
    }

    public static function selectOneAsDbRecord(
        string|array $columns,
        array $conditions,
        ?\Closure $configurator = null
    ): RecordInterface {
        return static::makeQueryBuilder($columns, $conditions, $configurator)
            ->fetchOneAsDbRecord();
    }

    public static function selectValue(
        DbExpr $expression,
        array $conditions = [],
        ?\Closure $configurator = null
    ): mixed {
        return static::makeQueryBuilder([], $conditions, $configurator)
            ->fetchValue($expression);
    }

    public static function selectColumnValue(
        string|TableColumnInterface $column,
        array $conditions = [],
        ?\Closure $configurator = null
    ): mixed {
        if (is_string($column)) {
            $column = static::getInstance()->getColumn($column);
        }
        return static::selectValue(
            DbExpr::create("`{$column->getName()}`"),
            $conditions,
            $configurator
        );
    }

    public static function hasMatchingRecord(
        array $conditions,
        ?\Closure $configurator = null
    ): bool {
        $callback = static function (
            SelectQueryBuilderInterface $select
        ) use ($configurator) {
            if ($configurator) {
                $configurator($select);
            }
            $select->offset(0)
                ->limit(1)
                ->removeOrdering();
        };
        return (int)static::selectValue(DbExpr::create('1'), $conditions, $callback) === 1;
    }

    public static function count(
        array $conditions = [],
        ?\Closure $configurator = null,
        bool $removeNotInnerJoins = false
    ): int {
        return static::makeQueryBuilder(
            [static::getInstance()->getPkColumnName()],
            $conditions,
            $configurator
        )->fetchCount($removeNotInnerJoins);
    }

    public static function getLastQuery(bool $useWritableConnection): ?string
    {
        try {
            return static::getInstance()
                ->getConnection($useWritableConnection)
                ->getLastQuery();
        } catch (\Exception $exception) {
            return $exception->getMessage() . '. ' . $exception->getTraceAsString();
        }
    }

    public static function beginTransaction(
        bool $readOnly = false,
        ?string $transactionType = null
    ): void {
        static::getInstance()
            ->getConnection(true)
            ->begin($readOnly, $transactionType);
    }

    public static function inTransaction(): bool
    {
        return static::getInstance()
            ->getConnection(true)
            ->inTransaction();
    }

    public static function commitTransaction(): void
    {
        static::getInstance()
            ->getConnection(true)
            ->commit();
    }

    public static function rollBackTransaction(bool $onlyIfExists = false): void
    {
        if ($onlyIfExists && !static::inTransaction()) {
            return;
        }
        static::getInstance()
            ->getConnection(true)
            ->rollBack();
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteDbEntityName()
     */
    public static function quoteDbEntityName(string $name): string
    {
        return static::getInstance()
            ->getConnection(true)
            ->quoteDbEntityName($name);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteValue()
     */
    public static function quoteValue($value, int $fieldInfoOrType = \PDO::PARAM_STR): string
    {
        return static::getInstance()
            ->getConnection(true)
            ->quoteValue($value, $fieldInfoOrType);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteDbExpr()
     */
    public static function quoteDbExpr(DbExpr $value): string
    {
        return static::getInstance()
            ->getConnection(true)
            ->quoteDbExpr($value);
    }

    public static function query(
        string|DbExpr $query,
        string $fetchData = PdoUtils::FETCH_STATEMENT
    ): mixed {
        return static::getInstance()
            ->getConnection(true)
            ->query($query, $fetchData);
    }

    public static function exec(string|DbExpr $query): int
    {
        return static::getInstance()
            ->getConnection(true)
            ->exec($query);
    }

    public static function insert(
        array $data,
        array|bool $returning = false,
        bool $valuesAreProcessed = true
    ): ?array {
        $table = static::getInstance();
        $pkName = $table->getPkColumnName();
        if (!$valuesAreProcessed) {
            $data = $table->newRecord()
                ->fromData($data, isset($data[$pkName]))
                ->getValuesForInsertQuery(array_keys($data));
        }
        return $table->getConnection(true)
            ->insert(
                $table->getNameWithSchema(),
                $data,
                $table->getPdoDataTypesForColumns(),
                $returning,
                $pkName
            );
    }

    public static function upsert(array $data, array $uniqueColumnNames): RecordInterface
    {
        ArgumentValidators::assertNotEmpty('$uniqueColumnNames', $uniqueColumnNames);
        $record = static::getInstance()->newRecord();
        $conditions = [];
        foreach ($uniqueColumnNames as $index => $columnName) {
            ArgumentValidators::assertNotEmptyString(
                "\$uniqueColumnNames[{$index}]",
                $columnName,
                true
            );
            ArgumentValidators::assertArrayKeyValueIsNotEmpty(
                "\$data[{$columnName}]",
                $data[$columnName] ?? null
            );
            // validate and normalize value
            $uniqueValue = $record->updateValue($columnName, $data[$columnName], false)
                ->getValue($columnName);
            $conditions[$columnName] = $uniqueValue;
        }

        $record->fetch($conditions);
        if ($record->existsInDb()) {
            $record->begin()
                ->updateValues($data, false)
                ->commit();
        } else {
            $record->reset()
                ->fromData($data)
                ->save();
        }
        return $record;
    }

    public static function insertMany(
        array $columns,
        array $rows,
        array|bool $returning = false,
        bool $valuesAreProcessed = true
    ): ?array {
        $table = static::getInstance();
        if (!$valuesAreProcessed) {
            $rows = $table->prepareDataForInsertMany($rows, $columns);
        }
        return $table->getConnection(true)
            ->insertMany(
                $table->getNameWithSchema(),
                $columns,
                $rows,
                $table->getPdoDataTypesForColumns($columns),
                $returning,
                $table->getPkColumnName()
            );
    }

    public static function update(array $data, array $conditions, array|bool $returning = false): array|int
    {
        $table = static::getInstance();
        return $table->getConnection(true)
            ->update(
                $table->getNameWithSchema() . ' AS ' . $table->getTableAlias(),
                $data,
                $conditions,
                $table->getPdoDataTypesForColumns(),
                $returning,
                $table->getPkColumnName()
            );
    }

    public static function delete(array $conditions = [], array|bool $returning = false): array|int
    {
        $table = static::getInstance();
        return $table->getConnection(true)
            ->delete(
                $table->getNameWithSchema() . ' AS ' . $table->getTableAlias(),
                $conditions,
                $returning,
                $table->getPkColumnName()
            );
    }

    /**
     * Table name with schema.
     * Example: 'public.users'
     * @return string
     */
    protected function getNameWithSchema(): string
    {
        return ltrim(
            $this->getSchema() . '.' . $this->getTableName(),
            '.'
        );
    }

    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     */
    protected function getPdoDataTypesForColumns(array $columns = []): array
    {
        $pdoDataTypes = [];
        if (empty($columns)) {
            $columns = array_keys($this->getColumns());
        }
        foreach ($columns as $columnName) {
            $columnInfo = $this->getColumn($columnName);
            $pdoDataTypes[$columnInfo->getName()] = match ($columnInfo->getDataType()) {
                TableColumnDataType::BOOL => \PDO::PARAM_BOOL,
                TableColumnDataType::INT => \PDO::PARAM_INT,
                TableColumnDataType::BLOB => \PDO::PARAM_LOB,
                default => \PDO::PARAM_STR,
            };
        }
        return $pdoDataTypes;
    }

    /**
     * Alter $rows to honor special columns features like values trimming, autoupdates, etc.
     * Missing values will be filled with defaults.
     * @see RecordInterface::getValuesForInsertQuery()
     */
    protected function prepareDataForInsertMany(
        array $rows,
        array $columnsToSave = []
    ): array {
        if (empty($columnsToSave)) {
            foreach ($this->getColumns() as $column) {
                if ($column->isReal() && !$column->isReadonly()) {
                    $columnsToSave[] = $column->getName();
                }
            }
        }
        $record = $this->newRecord();
        $pkColumnName = $this->getPkColumnName();
        array_walk(
            $rows,
            static function (&$row, $index) use ($pkColumnName, $columnsToSave, $record) {
                if ($row instanceof RecordInterface) {
                    $row = $row->getValuesForInsertQuery($columnsToSave);
                } else {
                    ArgumentValidators::assertArrayKeyValueIsArray("\$rows[{$index}]", $row);
                    $record->fromData($row, isset($row[$pkColumnName]));
                    $row = $record->getValuesForInsertQuery($columnsToSave);
                    $record->reset();
                }
            }
        );
        return $rows;
    }

}
