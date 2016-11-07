<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbJoinConfig;

class OrmJoinConfig extends DbJoinConfig {

    /** @var DbTableInterface */
    protected $dbTable = null;
    /** @var DbTableInterface */
    protected $foreignDbTable = null;

    /**
     * @param string $joinName
     * @param DbTableInterface $dbTable
     * @param string $column
     * @param string $joinType
     * @param DbTableInterface $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function construct(
        $joinName,
        DbTableInterface $dbTable,
        $column,
        $joinType,
        DbTableInterface $foreignDbTable,
        $foreignColumn
    ) {
        return self::create($joinName)
            ->setConfigForLocalTable($dbTable, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignDbTable, $foreignColumn);
    }

    /**
     * @param DbTableInterface $dbTable
     * @param string $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable(DbTableInterface $dbTable, $column) {
        return $this->setDbTable($dbTable)->setColumnName($column);
    }

    /**
     * @param DbTableInterface $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable(DbTableInterface $foreignDbTable, $foreignColumn) {
        return $this->setForeignDbTable($foreignDbTable)->setForeignColumnName($foreignColumn);
    }

    /**
     * @param DbTableInterface $dbTable
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbTable(DbTableInterface $dbTable) {
        $this->dbTable = $dbTable;
        if ($this->tableAlias === null) {
            $this->setTableAlias($this->dbTable->getAlias());
        }
        return $this;
    }

    /**
     * @param string $tableName
     * @return $this
     * @throws \BadMethodCallException
     */
    public function setTableName($tableName) {
        throw new \BadMethodCallException('You must use ' . get_class($this) . '->setDbTable() instead');
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->getDbTable()->getTableStructure()->getTableName();
    }

    /**
     * @return bool
     */
    protected function hasTableName() {
        return !empty($this->getDbTable());
    }

    /**
     * @return DbTableInterface
     */
    public function getDbTable() {
        return $this->dbTable;
    }

    /**
     * @param DbTableInterface $foreignDbTable
     * @return $this
     */
    public function setForeignDbTable(DbTableInterface $foreignDbTable) {
        $this->foreignDbTable = $foreignDbTable;
        $this->foreignTableName = $foreignDbTable->getName();
        return $this;
    }

    /**
     * @param string $foreignTableName
     * @return $this
     * @throws \BadMethodCallException
     */
    public function setForeignTableName($foreignTableName) {
        throw new \BadMethodCallException('You must use ' . get_class($this) . '->setForeignDbTable() instead');
    }

    /**
     * @return DbTableInterface
     */
    public function getForeignDbTable() {
        return $this->foreignDbTable;
    }

    public function setForeignTableSchema($schema) {
        throw new \BadMethodCallException('This method cannot be used in ' . get_class($this));
    }

    /**
     * @param array $columns
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setForeignColumnsToSelect(...$columns) {
        if (count($columns) === 1 && is_array($columns[0])) {
            /** @var array $columns */
            $columns = $columns[0];
        }
        $this->foreignColumnsToSelect = [];
        $tableStruct = $this->getForeignDbTable()->getTableStructure();
        foreach ($columns as $columnAlias => $columnName) {
            if ($columnName !== '*') {
                if (!is_string($columnName)) {
                    throw new \InvalidArgumentException(
                        "\$columns argument contains non-string column name on key '{$columnAlias}' for join named '{$this->getJoinName()}'"
                    );
                } else if (!$tableStruct->hasColumn($columnName)) {
                    throw new \InvalidArgumentException(
                        "Column with name [{$this->getJoinName()}.{$columnName}]"
                            . (is_int($columnAlias) ? '' : " and alias [{$columnAlias}]")
                            . ' not found in ' . get_class($tableStruct)
                    );
                }
                $this->foreignColumnsToSelect[$columnAlias] = $columnName;
            } else {
                foreach ($this->getForeignDbTable()->getTableStructure()->getColumns() as $knownColumnName => $columnInfo) {
                    if ($columnInfo->isItExistsInDb() && !in_array($knownColumnName, $columns, true)) {
                        // add only columns still not listed here
                        $this->foreignColumnsToSelect[] = $knownColumnName;
                    }
                }
            }
        }
        return $this;
    }


}