<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Fakes\FakeTable;
use PeskyORM\ORM\Table\KeyValueTableInterface;
use PeskyORM\ORM\Table\KeyValueTableWorkflow;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use Swayok\Utils\NormalizeValue;

// todo: refactor key-value helper classes and system to be more clear
class KeyValueDataSaverRecord extends Record
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
        /** @var array|TableColumnInterface[] $columns */
        $columns = [
            'fakeid' => TableColumn::TYPE_INT,
        ];
        $tableStructure = $table->getTableStructure();
        foreach ($newData as $key => $value) {
            if ($tableStructure::hasColumn($key)) {
                $columns[$key] = $tableStructure::getColumn($key);
            } else {
                $columns[$key] = is_array($value) ? TableColumn::TYPE_JSON : TableColumn::TYPE_TEXT;
            }
        }
        static::$originalTable = $table;
        $fkName = $table::getMainForeignKeyColumnName();
        $fkName = empty($fkName) ? 'null' : "'{$fkName}'";
        static::$table = FakeTable::makeNewFakeTable(
            $table::getName(),
            $columns,
            null,
            [KeyValueTableInterface::class],
            [KeyValueTableWorkflow::class],
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

    /**
     * Convert associative array to arrays that represent DB record and are ready for saving to DB
     * @param array $valuesAssoc - associative array of settings
     * @param float|int|string|null $foreignKeyValue
     * @param array $additionalConstantValues - contains constant values for all records (for example: admin id)
     * @return array
     */
    protected function convertAssocDataToRecords(
        array $valuesAssoc,
        float|int|string|null $foreignKeyValue = null,
        array $additionalConstantValues = []
    ): array {
        $records = [];
        $table = static::$originalTable;
        $foreignKeyColumn = $table::getMainForeignKeyColumnName();
        if ($foreignKeyColumn) {
            if ($foreignKeyValue === null) {
                throw new \InvalidArgumentException('$foreignKeyValue argument cannot have null value');
            }
            $additionalConstantValues[$foreignKeyColumn] = $foreignKeyValue;
        }
        foreach ($valuesAssoc as $key => $value) {
            $records[] = static::fromArray(
                array_merge(
                    $additionalConstantValues,
                    [
                        $table::getKeysColumnName() => $key,
                        $table::getValuesColumnName() => static::encodeValue($value),
                    ],
                ), false
            );
        }
        return $records;
    }

    protected static function encodeValue(mixed $value): DbExpr|string
    {
        if ($value instanceof DbExpr) {
            return $value;
        }

        return NormalizeValue::normalizeJson($value);
    }

    protected function performDataSave(bool $isUpdate, array $data): bool
    {
        $table = static::$originalTable;
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            $records = $this->convertAssocDataToRecords(
                $data,
                $this->_fkValue,
                $this->_constantAdditionalData
            );
            foreach ($records as $record) {
                $table::saveRecord($record);
            }
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
