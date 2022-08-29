<?php

declare(strict_types=1);

namespace PeskyORM\ORM\KeyValueTableHelpers;

use PeskyORM\Core\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\Relation;
use Swayok\Utils\NormalizeValue;

/**
 * @psalm-require-implements \PeskyORMLaravel\Db\KeyValueTableHelpers\KeyValueTableInterface
 */
trait KeyValueTableHelpers
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
    public function getMainForeignKeyColumnName(): ?string
    {
        /** @var KeyValueTableInterface $this */
        if (!$this->_detectedMainForeignKeyColumnName) {
            foreach (
                $this->getTableStructure()
                    ->getRelations() as $relationConfig
            ) {
                if ($relationConfig->getType() === Relation::BELONGS_TO) {
                    $this->_detectedMainForeignKeyColumnName = $relationConfig->getLocalColumnName();
                    break;
                }
            }
            if (!$this->_detectedMainForeignKeyColumnName) {
                throw new \BadMethodCallException(
                    __METHOD__ . '() - cannot find foreign key column name'
                );
            }
        }
        return $this->_detectedMainForeignKeyColumnName;
    }
    
    /**
     * Make array that represents DB record and can be saved to DB
     * @param string $key
     * @param mixed $value
     * @param mixed $foreignKeyValue
     * @return array
     */
    public static function makeDataForRecord(string $key, $value, $foreignKeyValue = null): array
    {
        $record = [
            static::getKeysColumnName() => $key,
            static::getValuesColumnName() => static::encodeValue($value),
        ];
        if ($foreignKeyValue !== null) {
            /** @var KeyValueTableInterface $table */
            $table = static::getInstance();
            $foreignKeyColumn = $table->getMainForeignKeyColumnName();
            if ($foreignKeyColumn) {
                $record[$foreignKeyColumn] = $foreignKeyValue;
            }
        }
        return $record;
    }
    
    /**
     * @param mixed $value
     * @return string|DbExpr
     */
    public static function encodeValue($value)
    {
        if ($value instanceof DbExpr) {
            return $value;
        } else {
            return NormalizeValue::normalizeJson($value);
        }
    }
    
    /**
     * Convert associative array to arrays that represent DB record and are ready for saving to DB
     * @param array $settingsAssoc - associative array of settings
     * @param mixed $foreignKeyValue
     * @param array $additionalConstantValues - contains constant values for all records (for example: admin id)
     * @return array
     */
    public static function convertToDataForRecords(
        array $settingsAssoc,
        $foreignKeyValue = null,
        array $additionalConstantValues = []
    ): array {
        $records = [];
        foreach ($settingsAssoc as $key => $value) {
            $records[] = array_merge(
                $additionalConstantValues,
                static::makeDataForRecord($key, $value, $foreignKeyValue)
            );
        }
        return $records;
    }
    
    /**
     * Decode values for passed settings associative array
     * @param array $settingsAssoc
     * @return array
     */
    public static function decodeValues(array $settingsAssoc): array
    {
        foreach ($settingsAssoc as &$value) {
            $value = static::decodeValue($value);
        }
        return $settingsAssoc;
    }
    
    /**
     * @param string|array $encodedValue
     * @return mixed
     */
    public static function decodeValue($encodedValue)
    {
        return is_array($encodedValue) ? $encodedValue : json_decode($encodedValue, true);
    }
    
    /**
     * Update: added values decoding
     * @param string|null|DbExpr $keysColumn
     * @param string|null|DbExpr $valuesColumn
     * @param array $conditions
     * @param \Closure|null $configurator
     * @return array
     */
    public static function selectAssoc(
        $keysColumn = null,
        $valuesColumn = null,
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
    
    /**
     * Update existing value or create new one
     * @param array $data - must contain: key, foreign_key, value
     * @return Record
     * @throws InvalidDataException
     */
    public static function updateOrCreateRecord(array $data): RecordInterface
    {
        if (empty($data[static::getKeysColumnName()])) {
            throw new \InvalidArgumentException(
                '$record argument does not contain value for key \'' . static::getKeysColumnName() . '\' or its value is empty'
            );
        } elseif (!array_key_exists(static::getValuesColumnName(), $data)) {
            throw new \InvalidArgumentException(
                '$record argument does not contain value for key \'' . static::getValuesColumnName() . '\' or its value is empty'
            );
        }
        $conditions = [
            static::getKeysColumnName() => $data[static::getKeysColumnName()],
        ];
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        $fkName = $table->getMainForeignKeyColumnName();
        if (!empty($fkName)) {
            if (empty($data[$fkName])) {
                throw new \InvalidArgumentException("\$record argument does not contain value for key '{$fkName}' or its value is empty");
            }
            $conditions[$fkName] = $data[$fkName];
        }
        $object = $table->newRecord()
            ->fetch($conditions);
        if ($object->existsInDb()) {
            $success = $object
                ->begin()
                ->updateValues(array_diff_key($data, [static::getKeysColumnName() => '', $fkName => '']), false)
                ->commit();
        } else {
            $object
                ->reset()
                ->updateValues($data, false);
            $success = $object->save();
        }
        return $success;
    }
    
    public static function updateOrCreateRecords(array $records): void
    {
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            foreach ($records as $record) {
                $table::updateOrCreateRecord($record);
            }
            if (!$alreadyInTransaction) {
                $table::commitTransaction();
            }
        } catch (\Exception $exc) {
            if (!$alreadyInTransaction && $table::inTransaction()) {
                $table::rollBackTransaction();
            }
            throw $exc;
        }
    }
    
    /**
     * @param string $key
     * @param mixed $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed $default
     * @param bool $ignoreEmptyValue
     *      - true: if value recorded to DB is empty - returns $default
     *      - false: returns any value from DB if it exists
     * @return mixed
     */
    public static function getValue(string $key, $foreignKeyValue = null, $default = null, bool $ignoreEmptyValue = false)
    {
        return static::getFormattedValue($key, null, $foreignKeyValue, $default, $ignoreEmptyValue);
    }
    
    /**
     * @param string $key
     * @param string|null $format - get formatted version of value
     * @param mixed $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed|\Closure $default
     * @param bool $ignoreEmptyValue
     *      - true: if value recorded to DB is empty - returns $default
     *      - false: returns any value from DB if it exists
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function getFormattedValue(
        string $key,
        ?string $format,
        $foreignKeyValue = null,
        $default = null,
        bool $ignoreEmptyValue = false
    ) {
        $recordData = static::findRecordForKey($key, $foreignKeyValue);
        
        $defaultClosure = ($default instanceof \Closure)
            ? $default
            : function () use ($default) {
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
        $fkName = $table->getMainForeignKeyColumnName();
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
    
    /**
     * @param array $recordData
     * @param string $key
     * @param string $format
     * @param \Closure $defaultClosure
     * @param bool $ignoreEmptyValue
     * @return mixed
     */
    protected static function getFormattedValueFromRecordData(
        array $recordData,
        string $key,
        string $format,
        \Closure $defaultClosure,
        bool $ignoreEmptyValue
    ) {
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        if ($table->getTableStructure()->hasColumn($key)) {
            // modify value so that it is processed by custom column defined in table structure
            // if $recordData is empty it uses default value provided by $column prior to $default
            $column = $table->getTableStructure()->getColumn($key);
            if (!$column->isItExistsInDb()) {
                $recordObj = $table->newRecord();
                if (empty($recordData)) {
                    return $recordObj->hasValue($column, true) ? $recordObj->getValue($column, $format) : $defaultClosure();
                } else {
                    $value = $recordObj
                        ->updateValue($column, static::decodeValue($recordData[static::getValuesColumnName()]), false)
                        ->getValue($column, $format);
                    return ($ignoreEmptyValue && static::isEmptyValue($value)) ? $defaultClosure() : $value;
                }
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
     * @param mixed $foreignKeyValue
     * @param bool $ignoreEmptyValues
     *      - true: return only not empty values stored in DB
     *      - false: return all values strored in db
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getValuesForForeignKey($foreignKeyValue = null, bool $ignoreEmptyValues = false): array
    {
        $conditions = [];
        /** @var KeyValueTableInterface $table */
        $table = static::getInstance();
        $fkName = $table->getMainForeignKeyColumnName();
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
                if (!$column->isItExistsInDb()) {
                    $isJson = in_array($column->getType(), [$column::TYPE_JSON, $column::TYPE_JSONB], true);
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
