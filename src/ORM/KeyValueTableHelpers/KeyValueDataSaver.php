<?php

declare(strict_types=1);

namespace PeskyORM\ORM\KeyValueTableHelpers;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\TableInterface;

class KeyValueDataSaver extends Record
{
    
    protected static FakeTable $table;
    protected static KeyValueTableInterface $originalTable;
    protected string|int|float|null $_fkValue = null;
    protected array $_constantAdditionalData = [];
    
    public static function getTable(): TableInterface
    {
        return static::$table;
    }
    
    public static function saveKeyValuePairs(
        KeyValueTableInterface $table,
        array $originalData,
        array $newData,
        float|int|string|null $fkValue = null,
        array $constantAdditionalData = []
    ): void {
        /** @var array|Column[] $columns */
        $columns = [
            'fakeid' => Column::TYPE_INT,
        ];
        $tableStructure = $table->getTableStructure();
        foreach ($newData as $key => $value) {
            if ($tableStructure::hasColumn($key)) {
                $columns[$key] = $tableStructure::getColumn($key);
            } else {
                $columns[$key] = is_array($value) ? Column::TYPE_JSON : Column::TYPE_TEXT;
            }
        }
        static::$originalTable = $table;
        $fkName = $table->getMainForeignKeyColumnName();
        $fkName = empty($fkName) ? 'null' : "'{$fkName}'";
        static::$table = FakeTable::makeNewFakeTable(
            $table::getName(),
            $columns,
            null,
            [KeyValueTableInterface::class],
            [KeyValueTableHelpers::class],
            "public function getMainForeignKeyColumnName() {return {$fkName};}"
        );
        static::$table->getTableStructure()->markColumnAsPrimaryKey('fakeid');
        static::fromArray($originalData, true, false)
            ->updateValue(static::getPrimaryKeyColumn(), 0, true)
            ->updateValues($newData, false)
            ->saveToDb(array_keys($newData), $fkValue, $constantAdditionalData);
    }
    
    protected function saveToDb(
        array $columnsToSave = [],
        float|int|string|null $fkValue = null,
        array $constantAdditionalData = []
    ): void {
        $this->_fkValue = $fkValue;
        $this->_constantAdditionalData = $constantAdditionalData;
        parent::saveToDb($columnsToSave);
    }
    
    protected function collectValuesForSave(array &$columnsToSave, bool $isUpdate): array
    {
        $data = [];
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if ($column->isAutoUpdatingValue()) {
                $data[$columnName] = static::getColumn($columnName)
                    ->getAutoUpdateForAValue($this);
            } elseif (!$column->isItPrimaryKey()) {
                $data[$columnName] = $this->getValue($column);
            }
        }
        return $data;
    }
    
    protected function performDataSave(bool $isUpdate, array $data): bool
    {
        $table = static::$originalTable;
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            $table::updateOrCreateRecords(
                $table::convertToDataForRecords($data, $this->_fkValue, $this->_constantAdditionalData)
            );
            $table::commitTransaction();
            return true;
        } catch (\PDOException $exc) {
            if ($table::inTransaction()) {
                $table::rollBackTransaction();
            }
            throw $exc;
        }
    }
    
    protected function getColumnsNamesWithUpdatableValues(): array
    {
        return array_keys(static::getColumns());
    }
    
    public function existsInDb(bool $useDbQuery = false): bool
    {
        return true;
    }
    
    protected function _existsInDbViaQuery(): bool
    {
        return true;
    }
    
}
