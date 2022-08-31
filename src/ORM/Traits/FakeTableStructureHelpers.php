<?php

namespace PeskyORM\ORM\Traits;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\TableStructure;

trait FakeTableStructureHelpers
{
    
    /**
     * @param array $columns - key-value array where key is column name and value is column type or
     *          key is int and value is column name or instance of Column class
     * @return static
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function setTableColumns(array $columns): static
    {
        $this->columns = [];
        foreach ($columns as $name => $typeOrColumnInstance) {
            if (is_int($name)) {
                $name = $typeOrColumnInstance;
                $typeOrColumnInstance = Column::TYPE_STRING;
            }
            if ($typeOrColumnInstance instanceof Column) {
                if ($typeOrColumnInstance->hasName()) {
                    $name = $typeOrColumnInstance->getName();
                } else {
                    $typeOrColumnInstance->setName($name);
                }
                $this->columns[$name] = $typeOrColumnInstance;
            } else {
                $this->columns[$name] = Column::create($typeOrColumnInstance, $name);
            }
            if ($this->columns[$name]->isItAFile()) {
                $this->fileColumns[] = $this->columns[$name];
            } elseif ($this->columns[$name]->isItPrimaryKey()) {
                $this->pk = $this->columns[$name];
            }
            if ($this->columns[$name]->isItExistsInDb()) {
                $this->columsThatExistInDb[$name] = $this->columns[$name];
            } else {
                $this->columsThatDoNotExistInDb[$name] = $this->columns[$name];
            }
        }
        $this->treatAnyColumnNameAsValid = count($this->columns) === 0;
        return $this;
    }
    
    public function markColumnAsPrimaryKey(string $columnName): static
    {
        $this->pk = static::getColumn($columnName)->primaryKey();
        return $this;
    }
    
    /**
     * @param TableStructure $structure
     * @param bool $append - true: existing structure will be appended; false - replaced
     * @return static
     */
    public function mimicTableStructure(TableStructure $structure, bool $append = false): static
    {
        if (!$append) {
            $this->columns = [];
            $this->relations = [];
            $this->fileColumns = [];
            $this->pk = null;
        }
        $this->columns = array_merge($this->columns, $structure::getColumns());
        $this->columsThatExistInDb = array_merge($this->columsThatExistInDb, $structure::getColumnsThatExistInDb());
        $this->columsThatDoNotExistInDb = array_merge($this->columsThatDoNotExistInDb, $structure::getColumnsThatDoNotExistInDb());
        $this->relations = array_merge($this->columns, $structure::getRelations());
        $this->columnsRelations = array_merge($this->columns, $structure::getColumnsRelations());
        $this->fileColumns = array_merge($this->fileColumns, $structure::getFileColumns());
        $this->pk = $structure::getPkColumn();
        $this->connectionName = $structure::getConnectionName(false);
        $this->connectionNameWritable = $structure::getConnectionName(true);
        $this->treatAnyColumnNameAsValid = count($this->columns) === 0;
        return $this;
    }
}