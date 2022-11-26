<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Fakes;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\ORM\TableStructure\TableStructureInterface;

trait FakeTableStructureHelpers
{
    
    /**
     * @param array $columns - key-value array where key is column name and value is column type or
     *          key is int and value is column name or instance of TableColumn class
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
                $typeOrColumnInstance = TableColumn::TYPE_STRING;
            }
            if ($typeOrColumnInstance instanceof TableColumn) {
                if ($typeOrColumnInstance->hasName()) {
                    $name = $typeOrColumnInstance->getName();
                } else {
                    $typeOrColumnInstance->setName($name);
                }
                $this->columns[$name] = $typeOrColumnInstance;
            } else {
                $this->columns[$name] = TableColumn::create($typeOrColumnInstance, $name);
            }
            if ($this->columns[$name]->isPrimaryKey()) {
                $this->pk = $this->columns[$name];
            }
            if ($this->columns[$name]->isReal()) {
                $this->realColumns[$name] = $this->columns[$name];
            } else {
                $this->virtualColumns[$name] = $this->columns[$name];
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
    public function mimicTableStructure(TableStructureInterface $structure, bool $append = false): static
    {
        if (!$append) {
            $this->columns = [];
            $this->relations = [];
            $this->pk = null;
        }
        $this->columns = array_merge($this->columns, $structure::getColumns());
        $this->realColumns = array_merge($this->realColumns, $structure::getRealColumns());
        $this->virtualColumns = array_merge($this->virtualColumns, $structure::getVirtualColumns());
        $this->relations = array_merge($this->columns, $structure::getRelations());
        $this->pk = $structure::getPkColumn();
        $this->connectionName = $structure::getConnectionName(false);
        $this->connectionNameWritable = $structure::getConnectionName(true);
        $this->treatAnyColumnNameAsValid = count($this->columns) === 0;
        return $this;
    }
}