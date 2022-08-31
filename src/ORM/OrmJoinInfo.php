<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;

class OrmJoinInfo extends AbstractJoinInfo
{
    
    protected TableInterface $dbTable;
    protected TableInterface $foreignDbTable;
    
    public static function create(
        string $joinName,
        TableInterface $localTable,
        string $localColumnName,
        string $joinType,
        TableInterface $foreignTable,
        string $foreignColumnName
    ): static {
        return new static(
            $joinName,
            $localTable,
            $localColumnName,
            $joinType,
            $foreignTable,
            $foreignColumnName
        );
    }
    
    public function __construct(
        string $joinName,
        TableInterface $localTable,
        string $localColumnName,
        string $joinType,
        TableInterface $foreignTable,
        string $foreignColumnName
    ) {
        parent::__construct($joinName);
        $this
            ->setConfigForLocalTable($localTable, $localColumnName)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignTable, $foreignColumnName);
    }
    
    public function setConfigForLocalTable(TableInterface $table, string $columnName): static
    {
        return $this
            ->setDbTable($table)
            ->setColumnName($columnName);
    }
    
    public function setConfigForForeignTable(TableInterface $foreignTable, string $foreignColumnName): static
    {
        return $this
            ->setForeignDbTable($foreignTable)
            ->setForeignColumnName($foreignColumnName);
    }
    
    public function setDbTable(TableInterface $dbTable): static
    {
        $this->dbTable = $dbTable;
        $this->tableName = $dbTable->getName();
        $this->tableSchema = $dbTable->getTableStructure()
            ->getSchema();
        if ($this->tableAlias === null) {
            $this->setTableAlias($this->dbTable::getAlias());
        }
        return $this;
    }
    
    public function getDbTable(): TableInterface
    {
        return $this->dbTable;
    }
    
    public function setForeignDbTable(TableInterface $foreignDbTable): static
    {
        $this->foreignDbTable = $foreignDbTable;
        $this->foreignTableName = $foreignDbTable->getName();
        $this->foreignTableSchema = $foreignDbTable->getTableStructure()->getSchema();
        return $this;
    }
    
    public function getForeignDbTable(): TableInterface
    {
        return $this->foreignDbTable;
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnsToSelect(...$columns): static
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            /** @var array $columns */
            $columns = $columns[0];
        }
        $this->foreignColumnsToSelect = [];
        $tableStruct = $this->getForeignDbTable()
            ->getTableStructure();
        foreach ($columns as $columnAlias => $columnName) {
            if ($columnName !== '*') {
                if (!is_string($columnName)) {
                    throw new \InvalidArgumentException(
                        "\$columns argument contains non-string column name on key '{$columnAlias}' for join named '{$this->getJoinName()}'"
                    );
                } elseif (!$tableStruct::hasColumn($columnName)) {
                    throw new \InvalidArgumentException(
                        "Column with name [{$this->getJoinName()}.{$columnName}]"
                        . (is_int($columnAlias) ? '' : " and alias [{$columnAlias}]")
                        . ' not found in ' . get_class($tableStruct)
                    );
                }
                $this->foreignColumnsToSelect[$columnAlias] = $columnName;
            } else {
                foreach (
                    $this->getForeignDbTable()
                        ->getTableStructure()
                        ->getColumns() as $knownColumnName => $columnInfo
                ) {
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
