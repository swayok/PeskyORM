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
    protected $recordsLimitPerPageForOptimizedIteration = 50;
    /**
     * Automatically activates optimized iteration when records count is expected to be higher then this value
     * @var int
     */
    protected $minRecordsCountForOptimizedIteration = 200;
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
    protected $maxRecordsToStoreDuringOptimizedIteration = 1000;
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
        return new RecordsSet($dbSelect->getTable(), $dbSelect, true);
    }

    /**
     * @param TableInterface $table
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    public function __construct(TableInterface $table, OrmSelect $dbSelect) {
        parent::__construct($table, [], true);
        $this->records = null;
        $this->select = clone $dbSelect;
        $this->optimizeIterationOverLargeAmountOfRecords = (
            $this->select->getLimit() === 0
            || $this->select->getLimit() > $this->minRecordsCountForOptimizedIteration
        );
        if ($this->optimizeIterationOverLargeAmountOfRecords) {
            $this->selectForOptimizedIteration = clone $this->select;
            $this->selectForOptimizedIteration->limit($this->recordsLimitPerPageForOptimizedIteration);
            $pkCol = $this->selectForOptimizedIteration->getTableStructure()->getPkColumnName();
            if (!$this->selectForOptimizedIteration->hasOrderingForColumn($pkCol)) {
                $this->selectForOptimizedIteration->orderBy($pkCol, true);
            }
        }
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
        return $this->optimizeIterationOverLargeAmountOfRecords
            ? $this->selectForOptimizedIteration->fetchMany()
            : $this->select->fetchMany();
    }

    /**
     * @return array[]
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function toArrays() {
        if (
            $this->optimizeIterationOverLargeAmountOfRecords
            && (
                $this->records === null
                || $this->count() !== count($this->records))
            ) {
            $this->records = $this->select->fetchMany();
        } else if ($this->records === null) {
            $this->records = $this->getRecords();
        }
        return $this->records;
    }

    /**
     * Whether a record with specified index exists
     * @param mixed $index - an offset to check for.
     * @return boolean - true on success or false on failure.
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function offsetExists($index) {
        return is_int($index) && $index >= 0 && $index < $this->count();
    }

    /**
     * @param $index
     * @return mixed
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
            /*if (
                $this->optimizeIterationOverLargeAmountOfRecords
                && $this->count() < $this->minRecordsCountForOptimizedIteration
            ) {
                $this->optimizeIterationOverLargeAmountOfRecords = false;
            }*/
            if ($this->optimizeIterationOverLargeAmountOfRecords) {
                $page = (int)floor($index / $this->recordsLimitPerPageForOptimizedIteration);
                $this->localOffsetForOptimizedIteration = $page * $this->recordsLimitPerPageForOptimizedIteration;
                $this->selectForOptimizedIteration->page(
                    $this->recordsLimitPerPageForOptimizedIteration,
                    $this->select->getOffset() + $this->localOffsetForOptimizedIteration
                );
                $newRecords = $this->getRecords();
                if (count($this->records) + $this->recordsLimitPerPageForOptimizedIteration >= $this->maxRecordsToStoreDuringOptimizedIteration) {
                    $this->records = [];
                }
                array_splice($this->records, $this->localOffsetForOptimizedIteration, $this->recordsLimitPerPageForOptimizedIteration, $newRecords);
            } else {
                $this->records = $this->getRecords();
            }
        }
        return $this->records[$index];
    }

    /**
     * Count DB records within LIMIT and OFFSET options
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function count() {
        if ($this->recordsCount === null) {
            $this->recordsCount = $this->select->fetchCount(true, false);
        }
        return $this->recordsCount;
    }

    /**
     * Count DB records ignoring LIMIT and OFFSET options
     * @return int
     */
    public function countTotal() {
        if ($this->recordsCountTotal === null) {
            if ($this->select->getLimit() === 0 && $this->select->getOffset() === 0) {
                $this->recordsCountTotal = $this->count();
            } else {
                $this->recordsCountTotal = $this->select->fetchCount(true, true);
            }
        }
        return $this->recordsCountTotal;
    }
}