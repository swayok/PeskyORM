<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Swayok\Utils\StringUtils;

abstract class Table implements TableInterface
{
    
    /** @var Table[] */
    static private $instances = [];
    /** @var string */
    protected $alias;
    
    /**
     * @return $this
     */
    final static public function getInstance(): TableInterface
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
    static private function resetInstances()
    {
        self::$instances = [];
    }
    
    /**
     * Shortcut for static::getInstance()
     * @return $this
     */
    final static public function i(): TableInterface
    {
        return static::getInstance();
    }
    
    /**
     * Get table name
     * @return string
     */
    static public function getName(): string
    {
        return static::getStructure()
            ->getTableName();
    }
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    static public function getConnection($writable = false): DbAdapterInterface
    {
        return DbConnectionsManager::getConnection(
            static::getStructure()
                ->getConnectionName($writable)
        );
    }
    
    /**
     * @return TableStructure
     */
    static public function getStructure(): TableStructureInterface
    {
        return static::getInstance()
            ->getTableStructure();
    }
    
    static public function getAlias(): string
    {
        return static::getInstance()
            ->getTableAlias();
    }
    
    public function getTableAlias(): string
    {
        if (!$this->alias || !is_string($this->alias)) {
            $this->alias = StringUtils::classify(static::getName());
        }
        return $this->alias;
    }
    
    static public function hasPkColumn(): bool
    {
        return static::getStructure()
            ->hasPkColumn();
    }
    
    static public function getPkColumn(): Column
    {
        return static::getStructure()
            ->getPkColumn();
    }
    
    static public function getPkColumnName(): string
    {
        return static::getStructure()
            ->getPkColumnName();
    }
    
    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return TableInterface
     */
    static public function getRelatedTable($relationName): TableInterface
    {
        return static::getStructure()
            ->getRelation($relationName)
            ->getForeignTable();
    }
    
    /**
     * Get OrmJoinInfo for required relation
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinInfo
     */
    static public function getJoinConfigForRelation($relationName, $alterLocalTableAlias = null, $joinName = null): OrmJoinInfo
    {
        return static::getStructure()
            ->getRelation($relationName)
            ->toOrmJoinConfig(
                static::getInstance(),
                $alterLocalTableAlias,
                $joinName
            );
    }
    
    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return bool
     */
    static public function hasRelation($relationName): bool
    {
        return static::getStructure()
            ->hasRelation($relationName);
    }
    
    /**
     * @see DbAdapter::getExpressionToSetDefaultValueForAColumn()
     * @throws \BadMethodCallException
     */
    static public function getExpressionToSetDefaultValueForAColumn(): DbExpr
    {
        if (static::class === __CLASS__) {
            throw new \BadMethodCallException(
                'Trying to call abstract method ' . __CLASS__ . '::getConnection(). Use child classes to do that'
            );
        }
        return static::getConnection(true)
            ->getExpressionToSetDefaultValueForAColumn();
    }
    
    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return OrmSelect
     */
    static public function makeSelect($columns, array $conditions = [], ?\Closure $configurator = null): OrmSelect
    {
        $select = OrmSelect::from(static::getInstance())
            ->fromConfigsArray($conditions);
        if ($configurator !== null) {
            $configurator($select);
        }
        $select->columns($columns);
        return $select;
    }
    
    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordsSet - Do not typehint returning value! it actually may be overriden to return array
     * @throws \PDOException
     */
    static public function select($columns = '*', array $conditions = [], ?\Closure $configurator = null)
    {
        return RecordsSet::createFromOrmSelect(static::makeSelect($columns, $conditions, $configurator));
    }
    
