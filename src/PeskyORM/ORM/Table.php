<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Swayok\Utils\StringUtils;

abstract class Table implements TableInterface {

    /** @var Table[] */
    static private $instances = [];
    /** @var string */
    protected $alias;
    
    /**
     * @return $this
     */
    final static public function getInstance(): TableInterface {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
        }
        return self::$instances[static::class];
    }
    
    /**
     * Shortcut for static::getInstance()
     * @return $this
     */
    final static public function i(): TableInterface {
        return static::getInstance();
    }
    
    /**
     * Get table name
     * @return string
     */
    static public function getName(): string {
        return static::getStructure()->getTableName();
    }
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    static public function getConnection($writable = false): DbAdapterInterface {
        return DbConnectionsManager::getConnection(static::getStructure()->getConnectionName($writable));
    }
    
    /**
     * @return TableStructure
     */
    static public function getStructure(): TableStructureInterface {
        return static::getInstance()->getTableStructure();
    }
    
    static public function getAlias(): string {
        return static::getInstance()->getTableAlias();
    }
    
    public function getTableAlias(): string {
        if (!$this->alias || !is_string($this->alias)) {
            $this->alias = StringUtils::classify(static::getName());
        }
        return $this->alias;
    }
    
    static public function hasPkColumn(): bool {
        return static::getStructure()->hasPkColumn();
    }
    
    static public function getPkColumn(): Column {
        return static::getStructure()->getPkColumn();
    }
    
    static public function getPkColumnName(): string {
        return static::getStructure()->getPkColumnName();
    }
    
    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return TableInterface
     */
    static public function getRelatedTable($relationName): TableInterface {
        return static::getStructure()->getRelation($relationName)->getForeignTable();
    }
    
    /**
     * Get OrmJoinInfo for required relation
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinInfo
     */
    static public function getJoinConfigForRelation($relationName, $alterLocalTableAlias = null, $joinName = null): OrmJoinInfo {
        return static::getStructure()->getRelation($relationName)->toOrmJoinConfig(
            static::getInstance(),
            $alterLocalTableAlias,
            $joinName
        );
    }
    
    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return bool
     */
    static public function hasRelation($relationName): bool {
        return static::getStructure()->hasRelation($relationName);
    }
    
    /**
     * @return DbExpr
     * @throws \BadMethodCallException
     */
    static public function getExpressionToSetDefaultValueForAColumn(): DbExpr {
        if (static::class === __CLASS__) {
            throw new \BadMethodCallException(
                'Trying to call abstract method ' . __CLASS__ . '::getConnection(). Use child classes to do that'
            );
        }
        return static::getConnection(true)->getExpressionToSetDefaultValueForAColumn();
    }
    
    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return OrmSelect
     */
    static public function makeSelect($columns, array $conditions = [], ?\Closure $configurator = null): OrmSelect {
        $select = OrmSelect::from(static::getInstance())
            ->fromConfigsArray($conditions)
            ->columns($columns);
        if ($configurator !== null) {
            $configurator($select);
        }
        return $select;
    }

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordsSet - Do not typehint returning value! it actually may be overriden to return array
     * @throws \PDOException
     */
    static public function select($columns = '*', array $conditions = [], ?\Closure $configurator = null) {
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
    static public function selectColumn($column, array $conditions = [], ?\Closure $configurator = null): array {
        return static::makeSelect(['value' => $column], $conditions, $configurator)->fetchColumn();
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
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], ?\Closure $configurator = null): array {
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
    static public function selectOne($columns, array $conditions, ?\Closure $configurator = null): array {
        return static::makeSelect($columns, $conditions, $configurator)->fetchOne();
    }
    
    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordInterface
     * @throws \PDOException
     */
    static public function selectOneAsDbRecord($columns, array $conditions, ?\Closure $configurator = null): \PeskyORM\ORM\RecordInterface {
        return static::makeSelect($columns, $conditions, $configurator)->fetchOneAsDbRecord();
    }
    
    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return string|null
     * @throws \PDOException
     */
    static public function selectValue(DbExpr $expression, array $conditions = [], ?\Closure $configurator = null): ?string {
        return static::makeSelect(['value' => $expression], $conditions, $configurator)->fetchValue($expression);
    }

    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure|null $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \PDOException
     */
    static public function hasMatchingRecord(array $conditions, ?\Closure $configurator = null): bool {
        $callback = function (OrmSelect $select) use ($configurator) {
            if ($configurator) {
                $configurator($select);
            }
            $select->offset(0)->limit(1)->removeOrdering();
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
    static public function count(array $conditions = [], ?\Closure $configurator = null, bool $removeNotInnerJoins = false): int {
        return static::makeSelect(
                [static::getInstance()->getTableStructure()->getPkColumnName()],
                $conditions,
                $configurator
            )
            ->fetchCount($removeNotInnerJoins);
    }
    
    /**
     * @param bool $useWritableConnection
     * @return null|string
     */
    static public function getLastQuery(bool $useWritableConnection): ?string {
        try {
            return static::getConnection($useWritableConnection)->getLastQuery();
        } catch (\Exception $exception) {
            return $exception->getMessage() . '. ' . $exception->getTraceAsString();
        }
    }
    
    /**
     * @param bool $readOnly
     * @param null|string $transactionType
     * @return void
     */
    static public function beginTransaction(bool $readOnly = false, ?string $transactionType = null) {
        static::getConnection(true)->begin($readOnly, $transactionType);
    }
    
    /**
     * @return bool
     */
    static public function inTransaction(): bool {
        return static::getConnection(true)->inTransaction();
    }

    /**
     * @return void
     */
    static public function commitTransaction() {
        static::getConnection(true)->commit();
    }

    /**
     * @return void
     */
    static public function rollBackTransaction() {
        static::getConnection(true)->rollBack();
    }
    
    /**
     * @return void
     */
    static public function rollBackTransactionIfExists() {
        if (static::inTransaction()) {
            static::rollBackTransaction();
        }
    }
    
    /**
     * @param string $name
     * @return string
     */
    static public function quoteDbEntityName(string $name): string {
        return static::getConnection(true)->quoteDbEntityName($name);
    }
    
    /**
     * @param mixed $value
     * @param int $fieldInfoOrType
     * @return string
     */
    static public function quoteValue($value, int $fieldInfoOrType = \PDO::PARAM_STR): string {
        return static::getConnection(true)->quoteValue($value, $fieldInfoOrType);
    }
    
    /**
     * @param DbExpr $value
     * @return string
     */
    static public function quoteDbExpr(DbExpr $value): string {
        return static::getConnection(true)->quoteDbExpr($value);
    }
    
    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|array
     * @throws \PDOException
     */
    static public function query($query, ?string $fetchData = null) {
        return static::getConnection(true)->query($query, $fetchData);
    }

    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
     * @throws \PDOException
     */
    static public function exec($query) {
        return static::getConnection(true)->exec($query);
    }

    /**
     * @param array $data
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PDOException
     */
    static public function insert(array $data, $returning = false) {
        return static::getConnection(true)->insert(
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
    static public function upsert(array $data, string $columnName): RecordInterface {
        if (!isset($data[$columnName])) {
            throw new \InvalidArgumentException("There is no value for column {$columnName} in passed \$data");
        }
        $record = static::getInstance()->newRecord();
        $record->updateValue($columnName, $data[$columnName], false); //< to validate and normalize value
        $record->fetch([
            $columnName => $record->getValue($columnName)
        ]);
        if ($record->existsInDb()) {
            unset($data[$columnName]);
            $record
                ->begin()
                ->updateValues($data, false)
                ->commit();
        } else {
            $record->reset()->fromData($data)->save();
        }
        return $record;
    }

    /**
     * @param array $columns - list of column names to insert data for
     * @param array $rows - data to insert
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PDOException
     */
    static public function insertMany(array $columns, array $rows, $returning = false) {
        return static::getConnection(true)->insertMany(
            static::getNameWithSchema(),
            $columns,
            $rows,
            static::getPdoDataTypesForColumns($columns),
            $returning
        );
    }

    /**
     * @param array $data - key-value array where key = table column and value = value of associated column
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|int - information about update execution
     *          - int: number of modified rows (when $returning === false)
     *          - array: modified records (when $returning !== false)
     * @throws \PDOException
     */
    static public function update(array $data, array $conditions, $returning = false) {
        return static::getConnection(true)->update(
            static::getNameWithSchema() . ' AS ' . static::getInstance()->getTableAlias(),
            $data,
            Utils::assembleWhereConditionsFromArray(static::getConnection(true), $conditions),
            static::getPdoDataTypesForColumns(),
            $returning
        );
    }

    /**
     * @param array $conditions - WHERE conditions
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return int|array - int: number of deleted records | array: returned only if $returning is not empty
     * @throws \PDOException
     */
    static public function delete(array $conditions = [], $returning = false) {
        return static::getConnection(true)->delete(
            static::getNameWithSchema(),
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
            $returning
        );
    }

    /**
     * Table name with schema.
     * Example: 'public.users'
     * @return string
     */
    static public function getNameWithSchema(): string {
        $tableStructure = static::getInstance()->getTableStructure();
        return ltrim($tableStructure::getSchema() . '.' . $tableStructure::getTableName(), '.');
    }
    
    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     */
    static protected function getPdoDataTypesForColumns(array $columns = []): array {
        $pdoDataTypes = [];
        if (empty($columns)) {
            $columns = array_keys(static::getStructure()->getColumns());
        }
        foreach ($columns as $columnName) {
            $columnInfo = static::getStructure()->getColumn($columnName);
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

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances() {
        self::$instances = [];
    }

}
