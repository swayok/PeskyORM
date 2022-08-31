<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use Swayok\Utils\Set;

class RecordsArray implements \ArrayAccess, \Iterator, \Countable
{
    
    protected TableInterface $table;
    /**
     * @var array[]|RecordInterface[]
     */
    protected array $records = [];
    protected ?bool $isFromDb = null;
    
    protected bool $isRecordsContainObjects = false;
    protected int $iteratorPosition = 0;
    /**
     * @var RecordInterface[]
     */
    protected array $dbRecords = [];
    
    protected bool $isDbRecordInstanceReuseEnabled = false;
    protected bool $isDbRecordDataValidationDisabled = false;
    protected ?RecordInterface $dbRecordForIteration = null;
    protected int $currentDbRecordIndex = -1;
    protected bool $readOnlyMode = false;
    /**
     * @var string[] - relation names
     */
    protected array $hasManyRelationsInjected = [];
    
    /**
     * @param TableInterface $table
     * @param array|RecordInterface[] $records
     * @param bool|null $isFromDb - true: records are from db | null - autodetect
     * @param bool $disableDbRecordDataValidation
     * @throws \InvalidArgumentException
     */
    public function __construct(
        TableInterface $table,
        array $records,
        ?bool $isFromDb = null,
        bool $disableDbRecordDataValidation = false
    ) {
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
                    /** @var RecordInterface $record */
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
     * @return static
     * @throws \InvalidArgumentException
     */
    public function injectHasManyRelationData(string $relationName, array $columnsToSelect = ['*']): static
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
    
    protected function injectHasManyRelationDataIntoRecords(Relation $relation, array $columnsToSelect = ['*']): void
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
    
    protected function getDbRecordObjectForIteration(): RecordInterface
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
     */
    public function resetRecords(): static
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
     * @throws \InvalidArgumentException
     */
    protected function getRecordDataByIndex(int $index): array
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
     */
    public function optimizeIteration(): static
    {
        $this->enableDbRecordInstanceReuseDuringIteration();
        $this->disableDbRecordDataValidation();
        return $this;
    }
    
    public function enableDbRecordInstanceReuseDuringIteration(): static
    {
        $this->isDbRecordInstanceReuseEnabled = true;
        return $this;
    }
    
    public function disableDbRecordInstanceReuseDuringIteration(): static
    {
        $this->isDbRecordInstanceReuseEnabled = false;
        $this->dbRecordForIteration = null;
        return $this;
    }
    
    public function isDbRecordInstanceReuseDuringIterationEnabled(): bool
    {
        return $this->isDbRecordInstanceReuseEnabled;
    }
    
    public function enableDbRecordDataValidation(): static
    {
        $this->setIsDbRecordDataValidationDisabled(false);
        return $this;
    }
    
    public function disableDbRecordDataValidation(): static
    {
        $this->setIsDbRecordDataValidationDisabled(true);
        return $this;
    }
    
    protected function setIsDbRecordDataValidationDisabled(bool $isDisabled): static
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
     * @return bool|null - null: mixed, will be autodetected based on primary key value existence
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
     * @param string|array|\Closure|null $closureOrColumnsListOrMethodName
     *      - null: return all fetched records as not modified arrays
     *      - other options and arguments described in RecordsArray::getDataFromEachObject()
     * @param array $argumentsForMethod
     * @param bool $enableReadOnlyMode
     * @return array[]
     * @see RecordsArray::getDataFromEachObject()
     */
    public function toArrays(
        string|array|\Closure|null $closureOrColumnsListOrMethodName = null,
        array $argumentsForMethod = [],
        bool $enableReadOnlyMode = true
    ): array {
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
     * @param array|\Closure|string $closureOrColumnsListOrMethodName
     *      - string: get record data processed by ORM Record's method name. You can provide additional args via $argumentsForMethod
     *      - array: list of columns compatible with RecordInterface->toArray($columnsNames)
     *      - \Closure: function (RecordInterface $record) { return $record->toArray(); }
     *      - \Closure: function (RecordInterface $record) { return \PeskyORM\ORM\KeyValuePair::create($record->id, $record->toArray()); }
     * @param array $argumentsForMethod - pass this arguments to ORM Record's method.
     *      Not used if $argumentsForMethod is Closure.
     *      If $closureOrColumnsListOrMethodName is array - 1st argument is $closureOrColumnsListOrMethodName.
     * @param bool $enableReadOnlyMode - true: disable all processing of Record's data during Record object creations so
     *      it will work much faster on large sets of Records and allow using Record's methods but will disable
     *      Record's data modification
     * @throws \InvalidArgumentException
     * @see RecordInterface::toArray()
     * @see \PeskyORM\ORM\KeyValuePair::create()
     */
    public function getDataFromEachObject(
        array|\Closure|string $closureOrColumnsListOrMethodName,
        array $argumentsForMethod = [],
        bool $enableReadOnlyMode = true
    ): array {
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
     * @param mixed|null $defaultValue
     * @param null|\Closure $filter - \Closure compatible with array_filter()
     * @return array
     */
    public function getValuesForColumn(string $columnName, mixed $defaultValue = null, \Closure $filter = null): array
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
    public function filterRecords(\Closure $filter, bool $resetOriginalRecordsArray = false): RecordsArray
    {
        $newArray = new self($this->table, array_filter($this->toObjects(), $filter), $this->isFromDb);
        if ($resetOriginalRecordsArray) {
            $this->resetRecords();
        }
        return $newArray;
    }
    
    protected function convertToObject(int $recordIndex): RecordInterface
    {
        if (empty($this->dbRecords[$recordIndex])) {
            $data = $this->getRecordDataByIndex($recordIndex);
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
                $record->updateValues($data, false);
            } else {
                $record->fromData($data, $isFromDb);
            }
            $this->dbRecords[$recordIndex] = $record;
        }
        return $this->dbRecords[$recordIndex];
    }
    
    protected function getStandaloneObject(RecordInterface|array $data, bool $withOptimizations): RecordInterface
    {
        $dataIsRecord = $data instanceof RecordInterface;
        if ($dataIsRecord) {
            $record = clone $data;
        } else {
            $record = $this->getNewRecord();
        }
        if ($withOptimizations) {
            if ($this->isReadOnlyModeEnabled()) {
                $record->enableReadOnlyMode();
            }
            if ($this->isDbRecordDataValidationDisabled()) {
                $record->enableTrustModeForDbData();
            }
        }
        return $dataIsRecord
            ? $record
            : $record->fromData($data, $this->isRecordsFromDb());
    }
    
    /**
     * Return the current element
     */
    public function current(): ?RecordInterface
    {
        if (!$this->offsetExists($this->iteratorPosition)) {
            return null;
        }
        return $this->offsetGet($this->iteratorPosition);
    }
    
    /**
     * Move forward to next element
     */
    public function next(): void
    {
        $this->iteratorPosition++;
    }
    
    /**
     * Return the key of the current element
     */
    public function key(): int
    {
        return $this->iteratorPosition;
    }
    
    /**
     * Checks if current position is valid
     * @return boolean - true on success or false on failure.
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->iteratorPosition);
    }
    
    /**
     * Rewind the Iterator to the first element
     */
    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }
    
    /**
     * Get first record
     * @param string|null $columnName - null: return record; string - return value for the key from record
     * @return RecordInterface|mixed
     * @throws \BadMethodCallException
     */
    public function first(?string $columnName = null): mixed
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There is no records');
        }
        $record = $this->offsetGet(0);
        return $columnName === null ? $record : $record[$columnName];
    }
    
    /**
     * Get last record
     * @param string|null $columnName - null: return record; string - return value for the key from record
     * @return RecordInterface|mixed
     * @throws \BadMethodCallException
     */
    public function last(?string $columnName = null): mixed
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There is no records');
        }
        $record = $this->offsetGet(count($this->getRecords()) - 1);
        return $columnName === null ? $record : $record[$columnName];
    }
    
    /**
     * Find single record by $columnName and $expectedValue within selected records
     */
    public function findOne(string $columnName, mixed $expectedValue, bool $asObject): RecordInterface|array|null
    {
        foreach ($this->getRecords() as $record) {
            if ($record[$columnName] === $expectedValue) {
                return $asObject ? $this->getStandaloneObject($record, false) : $record;
            }
        }
        return null;
    }
    
    /**
     * Whether a record with specified index exists
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetExists(mixed $index): bool
    {
        return array_key_exists($index, $this->getRecords());
    }
    
    /**
     * Get record by its index
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetGet(mixed $index): RecordInterface
    {
        if ($this->isDbRecordInstanceReuseDuringIterationEnabled()) {
            $dbRecord = $this->getDbRecordObjectForIteration();
            if ($index !== $this->currentDbRecordIndex) {
                $data = $this->getRecordDataByIndex($index);
                $isFromDb = $this->isRecordsFromDb();
                if ($isFromDb === null) {
                    $isFromDb = $this->autodetectIfRecordIsFromDb($data);
                }
                $dbRecord
                    ->reset()
                    ->fromData($data, $isFromDb);
                $this->currentDbRecordIndex = $index;
            }
            return $dbRecord;
        } else {
            return $this->convertToObject($index);
        }
    }
    
    /**
     * Add record to index
     * @throws \BadMethodCallException
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetSet(mixed $index, mixed $value): void
    {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }
    
    /**
     * Delete record by its index
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetUnset(mixed $index): void
    {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }
    
    /**
     * Count fetched records
     */
    public function count(): int
    {
        return count($this->getRecords());
    }
    
    /**
     * Count total records in DB
     */
    public function totalCount(): int
    {
        return $this->count();
    }
    
    public function enableReadOnlyMode(): static
    {
        $this->readOnlyMode = true;
        return $this;
    }
    
    public function disableReadOnlyMode(): static
    {
        $this->readOnlyMode = false;
        $this->dbRecordForIteration = null;
        return $this;
    }
    
    public function isReadOnlyModeEnabled(): bool
    {
        return $this->readOnlyMode;
    }
}
