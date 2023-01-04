<?php

namespace PeskyORM\ORM;

use Swayok\Utils\Set;

class RecordsArray implements \ArrayAccess, \Iterator, \Countable
{
    
    /**
     * @var TableInterface
     */
    protected $table;
    /**
     * @var array[]|Record[]
     */
    protected $records = [];
    /**
     * @var bool
     */
    protected $isRecordsContainObjects = false;
    /**
     * @var
     */
    protected $iteratorPosition = 0;
    /**
     * @var Record[]
     */
    protected $dbRecords = [];
    /**
     * @var bool
     */
    protected $isFromDb = null;
    /**
     * @var bool
     */
    protected $isDbRecordInstanceReuseEnabled = false;
    /**
     * @var bool
     */
    protected $isDbRecordDataValidationDisabled = false;
    /**
     * @var Record
     */
    protected $dbRecordForIteration = null;
    /**
     * @var int
     */
    protected $currentDbRecordIndex = -1;
    /**
     * @var bool
     */
    protected $readOnlyMode = false;
    /**
     * @var string[] - relation names
     */
    protected $hasManyRelationsInjected = [];
    
    /**
     * @param TableInterface $table
     * @param array|RecordInterface[]|Record[] $records
     * @param bool|null $isFromDb - true: records are from db | null - autodetect
     * @param bool $disableDbRecordDataValidation
     * @throws \InvalidArgumentException
     */
    public function __construct(TableInterface $table, array $records, ?bool $isFromDb = null, bool $disableDbRecordDataValidation = false)
    {
        $this->table = $table;
        $this->setIsDbRecordDataValidationDisabled($disableDbRecordDataValidation);
        if (count($records)) {
            $recordClass = get_class($this->getNewRecord());
            /** @var array|RecordInterface[] $records */
            $records = array_values($records);
            foreach ($records as $index => $record) {
                if (is_array($record)) {
                    $this->records[$index] = $record;
                } elseif ($record instanceof $recordClass) {
                    /** @var Record $record */
                    if ($this->isDbRecordDataValidationDisabled()) {
                        $record->enableTrustModeForDbData();
                    } else {
                        $record->disableTrustModeForDbData();
                    }
                    $this->records[$index] = $record;
                    $this->dbRecords[$index] = $record;
                    $this->isRecordsContainObjects = true;
                } else {
                    throw new \InvalidArgumentException('$dbSelectOrRecords must contain only arrays or objects of class ' . $recordClass);
                }
            }
            $this->records = array_values($records);
        }
        $this->isFromDb = $isFromDb;
    }
    
    protected function getNewRecord(): RecordInterface
    {
        return $this->table->newRecord();
    }
    
    /**
     * Inject data from HAS MANY relation into records
     * @param string $relationName
     * @param array $columnsToSelect - see \PeskyORM\Core\AbstractSelect::columns()
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function injectHasManyRelationData($relationName, array $columnsToSelect = ['*'])
    {
        $relation = $this->table->getTableStructure()
            ->getRelation($relationName);
        if ($relation->getType() !== $relation::HAS_MANY) {
            throw new \InvalidArgumentException(
                'Relation must be of a \'' . $relation::HAS_MANY . "' type but relation '$relationName' is of type '{$relation->getType()}'"
            );
        }
        if (!in_array($relationName, $this->hasManyRelationsInjected, true)) {
            $this->injectHasManyRelationDataIntoRecords($relation, $columnsToSelect);
        }
        return $this;
    }
    
    /**
     * @param Relation $relation
     * @param array $columnsToSelect
     */
    protected function injectHasManyRelationDataIntoRecords(Relation $relation, array $columnsToSelect = ['*'])
    {
        $relationName = $relation->getName();
        $localColumnName = $relation->getLocalColumnName();
        $ids = $this->getValuesForColumn($localColumnName, null, function ($value) {
            return !empty($value);
        });
        if (count($ids)) {
            $relatedRecordsGrouped = Set::combine(
                $relation->getForeignTable()
                    ->select($columnsToSelect, [$relation->getForeignColumnName() => $ids])
                    ->toArrays(),
                '/@',
                '/',
                '/' . $relation->getForeignColumnName()
            );
            foreach ($this->getRecords() as $index => $record) {
                $relatedRecords = [];
                if (
                    isset($record[$localColumnName], $relatedRecordsGrouped[$record[$localColumnName]])
                    && is_array($relatedRecordsGrouped[$record[$localColumnName]])
                ) {
                    $relatedRecords = $relatedRecordsGrouped[$record[$localColumnName]];
                }
                if ($record instanceof RecordInterface) {
                    $this->records[$index] = $this->records[$index]->toArray([], ['*'], false);
                    unset($this->dbRecords[$index]);
                }
                $this->records[$index][$relationName] = array_values($relatedRecords);
            }
        }
        $this->hasManyRelationsInjected[] = $relationName;
    }
    
