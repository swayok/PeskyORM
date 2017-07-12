<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Exception\OrmException;
use Swayok\Utils\StringUtils;

abstract class Table implements TableInterface {

    /** @var Table[] */
    static private $instances = [];
    /** @var string */
    protected $alias;

    /**
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws OrmException
     */
    final static public function getInstance() {
        $class = get_called_class();
        if (!array_key_exists($class, self::$instances)) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    /**
     * Shortcut for static::getInstance()
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws OrmException
     */
    final static public function i() {
        return static::getInstance();
    }

    /**
     * Get table name
     * @return string
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getName() {
        return static::getStructure()->getTableName();
    }

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getConnection($writable = false) {
        return DbConnectionsManager::getConnection(static::getStructure()->getConnectionName($writable));
    }

    /**
     * @return TableStructure
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function getStructure() {
        return static::getInstance()->getTableStructure();
    }

    /**
     * @return string
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function getAlias() {
        return static::getInstance()->getTableAlias();
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    public function getTableAlias() {
        if (!$this->alias || !is_string($this->alias)) {
            $this->alias = StringUtils::classify(static::getName());
        }
        return $this->alias;
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::getStructure()->hasPkColumn();
    }

    /**
     * @return Column
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumn() {
        return static::getStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumnName() {
        return static::getStructure()->getPkColumnName();
    }

    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return TableInterface
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTable($relationName) {
        return static::getStructure()->getRelation($relationName)->getForeignTable();
    }

    /**
     * Get OrmJoinInfo for required relation
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinInfo
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getJoinConfigForRelation($relationName, $alterLocalTableAlias = null, $joinName = null) {
        return static::getStructure()->getRelation($relationName)->toOrmJoinConfig(
            static::getInstance(),
            $alterLocalTableAlias,
            $joinName
        );
    }

    /**
     * @param string $relationName - alias for relation defined in TableStructure
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function hasRelation($relationName) {
        return static::getStructure()->hasRelation($relationName);
    }

    /**
     * @return DbExpr
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\DbException
     */
    static public function getExpressionToSetDefaultValueForAColumn() {
        if (get_called_class() === __CLASS__) {
            throw new \BadMethodCallException(
                'Trying to call abstract method ' . __CLASS__ . '::getConnection(). Use child classes to do that'
            );
        }
        return static::getConnection(true)->getExpressionToSetDefaultValueForAColumn();
    }

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return OrmSelect
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function makeSelect($columns, array $conditions = [], \Closure $configurator = null) {
        $select = OrmSelect::from(static::getInstance())
            ->fromConfigsArray($conditions)
            ->columns($columns);
        if ($configurator !== null) {
            call_user_func($configurator, $select);
        }
        return $select;
    }

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordsSet
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditions = [], \Closure $configurator = null) {
        return RecordsSet::createFromOrmSelect(static::makeSelect($columns, $conditions, $configurator));
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectColumn($column, array $conditions = [], \Closure $configurator = null) {
        return static::makeSelect(['value' => $column], $conditions, $configurator)->fetchColumn();
    }

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], \Closure $configurator = null) {
        return static::makeSelect([], $conditions, $configurator)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectOne($columns, array $conditions, \Closure $configurator = null) {
        return static::makeSelect($columns, $conditions, $configurator)->fetchOne();
    }

    /**
     * Get 1 record from DB as Record
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return RecordInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function selectOneAsDbRecord($columns, array $conditions, \Closure $configurator = null) {
        return static::makeSelect($columns, $conditions, $configurator)->fetchOneAsDbRecord();
    }

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param DbExpr $expression - example: DbExpr::create('COUNT(*)'), DbExpr::create('SUM(`field`)')
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return string
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectValue(DbExpr $expression, array $conditions = [], \Closure $configurator = null) {
        return static::makeSelect(['value' => $expression], $conditions, $configurator)->fetchValue($expression);
    }

    /**
     * Does table contain any record matching provided condition
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasMatchingRecord(array $conditions, \Closure $configurator = null) {
        $callback = function (OrmSelect $select) use ($configurator) {
            if ($configurator) {
                call_user_func($configurator, $select);
            }
            $select->offset(0)->limit(1)->removeOrdering();
        };
        return (int)static::selectValue(DbExpr::create('1'), $conditions, $callback) === 1;
    }

    /**
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     *      Note: columns list, LIMIT, OFFSET and ORDER BY are not applied to count query
     * @param bool $removeNotInnerJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function count(array $conditions = [], \Closure $configurator = null, $removeNotInnerJoins = false) {
        return static::makeSelect([], $conditions, $configurator)->fetchCount($removeNotInnerJoins);
    }

    /**
     * @param bool $useWritableConnection
     * @return null|string
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getLastQuery($useWritableConnection) {
        return static::getConnection($useWritableConnection)->getLastQuery();
    }

    /**
     * @param bool $readOnly
     * @param null|string $transactionType
     * @return void
     */
    static public function beginTransaction($readOnly = false, $transactionType = null) {
        static::getConnection(true)->begin($readOnly, $transactionType);
    }

    /**
     * @return bool
     */
    static public function inTransaction() {
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
     * @param string $name
     * @return string
     */
    static public function quoteDbEntityName($name) {
        return static::getConnection(true)->quoteDbEntityName($name);
    }

    /**
     * @param mixed $value
     * @param int $fieldInfoOrType
     * @return string
     */
    static public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return static::getConnection(true)->quoteValue($value, $fieldInfoOrType);
    }

    /**
     * @param DbExpr $value
     * @return string
     */
    static public function quoteDbExpr(DbExpr $value) {
        return static::getConnection(true)->quoteDbExpr($value);
    }

    /**
     * @param string|DbExpr $query
     * @param string|null $fetchData - null: return PDOStatement; string: one of \PeskyORM\Core\Utils::FETCH_*
     * @return \PDOStatement|array
     */
    static public function query($query, $fetchData = null) {
        return static::getConnection(true)->query($query, $fetchData);
    }

    /**
     * @param string|DbExpr $query
     * @return int|array = array: returned if $returning argument is not empty
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function insert(array $data, $returning = false) {
        return static::getConnection(true)->insert(
            static::getName(),
            $data,
            static::getPdoDataTypesForColumns(),
            $returning
        );
    }

    /**
     * @param array $columns - list of column names to insert data for
     * @param array $rows - data to insert
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function insertMany(array $columns, array $rows, $returning = false) {
        return static::getConnection(true)->insertMany(
            static::getName(),
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
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function update(array $data, array $conditions, $returning = false) {
        return static::getConnection(true)->update(
            static::getName(),
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
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function delete(array $conditions = [], $returning = false) {
        return static::getConnection(true)->delete(
            static::getName(),
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
            $returning
        );
    }

    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static protected function getPdoDataTypesForColumns(array $columns = []) {
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

    /**
     * Resets class instances (used for testing only, that's why it is private)
     */
    static private function resetInstances() {
        self::$instances = [];
    }

}