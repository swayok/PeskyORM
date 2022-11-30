<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;

/**
 * @psalm-require-implements \PeskyORM\ORM\Table\KeyValueTableInterface
 * todo: refactor key-value helper classes and system to be more clear
 */
trait KeyValueTableWorkflow
{
    private ?string $_detectedMainForeignKeyColumnName = null;
    
    public static function getKeysColumnName(): string
    {
        return 'key';
    }
    
    public static function getValuesColumnName(): string
    {
        return 'value';
    }
    
    /**
     * Override if you wish to provide key manually
     * @return string|null - null returned when there is no foreign key
     * @throws \BadMethodCallException
     */
    public static function getMainForeignKeyColumnName(): ?string
    {
        // todo: remove this - it is better for this method to be implemented in specific Table class
        $instance = static::getInstance();
        /** @var KeyValueTableInterface $this */
        if (!$instance->_detectedMainForeignKeyColumnName) {
            foreach ($instance->getTableStructure()->getRelations() as $relationConfig) {
                if ($relationConfig->getType() === RelationInterface::BELONGS_TO) {
                    $instance->_detectedMainForeignKeyColumnName = $relationConfig->getLocalColumnName();
                    break;
                }
            }
            if (!$instance->_detectedMainForeignKeyColumnName) {
                throw new \BadMethodCallException(
                    __METHOD__ . '() - cannot find foreign key column name'
                );
            }
        }
        return $instance->_detectedMainForeignKeyColumnName;
    }
    
    /**
     * Decode values for passed settings associative array
     */
    public static function decodeValues(array $settingsAssoc): array
    {
        foreach ($settingsAssoc as &$value) {
            $value = static::decodeValue($value);
        }
        return $settingsAssoc;
    }
    
    public static function decodeValue(array|string $encodedValue): mixed
    {
        return is_array($encodedValue) ? $encodedValue : json_decode($encodedValue, true);
    }
    
    /**
     * Update: added values decoding
     */
    public static function selectAssoc(
        string|DbExpr|null $keysColumn = null,
        string|DbExpr|null $valuesColumn = null,
        array $conditions = [],
        ?\Closure $configurator = null
    ): array {
        if ($keysColumn === null) {
            $keysColumn = static::getKeysColumnName();
        }
        if ($valuesColumn === null) {
            $valuesColumn = static::getValuesColumnName();
        }
        return static::decodeValues(parent::selectAssoc($keysColumn, $valuesColumn, $conditions, $configurator));
    }
    
    public static function saveRecord(RecordInterface $record): RecordInterface
    {
        if (!is_a($record::getTable(), static::class)) {
            throw new \InvalidArgumentException(
                '$record argument is not a valid Record for table ' . static::class
            );
        }
        $conditions = [
            static::getKeysColumnName() => $record[static::getKeysColumnName()],
        ];

        $fkName = static::getMainForeignKeyColumnName();
        if (!empty($fkName)) {
            if (empty($record[$fkName])) {
                throw new \InvalidArgumentException(
                    "\$record argument does not contain value for key '{$fkName}' or its value is empty"
                );
            }
            $conditions[$fkName] = $record[$fkName];
        }
        $pkColumn = static::getPkColumn();
        $pkValue = static::selectColumnValue($pkColumn, $conditions);
        if ($pkValue) {
            $record->updateValue($pkColumn, $pkValue, true);
        }
        $record->save();
        return $record;
    }
    
    /**
     * @param string $key
     * @param float|int|string|null $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed|null $default
     * @param bool $ignoreEmptyValue
     *      - true: if value recorded to DB is empty - returns $default
     *      - false: returns any value from DB if it exists
     * @return mixed
     */
    public static function getValue(
        string $key,
        float|int|string|null $foreignKeyValue = null,
        mixed $default = null,
        bool $ignoreEmptyValue = false
    ): mixed {
        return static::getFormattedValue($key, null, $foreignKeyValue, $default, $ignoreEmptyValue);
    }
    
    /**
     * @param string $key
     * @param string|null $format - get formatted version of value
     * @param float|int|string|null $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed|null $default
     * @param bool $ignoreEmptyValue
     *      - true: if value recorded to DB is empty - returns $default
     *      - false: returns any value from DB if it exists
     * @return mixed
     */
    public static function getFormattedValue(
        string $key,
        ?string $format,
        float|int|string|null $foreignKeyValue = null,
        mixed $default = null,
        bool $ignoreEmptyValue = false
    ): mixed {
        $recordData = static::findRecordForKey($key, $foreignKeyValue);
        
        $defaultClosure = ($default instanceof \Closure)
            ? $default
            : static function () use ($default) {
                return $default;
            };
        return static::getFormattedValueFromRecordData($recordData, $key, $format, $defaultClosure, $ignoreEmptyValue);
    }
    