    /**
     * @return RecordInterface|Record
     */
    protected function getDbRecordObjectForIteration()
    {
        if ($this->dbRecordForIteration === null) {
            $this->dbRecordForIteration = $this->getNewRecord();
            if ($this->isReadOnlyModeEnabled()) {
                $this->dbRecordForIteration->enableReadOnlyMode();
            } else {
                $this->dbRecordForIteration->disableReadOnlyMode();
                if ($this->isDbRecordDataValidationDisabled()) {
                    $this->dbRecordForIteration->enableTrustModeForDbData();
                } else {
                    $this->dbRecordForIteration->disableTrustModeForDbData();
                }
            }
        }
        return $this->dbRecordForIteration;
    }
    
    /**
     * Reset stored data
     * Note: RecordsArray instance won't be usable after this while RecordsSet can fetch data again
     * @return $this
     */
    public function resetRecords()
    {
        $this->records = [];
        $this->dbRecords = [];
        $this->rewind();
        $this->currentDbRecordIndex = -1;
        return $this;
    }
    
    protected function getRecords(): array
    {
        return $this->records;
    }
    
    public function areRecordsFetchedFromDb(): bool
    {
        return true;
    }
    
    /**
     * @param int $index
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getRecordDataByIndex($index)
    {
        if (!$this->offsetExists($index)) {
            throw new \InvalidArgumentException("Array does not contain index '{$index}'");
        }
        if (!is_array($this->records[$index])) {
            $this->records[$index] = $this->records[$index]->toArray([], ['*'], false);
        }
        return $this->records[$index];
    }
    
    /**
     * Optimize iteration to reuse Db\Record instance and disable validation for data received from DB
     * @return $this
     */
    public function optimizeIteration()
    {
        $this->enableDbRecordInstanceReuseDuringIteration();
        $this->disableDbRecordDataValidation();
        return $this;
    }
    
