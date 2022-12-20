<?php

declare(strict_types=1);

namespace PeskyORM\ORM\RecordsCollection;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;

class RecordsArray implements RecordsCollectionInterface
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
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function injectHasManyRelationData(
        string $relationName,
        array $columnsToSelect = ['*'],
        array $additionalConditionsAndOptions = []
    ): static {
        $relation = $this->table->getTableStructure()->getRelation($relationName);
        if ($relation->getType() !== $relation::HAS_MANY) {
            $type = mb_strtoupper($relation->getType());
            throw new \InvalidArgumentException(
                "Relation '{$relationName}' is expected to be HAS_MANY  but it is {$type}."
            );
        }
        if (!in_array($relationName, $this->hasManyRelationsInjected, true)) {
            $this->injectHasManyRelationDataIntoRecords(
                $relation,
                $columnsToSelect,
                $additionalConditionsAndOptions
            );
        }
        return $this;
    }

    protected function injectHasManyRelationDataIntoRecords(
        RelationInterface $relation,
        array $columnsToSelect = ['*'],
        array $additionalConditionsAndOptions = []
    ): void {
        $relationName = $relation->getName();
        $localColumnName = $relation->getLocalColumnName();
        $ids = $this->getValuesForColumn($localColumnName, null, function ($value) {
            return !empty($value);
        });
        if (count($ids)) {
            $foreignTable = $relation->getForeignTable();
            $foreignColumnName = $relation->getForeignColumnName();
            unset($additionalConditionsAndOptions[$foreignColumnName]);
            $relatedRecords = $foreignTable::select(
                $columnsToSelect,
                array_merge(
                    [$foreignColumnName => $ids],
                    $additionalConditionsAndOptions,
                )
            );
            $relatedRecordsGrouped = [];
            foreach ($relatedRecords->toArrays() as $relatedRecord) {
                $relatedRecordsGrouped[$relatedRecord['parent_id']][] = $relatedRecord;
            }
            foreach ($this->getRecords() as $index => $record) {
                $relatedRecords = [];
                $localColumnValue = $record[$localColumnName] ?? null;
                if (
                    $localColumnValue
                    && isset($relatedRecordsGrouped[$localColumnValue])
                    && is_array($relatedRecordsGrouped[$localColumnValue])
                ) {
                    $relatedRecords = $relatedRecordsGrouped[$localColumnValue];
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

    public function isDbQueryAlreadyExecuted(): bool
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

    protected function isDbRecordInstanceReuseDuringIterationEnabled(): bool
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

    protected function isDbRecordDataValidationDisabled(): bool
    {
        return $this->isDbRecordDataValidationDisabled;
    }

    /**
     * null: mixed, will be autodetected based on primary key value existence
     */
    protected function isRecordsFromDb(): ?bool
    {
        return $this->isFromDb;
    }

    protected function autodetectIfRecordIsFromDb(array $data): bool
    {
        $pkName = $this->table->getTableStructure()
            ->getPkColumnName();
        return array_key_exists($pkName, $data) && $data[$pkName] !== null;
    }

    public function toArrays(
        array|\Closure|null $closureOrColumnsList = null,
        bool $enableReadOnlyMode = true
    ): array {
        if ($closureOrColumnsList) {
            return $this->getDataFromEachObject(
                $closureOrColumnsList,
                $enableReadOnlyMode
            );
        }

        if ($this->isRecordsContainObjects) {
            /** @var array|RecordInterface $data */
            foreach ($this->records as $index => $data) {
                if (!is_array($data)) {
                    $this->records[$index] = $data->toArray();
                }
            }
        }
        return $this->getRecords();
    }

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

    public function getDataFromEachObject(
        array|\Closure $closureOrColumnsList,
        bool $enableReadOnlyMode = true
    ): array {
        if (is_array($closureOrColumnsList)) {
            // columns list
            $closure = static function (
                RecordInterface $record
            ) use ($closureOrColumnsList) {
                return $record->toArray($closureOrColumnsList);
            };
        } else {
            $closure = $closureOrColumnsList;
        }
        $data = [];
        $backupReuse = $this->isDbRecordInstanceReuseDuringIterationEnabled();
        $backupValidation = $this->isDbRecordDataValidationDisabled();
        $backupReadonly = $this->isReadOnlyModeEnabled();
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
                    // Otherwise, it will cause unexpected problems
                    $this->disableDbRecordInstanceReuseDuringIteration();
                }
                $data[$value->getKey()] = $valueForKey;
            } else {
                $data[] = $value;
            }
        }
        $backupReuse
            ? $this->enableDbRecordInstanceReuseDuringIteration()
            : $this->disableDbRecordInstanceReuseDuringIteration();
        $backupValidation
            ? $this->enableDbRecordDataValidation()
            : $this->disableDbRecordDataValidation();
        $backupReadonly
            ? $this->enableReadOnlyMode()
            : $this->disableReadOnlyMode();
        return $data;
    }

    public function getValuesForColumn(
        string $columnName,
        mixed $defaultValue = null,
        \Closure $filter = null
    ): array {
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

    public function filterRecords(
        \Closure $filter,
        bool $resetOriginalRecordsArray = false
    ): RecordsArray {
        $newArray = new self(
            $this->table,
            array_filter($this->toObjects(), $filter),
            $this->isFromDb
        );
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
     * {@inheritDoc}
     * @throws \BadMethodCallException
     */
    public function first(): RecordInterface
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There are no records');
        }
        return $this->offsetGet(0);
    }

    /**
     * {@inheritDoc}
     * @throws \BadMethodCallException
     */
    public function last(): RecordInterface
    {
        if ($this->count() === 0) {
            throw new \BadMethodCallException('There are no records');
        }
        return $this->offsetGet(count($this->getRecords()) - 1);
    }

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
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->getRecords());
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
        }

        return $this->convertToObject($index);
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

    public function count(): int
    {
        return count($this->getRecords());
    }

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

    protected function isReadOnlyModeEnabled(): bool
    {
        return $this->readOnlyMode;
    }
}
