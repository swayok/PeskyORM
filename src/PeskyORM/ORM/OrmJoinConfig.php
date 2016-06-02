<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbJoinConfig;

class OrmJoinConfig extends DbJoinConfig {

    /** @var DbTable */
    protected $dbTable = null;
    /** @var DbTable */
    protected $foreignDbTable = null;

    /**
     * @param string $joinName
     * @param DbTable $dbTable
     * @param string $column
     * @param string $joinType
     * @param DbTable $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function construct(
        $joinName,
        DbTable $dbTable,
        $column,
        $joinType,
        DbTable $foreignDbTable,
        $foreignColumn
    ) {
        return self::create($joinName)
            ->setConfigForLocalTable($dbTable, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignDbTable, $foreignColumn);
    }

    /**
     * @param DbTable $dbTable
     * @param string $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable(DbTable $dbTable, $column) {
        return $this->setDbTable($dbTable)->setColumnName($column);
    }

    /**
     * @param DbTable $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable(DbTable $foreignDbTable, $foreignColumn) {
        return $this->setForeignDbTable($foreignDbTable)->setForeignColumnName($foreignColumn);
    }

    /**
     * @param DbTable $dbTable
     * @return $this
     */
    public function setDbTable(DbTable $dbTable) {
        $this->dbTable = $dbTable;
        return $this;
    }

    /**
     * @param DbTable $foreignDbTable
     * @return $this
     */
    public function setForeignDbTable(DbTable $foreignDbTable) {
        $this->foreignDbTable = $foreignDbTable;
        $this->foreignTableName = $foreignDbTable->getTableName();
        return $this;
    }

    /**
     * @param string $foreignTableName
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setForeignTableName($foreignTableName) {
        $foreignDbTable = $this->getForeignDbTable();
        if (!$foreignDbTable || $foreignDbTable->getTableName() !== $foreignTableName) {
            $this->setForeignDbTable(DbClassesManager::i()->getTableInstance($foreignTableName));
        } else {
            parent::setForeignTableName($foreignTableName);
        }
        return $this;
    }

    /**
     * @return DbTable
     */
    public function getForeignDbTable() {
        return $this->foreignDbTable;
    }

    /**
     * @param string $tableName
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setTableName($tableName) {
        $dbTable = $this->getDbTable();
        if (!$dbTable || $dbTable->getTableName() !== $tableName) {
            $this->setDbTable(DbClassesManager::i()->getTableInstance($tableName));
        } else {
            parent::setTableName($tableName);
        }
        return $this;
    }

    /**
     * @return DbTable
     */
    public function getDbTable() {
        return $this->dbTable;
    }


}