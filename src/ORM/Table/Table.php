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
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Utils\QueryBuilderUtils;
use PeskyORM\Utils\StringUtils;

abstract class Table implements TableInterface
{

    /** @var Table[] */
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

    /**
     * Shortcut for static::getInstance()
     * @return static
     */
    final public static function i(): TableInterface
    {
        return static::getInstance();
    }

    /**
     * Get table name
     * @return string
     */
    public static function getName(): string
    {
        return static::getStructure()
            ->getTableName();
    }

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    public static function getConnection(bool $writable = false): DbAdapterInterface
    {
        return DbConnectionsManager::getConnection(
            static::getStructure()->getConnectionName($writable)
        );
    }

    /**
     * @return TableStructure
     */
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

    public static function hasPkColumn(): bool
    {
        return static::getStructure()->hasPkColumn();
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
     * @param string $relationName - alias for relation defined in TableStructure
     * @return TableInterface
     */
    public static function getRelatedTable(string $relationName): TableInterface
    {
        return static::getStructure()
            ->getRelation($relationName)
            ->getForeignTable();
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
     * @param string $relationName - alias for relation defined in TableStructure
     * @return bool
     */
    public static function hasRelation(string $relationName): bool
    {
        return static::getStructure()->hasRelation($relationName);
    }

    /**
     * @throws \BadMethodCallException
     * @see \PeskyORM\Adapter\DbAdapterInterface::getExpressionToSetDefaultValueForAColumn()
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

    /**
     * @param array|string $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select): void {}
     * @return OrmSelect
     */
    public static function makeSelect(
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
            static::makeSelect($columns, $conditions, $configurator)
        );
    }

    public static function selectColumn(
        string|DbExpr $column,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array {
        return static::makeSelect([], $conditions, $configurator)
            ->fetchColumn($column);
    }

    public static function selectAssoc(
        string|DbExpr|null $keysColumn,
        string|DbExpr|null $valuesColumn,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array {
        return static::makeSelect([], $conditions, $configurator)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    public static function selectOne(string|array $columns, array $conditions, ?\Closure $configurator = null): array
    {
        return static::makeSelect($columns, $conditions, $configurator)
            ->fetchOne();
    }

    public static function selectOneAsDbRecord(
        string|array $columns,
        array $conditions,
        ?\Closure $configurator = null
    ): RecordInterface {
        return static::makeSelect($columns, $conditions, $configurator)
            ->fetchOneAsDbRecord();
    }

    public static function selectValue(
        DbExpr $expression,
        array $conditions = [],
        ?\Closure $configurator = null
    ): mixed {
        return static::makeSelect([], $conditions, $configurator)
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
        return static::makeSelect(
            [
                static::getInstance()
                    ->getTableStructure()
                    ->getPkColumnName(),
            ],
            $conditions,
            $configurator
        )->fetchCount($removeNotInnerJoins);
    }

    public static function makeConditionsFromArray(array $conditions): DbExpr
    {
        $assembled = QueryBuilderUtils::assembleWhereConditionsFromArray(
            static::getConnection(),
            $conditions,
            static function ($columnName) {
                $columnInfo = static::analyzeColumnName($columnName);
                $tableAlias = $columnInfo['join_name'] ?: static::getAlias();
                $columnNameForDbExpr = '`' . $tableAlias . '`.';
                if ($columnInfo['json_selector']) {
                    $columnNameForDbExpr .= static::quoteDbEntityName($columnInfo['json_selector']);
                } else {
                    $columnNameForDbExpr .= '`' . $columnInfo['name'] . '`';
                }
                if ($columnInfo['type_cast']) {
                    $columnNameForDbExpr = static::getConnection()
                        ->addDataTypeCastToExpression($columnInfo['type_cast'], $columnNameForDbExpr);
                }
                return $columnNameForDbExpr;
            },
            'AND',
            static function ($columnName, $rawValue) {
                if ($rawValue instanceof DbExpr) {
                    return $rawValue->get();
                }
                return $rawValue;
            }
        );
        return DbExpr::create(trim($assembled), false);
    }

    public static function analyzeColumnName($columnName): array
    {
        if ($columnName instanceof DbExpr) {
            $ret = [
                'name' => $columnName,
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ];
        } else {
            $columnName = trim($columnName);
            $ret = QueryBuilderUtils::splitColumnName($columnName);
            if (!static::getConnection()
                ->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
                if ($ret['json_selector']) {
                    throw new \InvalidArgumentException("Invalid json selector: [{$ret['json_selector']}]");
                }

                throw new \InvalidArgumentException("Invalid column name: [{$ret['name']}]");
            }
        }

        // nullify join name if it same as current table alias
        if ($ret['join_name'] === static::getAlias()) {
            $ret['join_name'] = null;
        }

        return $ret;
    }

    public static function getLastQuery(bool $useWritableConnection): ?string
    {
        try {
            return static::getConnection($useWritableConnection)->getLastQuery();
        } catch (\Exception $exception) {
            return $exception->getMessage() . '. ' . $exception->getTraceAsString();
        }
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::begin()
     */
    public static function beginTransaction(bool $readOnly = false, ?string $transactionType = null): void
    {
        static::getConnection(true)->begin($readOnly, $transactionType);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::inTransaction()
     */
    public static function inTransaction(): bool
    {
        return static::getConnection(true)->inTransaction();
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::commit()
     */
    public static function commitTransaction(): void
    {
        static::getConnection(true)->commit();
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::rollBack()
     */
    public static function rollBackTransaction(): void
    {
        static::getConnection(true)->rollBack();
    }

    public static function rollBackTransactionIfExists(): void
    {
        if (static::inTransaction()) {
            static::rollBackTransaction();
        }
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

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::query()
     */
    public static function query(string|DbExpr $query, ?string $fetchData = null): mixed
    {
        return static::getConnection(true)->query($query, $fetchData);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::exec()
     */
    public static function exec(string|DbExpr $query): int
    {
        return static::getConnection(true)->exec($query);
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::insert()
     */
    public static function insert(array $data, array|bool $returning = false): ?array
    {
        return static::getConnection(true)
            ->insert(
                static::getNameWithSchema(),
                $data,
                static::getPdoDataTypesForColumns(),
                $returning,
                static::getPkColumnName()
            );
    }

    /**
     * Insert new record or update existing one if duplicate value found for $columnName
     * @param array $data
     * @param string $columnName - column to detect duplicates (only 1 column allowed!)
     * @return \PeskyORM\ORM\Record\RecordInterface
     */
    public static function upsert(array $data, string $columnName): RecordInterface
    {
        if (!isset($data[$columnName])) {
            throw new \InvalidArgumentException("There is no value for column {$columnName} in passed \$data");
        }
        $record = static::getInstance()->newRecord();
        $record->updateValue($columnName, $data[$columnName], false); //< to validate and normalize value
        $record->fetch([
            $columnName => $record->getValue($columnName),
        ]);
        if ($record->existsInDb()) {
            unset($data[$columnName]);
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

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::insertMany()
     */
    public static function insertMany(array $columns, array $rows, array|bool $returning = false): ?array
    {
        return static::insertManyAsIs(
            $columns,
            static::prepareDataForInsertMany($rows),
            $returning
        );
    }

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::insertMany()
     */
    public static function insertManyAsIs(array $columns, array $rows, $returning = false): ?array
    {
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

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::update()
     */
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

    /**
     * @see \PeskyORM\Adapter\DbAdapterInterface::delete()
     */
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
     * Alter $rows to honor special columns features like isAutoUpdatingValue and fill missing values with defaults.
     * TableColumn features supported: isAutoUpdatingValue, isValueCanBeNull, convertsEmptyStringToNull,
     *      isValueTrimmingRequired, isValueLowercasingRequired, getValidDefaultValue.
     * Also uses static::getExpressionToSetDefaultValueForAColumn() if TableColumn has no default value
     * @param array $rows
     * @param array $columnsToSave
     * @param array|null $features - null: ['trim', 'lowercase', 'nullable', 'empty_string_to_null', 'auto']
     * @return array
     */
    protected static function prepareDataForInsertMany(
        array $rows,
        array $columnsToSave = [],
        ?array $features = null
    ): array {
        $allColumns = static::getStructure()->getColumns();
        if (empty($columnsToSave)) {
            $columnsToSave = array_keys($allColumns);
        }
        if ($features === null) {
            $features = ['trim', 'lowercase', 'nullable', 'empty_string_to_null', 'auto'];
        }
        $defaults = [];
        /** @var TableColumnInterface[] $autoupdatableColumns */
        $autoupdatableColumns = [];
        $notNulls = [];
        $emptyToNull = [];
        $trims = [];
        $lowercases = [];
        $dbDefault = static::getExpressionToSetDefaultValueForAColumn();
        foreach ($columnsToSave as $columnName) {
            $column = $allColumns[$columnName];
            if (in_array('auto', $features, true) && $column->isAutoUpdatingValue()) {
                $autoupdatableColumns[$columnName] = $column;
            } else {
                if ($column->hasDefaultValue()) {
                    $defaults[$columnName] = $column->getValidDefaultValue();
                } else {
                    $defaults[$columnName] = $dbDefault;
                }
                if (in_array('nullable', $features, true) && !$column->isValueCanBeNull()) {
                    $notNulls[] = $columnName;
                }
                if (in_array('empty_string_to_null', $features, true) && !$column->convertsEmptyStringToNull()) {
                    $emptyToNull[] = $columnName;
                }
                if (in_array('trim', $features, true) && $column->isValueTrimmingRequired()) {
                    $trims[] = $columnName;
                }
                if (in_array('lowercase', $features, true) && $column->isValueLowercasingRequired()) {
                    $lowercases[] = $columnName;
                }
            }
        }
        foreach ($rows as &$row) {
            if ($row instanceof RecordInterface) {
                $row = $row->toArray($columnsToSave);
            }
            // trim before $emptyToNull
            foreach ($trims as $columnName) {
                if (isset($row[$columnName])) {
                    $row[$columnName] = trim($row[$columnName]);
                }
            }
            // $emptyToNull before $notNulls
            foreach ($emptyToNull as $columnName) {
                if (isset($row[$columnName]) && $row[$columnName] === '') {
                    // convert empty string to null
                    $row[$columnName] = null;
                }
            }
            foreach ($notNulls as $columnName) {
                if (!isset($row[$columnName])) {
                    // remove column from array totally so that default value can replace it
                    unset($row[$columnName]);
                }
            }
            foreach ($lowercases as $columnName) {
                if (isset($row[$columnName])) {
                    $row[$columnName] = mb_strtolower($row[$columnName]);
                }
            }
            $row = array_merge($defaults, $row);
            foreach ($autoupdatableColumns as $columnName => $column) {
                $row[$columnName] = $column->getAutoUpdateForAValue($row);
            }
        }
        return $rows;
    }

}
