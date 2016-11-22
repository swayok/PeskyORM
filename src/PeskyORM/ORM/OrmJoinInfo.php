<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;

class OrmJoinInfo extends AbstractJoinInfo {

    /** @var TableInterface */
    protected $dbTable = null;
    /** @var TableInterface */
    protected $foreignDbTable = null;

    /**
     * @param string $joinName
     * @param TableInterface $dbTable
     * @param string $column
     * @param string $joinType
     * @param TableInterface $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function construct(
        $joinName,
        TableInterface $dbTable,
        $column,
        $joinType,
        TableInterface $foreignDbTable,
        $foreignColumn
    ) {
        return self::create($joinName)
            ->setConfigForLocalTable($dbTable, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignDbTable, $foreignColumn);
    }

    /**
     * @param TableInterface $dbTable
     * @param string $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable(TableInterface $dbTable, $column) {
        return $this->setDbTable($dbTable)->setColumnName($column);
    }

    /**
     * @param TableInterface $foreignDbTable
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable(TableInterface $foreignDbTable, $foreignColumn) {
        return $this->setForeignDbTable($foreignDbTable)->setForeignColumnName($foreignColumn);
    }

    /**
     * @param TableInterface $dbTable
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbTable(TableInterface $dbTable) {
        $this->dbTable = $dbTable;
        if ($this->tableAlias === null) {
            $this->setTableAlias($this->dbTable->getAlias());
        }
        return $this;
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
        return $this->getDbTable() !== null;
    }

    /**
     * @return TableInterface
     */
    public function getDbTable() {
        return $this->dbTable;
    }

    /**
     * @param TableInterface $foreignDbTable
     * @return $this
     */
    public function setForeignDbTable(TableInterface $foreignDbTable) {
        $this->foreignDbTable = $foreignDbTable;
        $this->foreignTableName = $foreignDbTable->getName();
        return $this;
    }

    /**
     * @return TableInterface
     */
    public function getForeignDbTable() {
        return $this->foreignDbTable;
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
                } else if (!$tableStruct::hasColumn($columnName)) {
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