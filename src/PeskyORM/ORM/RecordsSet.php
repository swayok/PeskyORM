<?php

namespace PeskyORM\ORM;

class RecordsSet extends RecordsArray {

    /**
     * @var OrmSelect
     */
    protected $select;
    /**
     * @var OrmSelect
     */
    protected $selectForOptimizedIteration;
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
     * Used for optimized iteration that uses pagination to fetch small packs of records instead of fetching all at once
     * @var int
     */
    protected $recordsLimitPerPageForOptimizedIteration = 1000;
    /**
     * Automatically activates optimized iteration when records count is expected to be higher then this value
     * @var int
     */
    protected $minRecordsCountForOptimizedIteration = 2000;
    /**
     * Use pagination to fetch small packs of records instead of fetching all at once?
     * Automatically enabed when there is no limit/offset in Select or when
     * limit > $this->recordsCountPerPageForOptimizedIteration * 4
     * @var bool
     */
    protected $optimizeIterationOverLargeAmountOfRecords = false;
    /**
     * Limits size of $this->records when $this->optimizeIterationOverLargeAmountOfRecords === true to avoid memory
     * problems
     * @var int
     */
    protected $maxRecordsToStoreDuringOptimizedIteration = 10000;
    /**
     * @var int
     */
    protected $localOffsetForOptimizedIteration = 0;

    /**
     * @param TableInterface $table
     * @param array $records
     * @param boolean|null $isFromDb - null: autodetect by primary key value existence
     * @return RecordsArray
     * @throws \InvalidArgumentException
     */
    static public function createFromArray(TableInterface $table, array $records, $isFromDb = null) {
        return new RecordsArray($table, $records, $isFromDb === null ? null : (bool)$isFromDb);
    }

