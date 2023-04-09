<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;

class RecordsSet extends RecordsArray
{
    
    /**
     * @var OrmSelect
     */
    protected $select;
    /**
     * Count of records affected by LIMIT and OFFSET
     * null - not counted
     * @var null|int
     */
    protected $recordsCount = null;
    /**
     * Count of records not affected by LIMIT and OFFSET
     * null - not counted
     * @var null|int
     */
    protected $recordsCountTotal = null;
    /**
     * @var array[] - keys: relation names; values: arrays ['relation' => Relation; 'columns' => array]
     */
    protected $hasManyRelationsToInject = [];
    /**
     * @var bool
     */
    protected $ignoreLeftJoinsForCount = false;
    
    /**
     * @param TableInterface $table
     * @param array $records
     * @param boolean|null $isFromDb - null: autodetect by primary key value existence
     * @param bool $trustDataReceivedFromDb
     * @return RecordsArray
     */
    static public function createFromArray(TableInterface $table, array $records, ?bool $isFromDb = null, bool $trustDataReceivedFromDb = false)
    {
        return new RecordsArray($table, $records, $isFromDb, $trustDataReceivedFromDb);
    }
    
    /**
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @return RecordsSet
     */
    static public function createFromOrmSelect(OrmSelect $dbSelect)
    {
        return new self($dbSelect);
    }
    
    /**
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @param bool $disableDbRecordDataValidation
     */
    public function __construct(OrmSelect $dbSelect, bool $disableDbRecordDataValidation = false)
    {
        parent::__construct($dbSelect->getTable(), [], true, $disableDbRecordDataValidation);
        $this->setOrmSelect($dbSelect);
    }
    
    public function setRecordClass(?string $class)
    {
        $this->getOrmSelect()
            ->setRecordClass($class);
        $this->dbRecords = [];
        $this->dbRecordForIteration = null;
        return $this;
    }
    
    protected function getNewRecord(): RecordInterface
    {
        return $this->getOrmSelect()
            ->getNewRecord();
    }
    
    /**
     * @param Relation $relation
     * @param array $columnsToSelect
     * @param array $orderBy
     */
    protected function injectHasManyRelationDataIntoRecords(
        Relation $relation,
        array $columnsToSelect = ['*'],
        array $orderBy = []
    ) {
        $this->hasManyRelationsToInject[$relation->getName()] = [
            'relation' => $relation,
            'columns' => $columnsToSelect,
        ];
        if (is_array($this->records)) {
            parent::injectHasManyRelationDataIntoRecords($relation, $columnsToSelect, $orderBy);
        }
    }
    
    
    /**
     * For internal use only!
     * @param OrmSelect $dbSelect
     * @return $this
     */
    protected function setOrmSelect(OrmSelect $dbSelect)
    {
        $this->resetRecords();
        $this->select = $dbSelect;
        return $this;
    }
    
    /**
     * @return OrmSelect
     */
    public function getOrmSelect()
    {
        return $this->select;
    }
    
    /**
     * Append conditions
     * @param array $conditions
     * @param bool $returnNewRecordSet
     *      - true: will return new RecordSet with new OrmSelect instead of changing current RecordSet
     *      - false: append conditions to current RecordSet; this will reset current state of RecordSet (count, records)
     * @param bool $resetOriginalRecordSet - true: used only when $returnNewRecordSet is true and will reset
     *      Current RecordSet (count, records)
     * @return RecordsSet
     */
    public function appendConditions(array $conditions, bool $returnNewRecordSet, bool $resetOriginalRecordSet = false)
    {
        if ($returnNewRecordSet) {
            $newSelect = clone $this->select;
            $newSelect->where($conditions, true);
            $newSet = static::createFromOrmSelect($newSelect);
            if ($resetOriginalRecordSet) {
                $this->resetRecords();
            }
            return $newSet;
        } else {
            // update OrmSelect and reset RecordSet
            $this->setOrmSelect($this->select->where($conditions, true));
            return $this;
        }
    }
    
    /**
     * Replace records ordering
     * Note: deletes already selected records and selects new
     * @param array|string|DbExpr $column
     * @param bool $orderAscending
     * @return $this
     */
    public function replaceOrdering($column, bool $orderAscending = true)
    {
        if (is_array($column)) {
            $this->select->removeOrdering();
            foreach ($column as $colName => $direction) {
                if (is_int($colName)) {
                    // index => column or DbExpr
                    $this->select->orderBy($direction, 'asc', true);
                } else {
                    // column or DbExpr => direction
                    $this->select->orderBy($colName, $direction, true);
                }
            }
        } else {
            $this->select->orderBy($column, $orderAscending, false);
        }
        $this->resetRecords();
        return $this;
    }
    
    /**
     * Reset already fetched data
     * @return $this
     */
    public function resetRecords()
    {
        parent::resetRecords();
        $this->invalidateCount();
        return $this;
    }
    
