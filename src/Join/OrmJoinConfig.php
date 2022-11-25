<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\ORM\Table\TableInterface;

class OrmJoinConfig extends NormalJoinConfigAbstract implements OrmJoinConfigInterface
{
    protected TableInterface $localTable;
    protected TableInterface $foreignTable;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $joinName,
        string $joinType,
        string $localTableAlias,
        string $localColumnName,
        TableInterface $foreignTable,
        string $foreignColumnName
    ) {
        parent::__construct($joinName, $joinType);
        $this->setLocalTableAlias($localTableAlias)
            ->setLocalColumnName($localColumnName)
            ->setForeignTable($foreignTable)
            ->setForeignColumnName($foreignColumnName);
    }

    protected function setForeignTable(TableInterface $foreignDbTable): static
    {
        $this->foreignTable = $foreignDbTable;
        $this->foreignTableName = $foreignDbTable->getName();
        $this->foreignTableSchema = $foreignDbTable->getTableStructure()->getSchema();
        return $this;
    }
    
    public function getForeignTable(): TableInterface
    {
        return $this->foreignTable;
    }
    
    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnsToSelect(array $columns): static
    {
        $this->foreignColumnsToSelect = [];
        $tableStructure = $this->getForeignTable()->getTableStructure();
        foreach ($columns as $columnAlias => $columnName) {
            if ($columnName !== '*') {
                if (!is_string($columnName)) {
                    throw new \InvalidArgumentException(
                        "\$columns argument contains non-string column name on key '{$columnAlias}' for join named '{$this->getJoinName()}'"
                    );
                }

                if (!$tableStructure::hasColumn($columnName)) {
                    throw new \InvalidArgumentException(
                        "TableColumn with name [{$this->getJoinName()}.{$columnName}]"
                        . (is_int($columnAlias) ? '' : " and alias [{$columnAlias}]")
                        . ' not found in ' . get_class($tableStructure)
                    );
                }
                $this->foreignColumnsToSelect[$columnAlias] = $columnName;
            } else {
                $knownColumns = $this->getForeignTable()
                    ->getTableStructure()
                    ->getColumns();
                foreach ($knownColumns as $knownColumnName => $columnInfo) {
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
