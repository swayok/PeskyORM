<?php

namespace PeskyORM\ORM;

class RecordsSet extends RecordsArray {

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
        $this->records = $this->select->fetchNextPage();
        $this->recordsCount = count($this->records);
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
        $this->records = $this->select->fetchPrevPage();
        $this->recordsCount = count($this->records);
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
        $this->recordsCount = null;
        $this->records = null;
        return $this;
    }

    /**
     * @param bool $reload
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function getRecords($reload = false) {
        if ($reload || $this->recordsCount === null) {
            $this->records = $this->select->fetchMany();
            $this->recordsCount = count($this->records);
        }
        return $this->records;
    }

    /**
     * @return array[]
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function toArrays() {
        return $this->getRecords();
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
        return $this->getRecords()[(int)$index];
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
            if ($this->recordsCount > 0) {
                $this->getRecords(true);
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