    /**
     * @return $this
     */
    public function enableDbRecordInstanceReuseDuringIteration()
    {
        $this->isDbRecordInstanceReuseEnabled = true;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function disableDbRecordInstanceReuseDuringIteration()
    {
        $this->isDbRecordInstanceReuseEnabled = false;
        $this->dbRecordForIteration = null;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isDbRecordInstanceReuseDuringIterationEnabled()
    {
        return $this->isDbRecordInstanceReuseEnabled;
    }
    
    /**
     * @return $this
     */
    public function enableDbRecordDataValidation()
    {
        $this->setIsDbRecordDataValidationDisabled(false);
        return $this;
    }
    
    /**
     * @return $this
     */
    public function disableDbRecordDataValidation()
    {
        $this->setIsDbRecordDataValidationDisabled(true);
        return $this;
    }
    
    /**
     * @param bool $isDisabled
     * @return $this
     */
    protected function setIsDbRecordDataValidationDisabled(bool $isDisabled)
    {
        $this->isDbRecordDataValidationDisabled = $isDisabled;
        if ($this->dbRecordForIteration) {
            if ($this->isDbRecordDataValidationDisabled()) {
                $this->dbRecordForIteration->enableTrustModeForDbData();
            } else {
                $this->dbRecordForIteration->disableTrustModeForDbData();
            }
        }
        return $this;
    }
    
    public function isDbRecordDataValidationDisabled(): bool
    {
        return $this->isDbRecordDataValidationDisabled;
    }
    
    /**
     * @return bool|null - null: mixed
     */
    public function isRecordsFromDb(): ?bool
    {
        return $this->isFromDb;
    }
    
    protected function autodetectIfRecordIsFromDb(array $data): bool
    {
        $pkName = $this->table->getTableStructure()
            ->getPkColumnName();
        return array_key_exists($pkName, $data) && $data[$pkName] !== null;
    }
    
    /**
     * @param null|string|\Closure $closureOrColumnsListOrMethodName
     *      - null: return all fetched records as not modified arrays
     *      - other options and arguments described in RecordsArray::getDataFromEachObject()
     * @param array $argumentsForMethod
     * @param bool $enableReadOnlyMode
     * @return array[]
     * @see RecordsArray::getDataFromEachObject()
     */
    public function toArrays($closureOrColumnsListOrMethodName = null, array $argumentsForMethod = [], bool $enableReadOnlyMode = true): array
    {
        if ($closureOrColumnsListOrMethodName) {
            return $this->getDataFromEachObject($closureOrColumnsListOrMethodName, $argumentsForMethod, $enableReadOnlyMode);
        } elseif ($this->isRecordsContainObjects) {
            /** @var array|RecordInterface $data */
            foreach ($this->records as $index => $data) {
                if (!is_array($data)) {
                    $this->records[$index] = $data->toArray();
                }
            }
        }
        return $this->getRecords();
    }
    
    /**
     * @return RecordInterface[]
     */
    public function toObjects(): array
    {
        $count = $this->count();
        if ($count !== count($this->dbRecords)) {
            for ($i = 0; $i < $count; $i++) {
                $this->convertToObject($i);
            }
        }
        return $this->dbRecords;
    }
    
    /**
     * Get some specific data from each object
     * @param string|\Closure|array $closureOrColumnsListOrMethodName
     *      - string: get record data processed by ORM Record's method name. You can provide additional args via $argumentsForMethod
     *      - array: list of columns compatible with Record->toArray($columnsNames)
     *      - \Closure: function (RecordInterface $record) { return $record->toArray(); }
     *      - \Closure: function (RecordInterface $record) { return \PeskyORM\ORM\KeyValuePair::create($record->id, $record->toArray()); }
     * @param array $argumentsForMethod - pass this arguments to ORM Record's method.
     *      Not used if $argumentsForMethod is Closure.
     *      If $closureOrColumnsListOrMethodName is array - 1st argument is $closureOrColumnsListOrMethodName.
     * @param bool $enableReadOnlyMode - true: disable all processing of Record's data during Record object creations so
     *      it will work much faster on large sets of Records and allow using Record's methods but will disable
     *      Record's data modification
     * @return array
     * @throws \InvalidArgumentException
     * @see Record::toArray()
     * @see \PeskyORM\ORM\KeyValuePair::create()
     */
    public function getDataFromEachObject($closureOrColumnsListOrMethodName, array $argumentsForMethod = [], bool $enableReadOnlyMode = true): array
    {
        if ($closureOrColumnsListOrMethodName instanceof \Closure) {
            $closure = $closureOrColumnsListOrMethodName;
        } else {
            if (is_array($closureOrColumnsListOrMethodName)) {
                // columns list
                $argumentsForMethod = array_merge([$closureOrColumnsListOrMethodName], $argumentsForMethod);
                $closureOrColumnsListOrMethodName = 'toArray';
            }
            if (is_string($closureOrColumnsListOrMethodName)) {
                // Record's method and arguments
                $closure = function (RecordInterface $record) use ($closureOrColumnsListOrMethodName, $argumentsForMethod) {
                    return call_user_func_array([$record, $closureOrColumnsListOrMethodName], $argumentsForMethod);
                };
            } else {
                throw new \InvalidArgumentException('$callback argument must be a string (method name), array (columns list) or closure');
            }
        }
        $data = [];
        $backupReuse = $this->isDbRecordInstanceReuseEnabled;
        $backupValidation = $this->isDbRecordDataValidationDisabled;
        $this->enableDbRecordInstanceReuseDuringIteration();
        if ($enableReadOnlyMode) {
            $this->enableReadOnlyMode();
        } else {
            $this->disableDbRecordDataValidation();
        }
        for ($i = 0, $count = $this->count(); $i < $count; $i++) {
            /** @var Record|RecordInterface $record */
            $record = $this->offsetGet($i);
            $value = $closure($record);
            if ($value instanceof KeyValuePair) {
                $valueForKey = $value->getValue();
                if (
                    is_object($valueForKey)
                    && $this->isDbRecordInstanceReuseDuringIterationEnabled()
                    && (get_class($valueForKey) === get_class($record))
                ) {
                    // disable db record instance reuse when $valueForKey is $record.
                    // Otherwise it will cause unexpected problems
                    $this->disableDbRecordInstanceReuseDuringIteration();
                }
                $data[$value->getKey()] = $valueForKey;
            } else {
                $data[] = $value;
            }
        }
        $this->isDbRecordInstanceReuseEnabled = $backupReuse;
        $this->isDbRecordDataValidationDisabled = $backupValidation;
        return $data;
    }
    
    /**
     * Get $columnName values from all records
     * @param string $columnName
     * @param mixed $defaultValue
     * @param null|\Closure $filter - closure compatible with array_filter()
     * @return array
     */
    public function getValuesForColumn($columnName, $defaultValue = null, \Closure $filter = null): array
    {
        $records = $this->toArrays();
        $ret = [];
        foreach ($records as $data) {
            if (array_key_exists($columnName, $data)) {
                $ret[] = $data[$columnName];
            } else {
                $ret[] = $defaultValue;
            }
        }
        return $filter ? array_filter($ret, $filter) : $ret;
    }
    
    /**
     * Filter records and create new RecordsArray from remaining records
     * @param \Closure $filter - closure compatible with array_filter()
     * @param bool $resetOriginalRecordsArray
     * @return RecordsArray - new RecordsArray (not RecordsSet!)
     */
    public function filterRecords(\Closure $filter, $resetOriginalRecordsArray = false): RecordsArray
    {
        $newArray = new self($this->table, array_filter($this->toObjects(), $filter), $this->isFromDb);
        if ($resetOriginalRecordsArray) {
            $this->resetRecords();
        }
        return $newArray;
    }
    
    /**
     * @param int $index - record's index
     * @return RecordInterface|Record
     */
    protected function convertToObject($index)
    {
        if (empty($this->dbRecords[$index])) {
            $data = $this->getRecordDataByIndex($index);
            $isFromDb = $this->isRecordsFromDb();
            if ($isFromDb === null) {
                $isFromDb = $this->autodetectIfRecordIsFromDb($data);
            }
            $record = $this->getNewRecord();
            $pkColumnName = $this->table->getTableStructure()
                ->getPkColumnName();
            if ($this->isReadOnlyModeEnabled()) {
                $record->enableReadOnlyMode();
            } else {
                $record->disableReadOnlyMode();
                if ($this->isDbRecordDataValidationDisabled()) {
                    $record->enableTrustModeForDbData();
                }
            }
            if (!$isFromDb && !empty($data[$pkColumnName])) {
                // primary key value is set but $isFromDb === false. This usually means that all data except
                // primery key is not from db
                $record->updateValue($pkColumnName, $data[$pkColumnName], true);
                unset($data[$pkColumnName]);
                $record->updateValues($data, false, false);
            } else {
                $record->fromData($data, $isFromDb);
            }
            $this->dbRecords[$index] = $record;
        }
        return $this->dbRecords[$index];
    }
    
    protected function getStandaloneObject(array $data, bool $withOptimizations): RecordInterface
    {
        $record = $this->getNewRecord();
        if ($withOptimizations) {
            if ($this->isReadOnlyModeEnabled()) {
                $record->enableReadOnlyMode();
            }
            if ($this->isDbRecordDataValidationDisabled()) {
                $record->enableTrustModeForDbData();
            }
        }
        return $record->fromData($data, $this->isRecordsFromDb());
    }
    
    /**
     * Return the current element
     * @return RecordInterface|Record
     */
    public function current()
    {
        if (!$this->offsetExists($this->iteratorPosition)) {
            return null;
        }
        return $this->offsetGet($this->iteratorPosition);
    }
    
    /**
     * Move forward to next element
     */
    public function next()
    {
        $this->iteratorPosition++;
    }
    
    /**
     * Return the key of the current element
     */
    public function key()
    {
        return $this->iteratorPosition;
    }
    
    /**
     * Checks if current position is valid
     * @return boolean - true on success or false on failure.
     */
    public function valid()
    {
        return $this->offsetExists($this->iteratorPosition);
    }
    
    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        $this->iteratorPosition = 0;
    }
    
    /**
     * Get first record
     * @param string|null $columnName - null: return record; string - return value for the key from record
     * @return RecordInterface|Record|mixed
     * @throws \BadMethodCallException
     */
    public function first($columnName = null)
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There is no records');
        }
        /** @var Record|RecordInterface $record */
        $record = $this->offsetGet(0);
        return $columnName === null ? $record : $record[$columnName];
    }
    
    /**
     * Get last record
     * @param string|null $columnName - null: return record; string - return value for the key from record
     * @return RecordInterface|Record|mixed
     * @throws \BadMethodCallException
     */
    public function last($columnName = null)
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There is no records');
        }
        /** @var Record|RecordInterface $record */
        $record = $this->offsetGet(count($this->getRecords()) - 1);
        return $columnName === null ? $record : $record[$columnName];
    }
    
    /**
     * Find single record by $columnName and $value within selected records
     * @param string $columnName
     * @param mixed $value - expected value for $columnName
     * @param bool $asObject
     * @return array|RecordInterface|Record|null
     */
    public function findOne(string $columnName, $value, bool $asObject)
    {
        foreach ($this->getRecords() as $index => $record) {
            if ($record[$columnName] === $value) {
                return $asObject ? $this->getStandaloneObject($record, false) : $record;
            }
        }
        return null;
    }
    
    /**
     * Whether a record with specified index exists
     * @param mixed $index - an offset to check for.
     * @return boolean - true on success or false on failure.
     */
    public function offsetExists($index)
    {
        return array_key_exists($index, $this->getRecords());
    }
    
    /**
     * @param int $index - The offset to retrieve.
     * @return RecordInterface|Record
     */
    public function offsetGet($index)
    {
        if ($this->isDbRecordInstanceReuseDuringIterationEnabled()) {
            /** @var RecordInterface|Record $dbRecord */
            $dbRecord = $this->getDbRecordObjectForIteration();
            if ($index !== $this->currentDbRecordIndex) {
                $data = $this->getRecordDataByIndex($index);
                $isFromDb = $this->isRecordsFromDb();
                if ($isFromDb === null) {
                    $isFromDb = $this->autodetectIfRecordIsFromDb($data);
                }
                $dbRecord
                    ->reset()
                    ->fromData($data, $isFromDb, false);
                $this->currentDbRecordIndex = $index;
            }
            return $dbRecord;
        } else {
            return $this->convertToObject($index);
        }
    }
    
    /**
     * Offset to set
     * @param int $index
     * @param mixed $value
     * @throws \BadMethodCallException
     */
    public function offsetSet($index, $value)
    {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }
    
    /**
     * Offset to unset
     * @param int $index
     * @throws \BadMethodCallException
     */
    public function offsetUnset($index)
    {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }
    
    /**
     * Count elements of an object
     * @return int
     */
    public function count()
    {
        return count($this->getRecords());
    }
    
    /**
     * Count elements of an object
     * @return int
     */
    public function totalCount()
    {
        return $this->count();
    }
    
    /**
     * @return $this
     */
    public function enableReadOnlyMode()
    {
        $this->readOnlyMode = true;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function disableReadOnlyMode()
    {
        $this->readOnlyMode = false;
        $this->dbRecordForIteration = null;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isReadOnlyModeEnabled()
    {
        return $this->readOnlyMode;
    }
}