    /**
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @return RecordsSet
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function createFromOrmSelect(OrmSelect $dbSelect) {
        return new self($dbSelect);
    }

    /**
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    public function __construct(OrmSelect $dbSelect) {
        parent::__construct($dbSelect->getTable(), [], true);
        $this->setOrmSelect($dbSelect);
    }

    /**
     * For internal use only!
     * @param OrmSelect $dbSelect
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function setOrmSelect(OrmSelect $dbSelect) {
        $this->resetRecords();
        $this->select = $dbSelect;
        $this->selectForOptimizedIteration = null;
        $this->optimizeIterationOverLargeAmountOfRecords = (
            $this->select->getLimit() === 0
            || $this->select->getLimit() > $this->minRecordsCountForOptimizedIteration
        );
        if ($this->optimizeIterationOverLargeAmountOfRecords) {
            $this->setOptimizeIterationOverLargeAmountOfRecords(true);
        }
        return $this;
    }

    /**
     * @param bool $enable
     * @param null|int $recordsPerRequest - how many records should be fetched per DB query (Default: 1000)
     * @param null|int $maxRecordsToHold - max amount of records to be stored in $this->records (Default: 10000)
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setOptimizeIterationOverLargeAmountOfRecords($enable, $recordsPerRequest = null, $maxRecordsToHold = null) {
        $this->optimizeIterationOverLargeAmountOfRecords = (bool) $enable;
        if ($this->optimizeIterationOverLargeAmountOfRecords) {
            if ((int)$recordsPerRequest > 0) {
                $this->recordsLimitPerPageForOptimizedIteration = (int)$recordsPerRequest;
            }
            if ((int)$maxRecordsToHold > 0) {
                $this->maxRecordsToStoreDuringOptimizedIteration = (int)$maxRecordsToHold;
            }
            $this->selectForOptimizedIteration = clone $this->select;
            $this->selectForOptimizedIteration->limit($this->recordsLimitPerPageForOptimizedIteration);
            $pkCol = $this->selectForOptimizedIteration->getTableStructure()->getPkColumnName();
            if (!$this->selectForOptimizedIteration->hasOrderingForColumn($pkCol)) {
                $this->selectForOptimizedIteration->orderBy($pkCol, true);
            }
        } else {
            $this->selectForOptimizedIteration = null;
        }
        return $this;
    }

    /**
     * @return OrmSelect
     */
    public function getOrmSelect() {
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
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function appendConditions(array $conditions, $returnNewRecordSet, $resetOriginalRecordSet = false) {
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
     * @param string $column
     * @param bool $orderAscending
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function replaceOrdering($column, $orderAscending = true) {
        $this->select->orderBy($column, $orderAscending, false);
        $this->selectForOptimizedIteration->orderBy($column, $orderAscending, false);
        $this->resetRecords();
        return $this;
    }

    /**
     * Reset already fetched data
     * @return $this
     */
    public function resetRecords() {
        parent::resetRecords();
        $this->invalidateCount();
        return $this;
    }

    /**
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function nextPage() {
        $this->rewind();
        if ($this->optimizeIterationOverLargeAmountOfRecords) {
            if ($this->select->getLimit() === 0) {
                throw new \BadMethodCallException('It is impossible to use pagination when there is no limit');
            }
            $this->changeBaseOffset($this->select->getOffset() + $this->select->getLimit());
        } else {
            $this->records = $this->select->fetchNextPage();
        }
        return $this;
    }

    /**
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function prevPage() {
        $this->rewind();
        if ($this->optimizeIterationOverLargeAmountOfRecords) {
            $this->changeBaseOffset($this->select->getOffset() - $this->select->getLimit());
        } else {
            $this->records = $this->select->fetchPrevPage();
        }
        return $this;
    }

    /**
     * @param int $newBaseOffset
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function changeBaseOffset($newBaseOffset) {
        if ($newBaseOffset < 0) {
            throw new \InvalidArgumentException('Negative offset is not allowed');
        }
        $this->select->offset($newBaseOffset);
        $this->selectForOptimizedIteration->offset($this->select->getOffset());
        $this->localOffsetForOptimizedIteration = 0;
        $this->recordsCount = null;
        $this->records = null;
        return $this;
    }

    /**
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function getRecords() {
        return $this->select->fetchMany();
    }

    /**
     * @return array[]
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function toArrays() {
        if ($this->records === null || $this->count() !== count($this->records)) {
            $this->records = $this->getRecords();
        }
        return $this->records;
    }

    /**
     * Reads all matching records from DB.
     * This prevents lazy loading
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function fetch() {
        $this->getRecords();
        return $this;
    }

    /**
     * Whether a record with specified index exists
     * @param int $index - an offset to check for.
     * @return boolean - true on success or false on failure.
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function offsetExists($index) {
        return is_int($index) && $index >= 0 && $index < $this->count();
    }

    /**
     * @param int $index
     * @return array|null
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function getRecordDataByIndex($index) {
        if (!$this->offsetExists($index)) {
            throw new \InvalidArgumentException("Array does not contain index '{$index}'");
        }
        $index = (int)$index;
        if (!is_array($this->records)) {
            $this->records = [];
        }
        if (!array_key_exists($index, $this->records)) {
            if ($this->optimizeIterationOverLargeAmountOfRecords) {
                $this->invalidateCount();
                $page = (int)floor($index / $this->recordsLimitPerPageForOptimizedIteration);
                $this->localOffsetForOptimizedIteration = $page * $this->recordsLimitPerPageForOptimizedIteration;
                $this->selectForOptimizedIteration->page(
                    $this->recordsLimitPerPageForOptimizedIteration,
                    $this->select->getOffset() + $this->localOffsetForOptimizedIteration
                );
                $newRecords = $this->selectForOptimizedIteration->fetchMany();
                if (count($newRecords) != $this->selectForOptimizedIteration->getLimit()) {
                    $this->invalidateCount();
                    $this->count();
                }
                if (count($this->records) + $this->recordsLimitPerPageForOptimizedIteration >= $this->maxRecordsToStoreDuringOptimizedIteration) {
                    $this->records = [];
                }
                array_splice($this->records, $this->localOffsetForOptimizedIteration, $this->selectForOptimizedIteration->getLimit(), $newRecords);
            } else {
                $this->records = $this->getRecords();
            }
        } else if (
            $this->optimizeIterationOverLargeAmountOfRecords
            && !isset($this->records[$index + 1])
        ) {
            // recount records when optimized iteration is enabled and next record is not loaded
            $this->invalidateCount();
        }
        return $this->records[$index];
    }

    /**
     * Count amount of DB records to be fetched
     * Note: not same as totalCount() - that one does not take in account LIMIT and OFFSET
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function count() {
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
        }
        return $this->recordsCount;
    }

    /**
     * Count DB records ignoring LIMIT and OFFSET options
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function totalCount() {
        if ($this->recordsCountTotal === null) {
            $this->recordsCountTotal = $this->select->fetchCount(true);
        }
        return $this->recordsCountTotal;
    }

    /**
     * Invalidate counts
     * @return $this
     */
    protected function invalidateCount() {
        $this->recordsCount = null;
        $this->recordsCountTotal = null;
        return $this;
    }
}