    /**
     * Selects only 1 column
     * @param string|DbExpr $column
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PDOException
     */
    static public function selectColumn($column, array $conditions = [], ?\Closure $configurator = null): array
    {
        return static::makeSelect(['value' => $column], $conditions, $configurator)
            ->fetchColumn();
    }
    
    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string|DbExpr $keysColumn
     * @param string|DbExpr $valuesColumn
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PDOException
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], ?\Closure $configurator = null): array
    {
        return static::makeSelect([], $conditions, $configurator)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }
    
    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PDOException
     */
    static public function selectOne($columns, array $conditions, ?\Closure $configurator = null): array
    {
        return static::makeSelect($columns, $conditions, $configurator)
            ->fetchOne();
    }
    
    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordInterface
     * @throws \PDOException
     */
    static public function selectOneAsDbRecord($columns, array $conditions, ?\Closure $configurator = null): \PeskyORM\ORM\RecordInterface
    {
        return static::makeSelect($columns, $conditions, $configurator)
            ->fetchOneAsDbRecord();
    }
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return string|int|float|null
     * @throws \PDOException
     */
    static public function selectValue(DbExpr $expression, array $conditions = [], ?\Closure $configurator = null)
    {
        return static::makeSelect(['value' => $expression], $conditions, $configurator)
            ->fetchValue($expression);
    }
    
    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \PDOException
     */
    static public function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool
    {
        $callback = function (OrmSelect $select) use ($configurator) {
            if ($configurator) {
                $configurator($select);
            }
            $select->offset(0)
                ->limit(1)
                ->removeOrdering();
        };
        return (int)static::selectValue(DbExpr::create('1'), $conditions, $callback) === 1;
    }
    
    /**
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     *      Note: columns list, LIMIT, OFFSET and ORDER BY are not applied to count query
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     * @throws \PDOException
     */
    static public function count(array $conditions = [], ?\Closure $configurator = null, bool $removeNotInnerJoins = false): int
    {
        return static::makeSelect(
            [
                static::getInstance()
                    ->getTableStructure()
                    ->getPkColumnName(),
            ],
            $conditions,
            $configurator
        )
            ->fetchCount($removeNotInnerJoins);
    }
    
    static public function makeConditionsFromArray(array $conditions): DbExpr
    {
        $assembled = Utils::assembleWhereConditionsFromArray(
            static::getConnection(),
            $conditions,
            function ($columnName) {
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
            function ($columnName, $rawValue) {
                if ($rawValue instanceof DbExpr) {
                    return $rawValue->get();
                }
                return $rawValue;
            }
        );
        return DbExpr::create(trim($assembled), false);
    }
    
    static public function analyzeColumnName($columnName): array
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
            $ret = Utils::splitColumnName($columnName);
            if (!static::getConnection()
                ->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
                if ($ret['json_selector']) {
                    throw new \InvalidArgumentException("Invalid json selector: [{$ret['json_selector']}]");
                } else {
                    throw new \InvalidArgumentException("Invalid column name: [{$ret['name']}]");
                }
            }
        }
        
        // nullify join name if it same as current table alias
        if ($ret['join_name'] === static::getAlias()) {
            $ret['join_name'] = null;
        }
        
        return $ret;
    }
    
    /**
     * @param bool $useWritableConnection
     * @return null|string
     */
    static public function getLastQuery(bool $useWritableConnection): ?string
    {
        try {
            return static::getConnection($useWritableConnection)
                ->getLastQuery();
        } catch (\Exception $exception) {
            return $exception->getMessage() . '. ' . $exception->getTraceAsString();
        }
    }
    
    /**
     * @see DbAdapter::begin()
     */
    static public function beginTransaction(bool $readOnly = false, ?string $transactionType = null)
    {
        static::getConnection(true)
            ->begin($readOnly, $transactionType);
    }
    
    /**
     * @see DbAdapter::inTransaction()
     */
    static public function inTransaction(): bool
    {
        return static::getConnection(true)
            ->inTransaction();
    }
    
    /**
     * @see DbAdapter::commit()
     * @return void
     */
    static public function commitTransaction()
    {
        static::getConnection(true)
            ->commit();
    }
    
    /**
     * @see DbAdapter::rollBack()
     * @return void
     */
    static public function rollBackTransaction()
    {
        static::getConnection(true)
            ->rollBack();
    }
    
    /**
     * @return void
     */
    static public function rollBackTransactionIfExists()
    {
        if (static::inTransaction()) {
            static::rollBackTransaction();
        }
    }
    
    /**
     * @see DbAdapter::quoteDbEntityName()
     */
    static public function quoteDbEntityName(string $name): string
    {
        return static::getConnection(true)
            ->quoteDbEntityName($name);
    }
    
    /**
     * @see DbAdapter::quoteValue()
     */
    static public function quoteValue($value, int $fieldInfoOrType = \PDO::PARAM_STR): string
    {
        return static::getConnection(true)
            ->quoteValue($value, $fieldInfoOrType);
    }
    
    /**
     * @see DbAdapter::quoteDbExpr()
     */
    static public function quoteDbExpr(DbExpr $value): string
    {
        return static::getConnection(true)
            ->quoteDbExpr($value);
    }
    
    /**
     * @see DbAdapter::query()
     */
    static public function query($query, ?string $fetchData = null)
    {
        return static::getConnection(true)
            ->query($query, $fetchData);
    }
    
    /**
     * @see DbAdapter::exec()
     */
    static public function exec($query)
    {
        return static::getConnection(true)
            ->exec($query);
    }
    
    /**
     * @see DbAdapter::insert()
     */
    static public function insert(array $data, $returning = false)
    {
        return static::getConnection(true)
            ->insert(
                static::getNameWithSchema(),
                $data,
                static::getPdoDataTypesForColumns(),
                $returning
            );
    }
    
    /**
     * Insert new record or update existing one if duplicate value found for $columnName
     * @param array $data
     * @param string $columnName - column to detect duplicates (only 1 column allowed!)
     * @return RecordInterface
     * @throws \PDOException
     */
    static public function upsert(array $data, string $columnName): RecordInterface
    {
        if (!isset($data[$columnName])) {
            throw new \InvalidArgumentException("There is no value for column {$columnName} in passed \$data");
        }
        $record = static::getInstance()
            ->newRecord();
        $record->updateValue($columnName, $data[$columnName], false); //< to validate and normalize value
        $record->fetch([
            $columnName => $record->getValue($columnName),
        ]);
        if ($record->existsInDb()) {
            unset($data[$columnName]);
            $record
                ->begin()
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
     * @see DbAdapter::insertMany()
     */
    static public function insertMany(array $columns, array $rows, $returning = false)
    {
        return static::insertManyAsIs(
            $columns,
            static::prepareDataForInsertMany($rows),
            $returning
        );
    }
    
    /**
     * @see DbAdapter::insertMany()
     */
    static public function insertManyAsIs(array $columns, array $rows, $returning = false)
    {
        return static::getConnection(true)
            ->insertMany(
                static::getNameWithSchema(),
                $columns,
                $rows,
                static::getPdoDataTypesForColumns($columns),
                $returning
            );
    }
    
    /**
     * @see DbAdapter::update()
     */
    static public function update(array $data, array $conditions, $returning = false)
    {
        return static::getConnection(true)
            ->update(
                static::getNameWithSchema() . ' AS ' . static::getInstance()->getTableAlias(),
                $data,
                Utils::assembleWhereConditionsFromArray(static::getConnection(true), $conditions),
                static::getPdoDataTypesForColumns(),
                $returning
            );
    }
    
    /**
     * @see DbAdapterInterface::delete()
     */
    static public function delete(array $conditions = [], $returning = false)
    {
        return static::getConnection(true)
            ->delete(
                static::getNameWithSchema() . ' AS ' . static::getInstance()->getTableAlias(),
                Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
                $returning
            );
    }
    
    /**
     * Table name with schema.
     * Example: 'public.users'
     * @return string
     */
    static public function getNameWithSchema(): string
    {
        $tableStructure = static::getInstance()->getTableStructure();
        return ltrim($tableStructure::getSchema() . '.' . $tableStructure::getTableName(), '.');
    }
    
    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     */
    static protected function getPdoDataTypesForColumns(array $columns = []): array
    {
        $pdoDataTypes = [];
        if (empty($columns)) {
            $columns = array_keys(
                static::getStructure()
                    ->getColumns()
            );
        }
        foreach ($columns as $columnName) {
            $columnInfo = static::getStructure()
                ->getColumn($columnName);
            switch ($columnInfo->getType()) {
                case $columnInfo::TYPE_BOOL:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_BOOL;
                    break;
                case $columnInfo::TYPE_INT:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_INT;
                    break;
                case $columnInfo::TYPE_BLOB:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_LOB;
                    break;
                default:
                    $pdoDataTypes[$columnInfo->getName()] = \PDO::PARAM_STR;
            }
        }
        return $pdoDataTypes;
    }
    
    /**
     * Alter $rows to honor special columns features like isAutoUpdatingValue and fill missing values with defaults.
     * Column features supported: isAutoUpdatingValue, isValueCanBeNull, convertsEmptyStringToNull,
     *      isValueTrimmingRequired, isValueLowercasingRequired, getValidDefaultValue.
     * Also uses static::getExpressionToSetDefaultValueForAColumn() if Column has no default value
     * @param array $rows
     * @param array $columnsToSave
     * @param array|null $features - null: ['trim', 'lowercase', 'nullable', 'empty_string_to_null', 'auto']
     * @return array
     */
    static protected function prepareDataForInsertMany(
        array $rows,
        array $columnsToSave = [],
        ?array $features = null
    ): array {
        $allColumns = static::getStructure()
            ->getColumns();
        if (empty($columnsToSave)) {
            $columnsToSave = array_keys($allColumns);
        }
        if ($features === null) {
            $features = ['trim', 'lowercase', 'nullable', 'empty_string_to_null', 'auto'];
        }
        $defaults = [];
        /** @var Column[] $autoupdatableColumns */
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