    protected static function findRecordForKey(string $key, $foreignKeyValue): array
    {
        $conditions = [
            static::getKeysColumnName() => $key,
        ];
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        $fkName = $table::getMainForeignKeyColumnName();
        if ($fkName !== null) {
            if (empty($foreignKeyValue)) {
                throw new \InvalidArgumentException('$foreignKeyValue argument is required');
            }
            $conditions[$fkName] = $foreignKeyValue;
        } elseif (!empty($foreignKeyValue)) {
            throw new \InvalidArgumentException(
                '$foreignKeyValue must be null when model does not have main foreign key column'
            );
        }
        return static::selectOne('*', $conditions);
    }
    
    protected static function getFormattedValueFromRecordData(
        array $recordData,
        string $key,
        string $format,
        \Closure $defaultClosure,
        bool $ignoreEmptyValue
    ): mixed {
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        if ($table->getTableStructure()->hasColumn($key)) {
            // modify value so that it is processed by custom column defined in table structure
            // if $recordData is empty it uses default value provided by $column prior to $default
            $column = $table->getTableStructure()->getColumn($key);
            if (!$column->isReal()) {
                $recordObj = $table->newRecord();
                if (empty($recordData)) {
                    return $recordObj->hasValue($column, true) ? $recordObj->getValue($column, $format) : $defaultClosure();
                }

                $value = $recordObj
                    ->updateValue($column, static::decodeValue($recordData[static::getValuesColumnName()]), false)
                    ->getValue($column, $format);

                return ($ignoreEmptyValue && static::isEmptyValue($value)) ? $defaultClosure() : $value;
            }
        }
        if (empty($recordData)) {
            return $defaultClosure();
        }
        $value = static::decodeValue($recordData[static::getValuesColumnName()]);
        if ($ignoreEmptyValue && static::isEmptyValue($value)) {
            return $defaultClosure();
        }
        return $value;
    }
    
    private static function isEmptyValue($value): bool
    {
        return (
            $value === null
            || (is_array($value) && count($value) === 0)
            || in_array($value, ['', '[]', '""', "{}"], true)
        );
    }
    
    /**
     * @param float|int|string|null $foreignKeyValue
     * @param bool $ignoreEmptyValues
     *      - true: return only not empty values stored in DB
     *      - false: return all values strored in db
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getValuesForForeignKey(float|int|string|null $foreignKeyValue = null, bool $ignoreEmptyValues = false): array
    {
        $conditions = [];
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        $fkName = $table::getMainForeignKeyColumnName();
        if ($fkName !== null) {
            if (empty($foreignKeyValue)) {
                throw new \InvalidArgumentException('$foreignKeyValue argument is required');
            }
            $conditions[$fkName] = $foreignKeyValue;
        } elseif (!empty($foreignKeyValue)) {
            throw new \InvalidArgumentException(
                '$foreignKeyValue must be null when model does not have main foreign key column'
            );
        }
        $data = static::selectAssoc(
            static::getKeysColumnName(),
            static::getValuesColumnName(),
            $conditions
        );
        if (!empty($data)) {
            // modify values so that they are processed by custom columns defined in table structure + set defaults
            $columns = $table->getTableStructure()->getColumns();
            $data[$table::getPkColumnName()] = 0;
            $record = $table->newRecord()->updateValues($data, true, false);
            foreach ($columns as $columnName => $column) {
                if (!$column->isReal()) {
                    $isJson = in_array($column->getDataType(), [$column::TYPE_JSON, $column::TYPE_JSONB], true);
                    if (
                        (
                            array_key_exists($columnName, $data)
                            && $record->hasValue($column)
                        )
                        || $record->hasValue($column, true)
                    ) {
                        // has processed value or default value
                        $data[$columnName] = $record->getValue($column, $isJson ? 'array' : null);
                    }
                    if ($ignoreEmptyValues && static::isEmptyValue($data[$columnName] ?? null)) {
                        unset($data[$columnName]);
                    }
                }
            }
        }
        return $data;
    }
}
