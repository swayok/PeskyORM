<?php

namespace PeskyORM\ORM;

use Swayok\Utils\StringUtils;

abstract class DbTable implements DbTableInterface {

    /** @var $this */
    static protected $instance;
    /** @var string  */
    static protected $alias;
    /** @var DbTableStructure */
    static protected $tableSchema;

    /**
     * @return string
     */
    static public function getTableAlias() {
        if (static::$alias === null) {
            static::$alias = StringUtils::classify(static::getTableName());
        }
        return static::$alias;
    }

    /**
     * @return $this
     * @throws \PeskyORM\ORM\OrmException
     */
    static public function getInstance() {
        if (static::$instance === null) {
            if (!static::hasPkColumn()) {
                throw new OrmException(
                    'Table schema must contain primary key',
                    OrmException::CODE_INVALID_TABLE_SCHEMA
                );
            }
            static::$instance = static();
        }
        return static::$instance;
    }

    /**
     * Shortcut for static::getInstance()
     * @return $this
     * @throws \PeskyORM\ORM\OrmException
     */
    static public function _() {
        return static::getInstance();
    }
    
    /**
     * @return bool
     */
    static public function hasPkColumn() {
        return static::getStructure()->hasPkColumn();
    }
    
    /**
     * @return bool
     */
    static public function getPkColumn() {
        return static::getStructure()->getPkColumn();
    }

    /**
     * @return string
     */
    static public function getPkColumnName() {
        return static::getStructure()->getPkColumnName();
    }

    /**
     * @return DbTableStructure
     */
    static public function getStructure() {
        if (static::$tableSchema === null) {
            static::$tableSchema = DbClassesManager::getInstance()->getTableStructure(static::getTableName());
        }
        return static::$tableSchema;
    }

    /**
     * @param string $relationAlias - alias for relation defined in DbTableStructure
     * @return DbTable
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTable($relationAlias) {
        return static::getStructure()->getRelation($relationAlias)->getForeignTable();
    }

    /**
     * @param string $relationAlias - alias for relation defined in DbTableStructure
     * @return string
     * @throws \InvalidArgumentException
     */
    static public function getRelatedTableName($relationAlias) {
        return static::getStructure()->getRelation($relationAlias)->getForeignTableName();
    }

    /**
     * @return DbRecord
     */
    static public function newRecord() {
        return DbClassesManager::newRecord(static::getTableName());
    }

    /**
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return DbRecordsSet
     * @throws \InvalidArgumentException
     */
    static public function select($columns = '*', array $conditionsAndOptions = []) {
        return DbSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchMany();
    }
    
    /**
     * Selects only 1 column
     * @param string $column
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectColumn($column, array $conditionsAndOptions = []) {
        return DbSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->columns(['value' => $column])
            ->fetchColumn();
    }

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectAssoc($keysColumn, $valuesColumn, array $conditionsAndOptions = []) {
        return DbSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->fetchAssoc($keysColumn, $valuesColumn);
    }

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param array $conditionsAndOptions
     * @return array
     */
    static public function selectOne($columns, array $conditionsAndOptions) {
        return DbSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->columns($columns)
            ->fetchOne();
    }

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param string $expression - example: 'COUNT(*)', 'SUM(`field`)'
     * @param array $conditionsAndOptions
     * @return string
     * @throws \InvalidArgumentException
     */
    static public function selectValue($expression, array $conditionsAndOptions = []) {
        return DbSelect::from(static::getTableName())
            ->parseArray($conditionsAndOptions)
            ->fetchValue($expression);
    }

    /**
     * @param array $conditionsAndOptions
     * @return bool
     * @throws \InvalidArgumentException
     */
    static public function exists(array $conditionsAndOptions) {
        $conditionsAndOptions['LIMIT'] = 1;
        return (int)static::selectValue('1', $conditionsAndOptions) === 1;
    }

    /**
     * @return null|string
     */
    public function getLastQuery() {
        return static::getConnection()->getLastQuery();
    }
    
    public function beginTransaction($readOnly = false, $transactionType = null) {
        static::getConnection()->begin($readOnly, $transactionType);
    }

    public function inTransaction() {
        return static::getConnection()->inTransaction();
    }

    public function commitTransaction() {
        static::getConnection()->commit();
    }

    public function rollBackTransaction() {
        static::getConnection()->rollBack();
    }

    public function quoteName($name) {
        return static::getConnection()->quoteName($name);
    }

    public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return static::getConnection()->quoteValue($value, $fieldInfoOrType);
    }

    public function query($query, $fetchData = null) {
        return static::getConnection()->query($query, $fetchData);
    }

    public function exec($query) {
        return static::getConnection()->exec($query);
    }
}