    /**
     * @return $this
     */
    public function nextPage()
    {
        $this->rewind();
        $this->setRecords($this->select->fetchNextPage());
        return $this;
    }
    
    /**
     * @return $this
     */
    public function prevPage()
    {
        $this->rewind();
        $this->setRecords($this->select->fetchPrevPage());
        return $this;
    }
    
    /**
     * @param int $newBaseOffset
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function changeBaseOffset(int $newBaseOffset)
    {
        if ($newBaseOffset < 0) {
            throw new \InvalidArgumentException('Negative offset is not allowed');
        }
        $this->select->offset($newBaseOffset);
        $this->recordsCount = null;
        $this->records = null;
        return $this;
    }
    
    /**
     * @param bool $reload
     * @return array
     */
    protected function getRecords(bool $reload = false): array
    {
        $this->fetchRecords($reload);
        return $this->records;
    }
    
    /**
     * @return $this
     */
    public function fetchRecords(bool $reload = false)
    {
        if ($reload || $this->recordsCount === null) {
            $this->setRecords($this->select->fetchMany());
        }
        return $this;
    }
    
    public function areRecordsFetchedFromDb(): bool
    {
        return $this->recordsCount !== null;
    }
    
    /**
     * @param array $records
     * @return $this
     */
    protected function setRecords(array $records)
    {
        $this->records = $records;
        $this->recordsCount = count($this->records);
        $this->hasManyRelationsInjected = [];
        foreach ($this->hasManyRelationsToInject as $injectionConfig) {
            parent::injectHasManyRelationDataIntoRecords($injectionConfig['relation'], $injectionConfig['columns']);
        }
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function toArrays($closureOrColumnsListOrMethodName = null, array $argumentsForMethod = [], bool $enableReadOnlyMode = true): array
    {
        if ($closureOrColumnsListOrMethodName) {
            return $this->getDataFromEachObject($closureOrColumnsListOrMethodName, $argumentsForMethod, $enableReadOnlyMode);
        } else {
            return $this->getRecords();
        }
    }
    
    /**
     * Whether a record with specified index exists
     * @param int $index - an offset to check for.
     * @return boolean - true on success or false on failure.
     */
    public function offsetExists($index)
    {
        return is_int($index) && $index >= 0 && $index < $this->count();
    }
    
    /**
     * @param int $index
     * @return array|null
     * @throws \InvalidArgumentException
     */
    protected function getRecordDataByIndex($index)
    {
        if (!$this->offsetExists($index)) {
            throw new \InvalidArgumentException("Array does not contain index '{$index}'");
        }
        return $this->getRecords()[(int)$index];
    }
    
    /**
     * Count amount of DB records to be fetched
     * Note: not same as totalCount() - that one does not take in account LIMIT and OFFSET
     * @return int
     */
    public function count()
    {
        if ($this->recordsCount === null) {
            if ($this->select->getOffset() === 0 && $this->select->getLimit() === 0) {
                $this->recordsCount = $this->totalCount();
            } else {
                $recordsCountAfterOffset = $this->totalCount() - $this->select->getOffset();
                if ($this->select->getLimit() === 0) {
                    $this->recordsCount = $recordsCountAfterOffset;
                } else {
                    $this->recordsCount = min($recordsCountAfterOffset, $this->select->getLimit());
                }
            }
            if ($this->recordsCount > 0) {
                $this->getRecords(true);
            }
        }
        return $this->recordsCount;
    }
    
    /**
     * This can speedup count query for simple cases when joins are not nested
     * @param bool $ignoreLeftJoinsForCount
     * @return $this
     */
    public function setIgnoreLeftJoinsForCount(bool $ignoreLeftJoinsForCount)
    {
        $this->ignoreLeftJoinsForCount = $ignoreLeftJoinsForCount;
        return $this;
    }
    
    /**
     * Count DB records ignoring LIMIT and OFFSET options
     * @param bool|null $ignoreLeftJoins - null: use $this->ignoreLeftJoinsForCount | bool: change $this->ignoreLeftJoinsForCount
     * @return int
     */
    public function totalCount(?bool $ignoreLeftJoins = null)
    {
        if ($ignoreLeftJoins !== null && $this->ignoreLeftJoinsForCount !== $ignoreLeftJoins) {
            // $this->ignoreLeftJoinsForCount differs from previous one - reset total records count
            $this->recordsCountTotal = null;
            $this->ignoreLeftJoinsForCount = $ignoreLeftJoins;
        }
        if ($this->recordsCountTotal === null) {
            $this->recordsCountTotal = $this->select->fetchCount($this->ignoreLeftJoinsForCount);
        }
        return $this->recordsCountTotal;
    }
    
    /**
     * Invalidate counts
     * @return $this
     */
    protected function invalidateCount()
    {
        $this->recordsCount = null;
        $this->recordsCountTotal = null;
        return $this;
    }
}
