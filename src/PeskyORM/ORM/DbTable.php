<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\ORM\Exception\OrmException;
use Swayok\Utils\StringUtils;

abstract class DbTable implements DbTableInterface {

    /** @var DbTable[] */
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getName() {
        return static::getStructure()->getTableName();
    }

    /**
     * @return DbAdapterInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getConnection() {
        return DbConnectionsManager::getConnection(static::getStructure()->getConnectionName());
    }

    /**
     * @return DbTableStructure
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function getStructure() {
        return static::getInstance()->getTableStructure();
    }

    /**
     * @return string
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function getAlias() {
        return static::getInstance()->getTableAlias();
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasPkColumn() {
        return static::getStructure()->hasPkColumn();
    }

    /**
     * @return DbTableColumn
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumn() {
        return static::getStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPkColumnName() {
        return static::getStructure()->getPkColumnName();
    }

    /**
     * @param string $relationName - alias for relation defined in DbTableStructure
     * @return DbTableInterface
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTable($relationName) {
        return static::getStructure()->getRelation($relationName)->getForeignTable();
    }

    /**
     * Get OrmJoinConfig for required relation
     * @param string $relationName
     * @param string|null $alterLocalTableAlias - alter this table's alias in join config
     * @param string|null $joinName - string: specific join name; null: $relationName is used
     * @return OrmJoinConfig
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @param string $relationName - alias for relation defined in DbTableStructure
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    static public function hasRelation($relationName) {
        return static::getStructure()->hasRelation($relationName);
    }

    /**
     * @return DbExpr
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Core\DbException
     */
    static public function getExpressionToSetDefaultValueForAColumn() {
        if (get_called_class() === __CLASS__) {
            throw new \BadMethodCallException(
                'Trying to call abstract method ' . __CLASS__ . '::getConnection(). Use child classes to do that'
            );
        }
        return static::getConnection()->getExpressionToSetDefaultValueForAColumn();
    }

    /**
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return OrmSelect
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @return DbRecordsSet
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditions = [], \Closure $configurator = null) {
        return DbRecordsSet::createFromOrmSelect(static::makeSelect($columns, $conditions, $configurator));
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditions = [], \Closure $configurator = null) {
        return static::makeSelect(['key' => $keysColumn, 'value' => $valuesColumn], $conditions, $configurator)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    /**
     * Get 1 record from DB as array
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function selectOne($columns, array $conditions, \Closure $configurator = null) {
        return static::makeSelect($columns, $conditions, $configurator)->fetchOne();
    }

    /**
     * Get 1 record from DB as DbRecord
     * @param string|array $columns
     * @param array $conditions
     * @param \Closure $configurator - closure to configure OrmSelect. function (OrmSelect $select) {}
     * @return DbRecordInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
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
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @throws \PeskyORM\ORM\Exception\OrmException
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function count(array $conditions, \Closure $configurator = null, $removeNotInnerJoins = false) {
        return static::makeSelect([], $conditions, $configurator)->fetchCount($removeNotInnerJoins);
    }

    /**
     * @return null|string
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getLastQuery() {
        return static::getConnection()->getLastQuery();
    }
    
    static public function beginTransaction($readOnly = false, $transactionType = null) {
        static::getConnection()->begin($readOnly, $transactionType);
    }

    static public function inTransaction() {
        return static::getConnection()->inTransaction();
    }

    static public function commitTransaction() {
        static::getConnection()->commit();
    }

    static public function rollBackTransaction() {
        static::getConnection()->rollBack();
    }

    static public function quoteDbEntityName($name) {
        return static::getConnection()->quoteDbEntityName($name);
    }

    static public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return static::getConnection()->quoteValue($value, $fieldInfoOrType);
    }

    static public function query($query, $fetchData = null) {
        return static::getConnection()->query($query, $fetchData);
    }

    static public function exec($query) {
        return static::getConnection()->exec($query);
    }

    /**
     * @param array $data
     * @param bool|array $returning - return some data back after $data inserted to $table
     *          - true: return values for all columns of inserted table row
     *          - false: do not return anything
     *          - array: list of columns to return values for
     * @return array|bool - array returned only if $returning is not empty
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function insert(array $data, $returning = false) {
        return static::getConnection()->insert(
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function insertMany(array $columns, array $rows, $returning = false) {
        return static::getConnection()->insertMany(
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
     * @return int - number of modified rows
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function update(array $data, array $conditions, $returning = false) {
        return static::getConnection()->update(
            static::getName(),
            $data,
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    static public function delete(array $conditions = [], $returning = false) {
        return static::getConnection()->delete(
            static::getName(),
            Utils::assembleWhereConditionsFromArray(static::getConnection(), $conditions),
            $returning
        );
    }

    /**
     * Get list of PDO data types for requested $columns
     * @param array $columns
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
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