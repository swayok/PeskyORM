<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsManager;
use PeskyORM\DbExpr;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\PdoUtils;
use PeskyORM\Utils\StringUtils;

abstract class Table implements TableInterface
{
    /** @var TableInterface[] */
    private static array $instances = [];
    protected ?string $alias = null;

    /**
     * @return static
     */
    final public static function getInstance(): TableInterface
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
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

    public static function getName(): string
    {
        return static::getStructure()->getTableName();
    }

    public static function getConnection(bool $writable = false): DbAdapterInterface
    {
        return DbConnectionsManager::getConnection(
            static::getStructure()->getConnectionName($writable)
        );
    }

    public static function getStructure(): TableStructureInterface
    {
        return static::getInstance()->getTableStructure();
    }

    public static function getAlias(): string
    {
        return static::getInstance()->getTableAlias();
    }

    public function getTableAlias(): string
    {
        if (!$this->alias || !is_string($this->alias)) {
            $this->alias = StringUtils::toPascalCase(static::getName());
        }
        return $this->alias;
    }

    public static function getPkColumn(): TableColumnInterface
    {
        return static::getStructure()->getPkColumn();
    }

    public static function getPkColumnName(): string
    {
        return static::getStructure()->getPkColumnName();
    }

    /**
     * Get OrmJoinConfig for required relation
     */
    public static function getJoinConfigForRelation(
        string|RelationInterface $relation,
        string $alterLocalTableAlias = null,
        string $joinName = null
    ): NormalJoinConfigInterface {
        if (is_string($relation)) {
            $relation = static::getStructure()::getRelation($relation);
        }
        return $relation->toJoinConfig($alterLocalTableAlias, $joinName);
    }

    /**
     * {@inheritDoc}
     * @throws \BadMethodCallException
     */
    public static function getExpressionToSetDefaultValueForAColumn(): DbExpr
    {
        if (static::class === __CLASS__) {
            throw new \BadMethodCallException(
                'Trying to call abstract method ' . __CLASS__ . '::getConnection(). Use child classes to do that'
            );
        }
        return static::getConnection(true)->getExpressionToSetDefaultValueForAColumn();
    }

    public static function makeQueryBuilder(
        array|string $columns,
        array $conditions = [],
        ?\Closure $configurator = null
    ): OrmSelect {
        $select = OrmSelect::from(static::getInstance())
            ->fromConfigsArray($conditions);
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
    ): RecordsSet {
        return RecordsSet::createFromOrmSelect(
            static::makeQueryBuilder($columns, $conditions, $configurator)
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

    public static function selectOne(string|array $columns, array $conditions, ?\Closure $configurator = null): array
    {
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
            $column = static::getStructure()->getColumn($column);
        }
        return static::selectValue(
            DbExpr::create("`{$column->getName()}`"),
            $conditions,
            $configurator
        );
    }

    public static function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool
    {
        $callback = static function (OrmSelect $select) use ($configurator) {
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
            [static::getPkColumnName()],
            $conditions,
            $configurator
        )->fetchCount($removeNotInnerJoins);
    }

    public static function getLastQuery(bool $useWritableConnection): ?string
    {
        try {
            return static::getConnection($useWritableConnection)->getLastQuery();
        } catch (\Exception $exception) {
            return $exception->getMessage() . '. ' . $exception->getTraceAsString();
        }
    }

    public static function beginTransaction(bool $readOnly = false, ?string $transactionType = null): void
    {
        static::getConnection(true)->begin($readOnly, $transactionType);
    }

    public static function inTransaction(): bool
    {
        return static::getConnection(true)->inTransaction();
    }

    public static function commitTransaction(): void
    {
        static::getConnection(true)->commit();
    }

    public static function rollBackTransaction(bool $onlyIfExists = false): void
    {
        if ($onlyIfExists && !static::inTransaction()) {
            return;
        }
        static::getConnection(true)->rollBack();
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteDbEntityName()
     */
    public static function quoteDbEntityName(string $name): string
    {
        return static::getConnection(true)->quoteDbEntityName($name);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteValue()
     */
    public static function quoteValue($value, int $fieldInfoOrType = \PDO::PARAM_STR): string
    {
        return static::getConnection(true)->quoteValue($value, $fieldInfoOrType);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::quoteDbExpr()
     */
    public static function quoteDbExpr(DbExpr $value): string
    {
        return static::getConnection(true)->quoteDbExpr($value);
    }

    public static function query(
        string|DbExpr $query,
        string $fetchData = PdoUtils::FETCH_STATEMENT
    ): mixed {
        return static::getConnection(true)->query($query, $fetchData);
    }

    public static function exec(string|DbExpr $query): int
    {
        return static::getConnection(true)->exec($query);
    }

    public static function insert(
        array $data,
        array|bool $returning = false,
        bool $valuesAreProcessed = true
    ): ?array {
        if (!$valuesAreProcessed) {
            $data = static::getInstance()
                ->newRecord()
                ->fromData($data, isset($data[static::getPkColumnName()]))
                ->getValuesForInsertQuery(array_keys($data));
        }
        return static::getConnection(true)
            ->insert(
                static::getNameWithSchema(),
                $data,
                static::getPdoDataTypesForColumns(),
                $returning,
                static::getPkColumnName()
            );
    }

    public static function upsert(array $data, array $uniqueColumnNames): RecordInterface
    {
        ArgumentValidators::assertNotEmpty('$uniqueColumnNames', $uniqueColumnNames);
        $record = static::getInstance()->newRecord();
        $conditions = [];
        foreach ($uniqueColumnNames as $index => $columnName) {
            ArgumentValidators::assertNotEmptyString("\$uniqueColumnNames[{$index}]", $columnName, true);
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
        if (!$valuesAreProcessed) {
            $rows = static::prepareDataForInsertMany($rows, $columns);
        }
        return static::getConnection(true)
            ->insertMany(
                static::getNameWithSchema(),
                $columns,
                $rows,
                static::getPdoDataTypesForColumns($columns),
                $returning,
                static::getPkColumnName()
            );
    }

    public static function update(array $data, array $conditions, array|bool $returning = false): array|int
    {
        return static::getConnection(true)
            ->update(
                static::getNameWithSchema() . ' AS ' . static::getInstance()->getTableAlias(),
                $data,
                $conditions,
                static::getPdoDataTypesForColumns(),
                $returning,
                static::getPkColumnName()
            );
    }

    public static function delete(array $conditions = [], array|bool $returning = false): array|int
    {
        return static::getConnection(true)
            ->delete(
                static::getNameWithSchema() . ' AS ' . static::getInstance()->getTableAlias(),
                $conditions,
                $returning,
                static::getPkColumnName()
            );
    }

    /**
     * Table name with schema.
     * Example: 'public.users'
     * @return string
     */
    public static function getNameWithSchema(): string
    {
        $tableStructure = static::getInstance()->getTableStructure();
        return ltrim($tableStructure::getSchema() . '.' . $tableStructure::getTableName(), '.');
    }

    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     */
    protected static function getPdoDataTypesForColumns(array $columns = []): array
    {
        $pdoDataTypes = [];
        if (empty($columns)) {
            $columns = array_keys(static::getStructure()->getColumns());
        }
        foreach ($columns as $columnName) {
            $columnInfo = static::getStructure()->getColumn($columnName);
            $pdoDataTypes[$columnInfo->getName()] = match ($columnInfo->getType()) {
                $columnInfo::TYPE_BOOL => \PDO::PARAM_BOOL,
                $columnInfo::TYPE_INT => \PDO::PARAM_INT,
                $columnInfo::TYPE_BLOB => \PDO::PARAM_LOB,
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
    protected static function prepareDataForInsertMany(
        array $rows,
        array $columnsToSave = []
    ): array {
        if (empty($columnsToSave)) {
            foreach (static::getStructure()->getColumns() as $column) {
                if ($column->isReal() && !$column->isReadonly()) {
                    $columnsToSave[] = $column->getName();
                }
            }
        }
        $record = static::getInstance()->newRecord();
        $pkColumnName = static::getPkColumnName();
        array_walk($rows, static function (&$row, $index) use ($pkColumnName, $columnsToSave, $record) {
            if ($row instanceof RecordInterface) {
                $row = $row->getValuesForInsertQuery($columnsToSave);
            } else {
                ArgumentValidators::assertArrayKeyValueIsArray("\$rows[{$index}]", $row);
                $record->fromData($row, isset($row[$pkColumnName]));
                $row = $record->getValuesForInsertQuery($columnsToSave);
                $record->reset();
            }
        });
        return $rows;
    }

}
