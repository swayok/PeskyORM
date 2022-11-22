<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use Closure;
use PeskyORM\DbExpr;
use PeskyORM\Select\OrmSelect;

class RecordsSet extends RecordsArray
{
    
    protected OrmSelect $select;
    /**
     * Count of records affected by LIMIT and OFFSET
     * null - not counted
     */
    protected ?int $recordsCount = null;
    /**
     * Count of records not affected by LIMIT and OFFSET
     * null - not counted
     */
    protected ?int $recordsCountTotal = null;
    /**
     * @var array[] - keys: relation names; values: arrays ['relation' => Relation; 'columns' => array]
     */
    protected array $hasManyRelationsToInject = [];
    
    protected bool $ignoreLeftJoinsForCount = false;
    
    /**
     * @param TableInterface $table
     * @param array $records
     * @param boolean|null $isFromDb - null: autodetect by primary key value existence
     * @param bool $trustDataReceivedFromDb
     * @return RecordsArray
     */
    public static function createFromArray(
        TableInterface $table,
        array $records,
        ?bool $isFromDb = null,
        bool $trustDataReceivedFromDb = false
    ): RecordsArray {
        return new RecordsArray($table, $records, $isFromDb, $trustDataReceivedFromDb);
    }
    
    /**
     * @param OrmSelect $dbSelect - it will be cloned to avoid possible problems when original object
     *      is changed outside RecordsSet + to allow optimised iteration via pagination
     * @return RecordsSet
     */
    public static function createFromOrmSelect(OrmSelect $dbSelect): RecordsSet
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
    
    public function setRecordClass(?string $class): static
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
     */
    protected function injectHasManyRelationDataIntoRecords(Relation $relation, array $columnsToSelect = ['*']): void
    {
        $this->hasManyRelationsToInject[$relation->getName()] = [
            'relation' => $relation,
            'columns' => $columnsToSelect,
        ];
        if ($this->records) {
            parent::injectHasManyRelationDataIntoRecords($relation, $columnsToSelect);
        }
    }
    
    /**
     * For internal use only!
     */
    protected function setOrmSelect(OrmSelect $dbSelect): static
    {
        $this->resetRecords();
        $this->select = $dbSelect;
        return $this;
    }
    
    public function getOrmSelect(): OrmSelect
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
     * @return static
     */
    public function appendConditions(array $conditions, bool $returnNewRecordSet, bool $resetOriginalRecordSet = false): static
    {
        if ($returnNewRecordSet) {
            $newSelect = clone $this->select;
            $newSelect->where($conditions, true);
            $newSet = static::createFromOrmSelect($newSelect);
            if ($resetOriginalRecordSet) {
                $this->resetRecords();
            }
            return $newSet;
        }

        // update OrmSelect and reset RecordSet
        $this->setOrmSelect($this->select->where($conditions, true));
        return $this;
    }
    
    /**
     * Replace records ordering
     * Note: deletes already selected records and selects new
     */
    public function replaceOrdering(DbExpr|array|string $column, bool $orderAscending = true): static
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
     */
    public function resetRecords(): static
    {
        parent::resetRecords();
        $this->invalidateCount();
        return $this;
    }
    
    public function nextPage(): static
    {
        $this->rewind();
        $this->setRecords($this->select->fetchNextPage());
        return $this;
    }
    
    public function prevPage(): static
    {
        $this->rewind();
        $this->setRecords($this->select->fetchPrevPage());
        return $this;
    }
    
    public function setOffset(int $newOffset): static
    {
        if ($newOffset < 0) {
            throw new \InvalidArgumentException('Negative offset is not allowed');
        }
        $this->select->offset($newOffset);
        $this->recordsCount = null;
        $this->records = [];
        return $this;
    }
    
    protected function getRecords(bool $reload = false): array
    {
        $this->fetchRecords($reload);
        return $this->records;
    }
    
    public function fetchRecords(bool $reload = false): static
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
    
    protected function setRecords(array $records): static
    {
        $this->records = $records;
        $this->recordsCount = count($this->records);
        $this->hasManyRelationsInjected = [];
        foreach ($this->hasManyRelationsToInject as $injectionConfig) {
            parent::injectHasManyRelationDataIntoRecords($injectionConfig['relation'], $injectionConfig['columns']);
        }
        return $this;
    }
    
    public function toArrays(
        string|array|Closure|null $closureOrColumnsListOrMethodName = null,
        array $argumentsForMethod = [],
        bool $enableReadOnlyMode = true
    ): array {
        if ($closureOrColumnsListOrMethodName) {
            return $this->getDataFromEachObject($closureOrColumnsListOrMethodName, $argumentsForMethod, $enableReadOnlyMode);
        }

        return $this->getRecords();
    }
    
    public function offsetExists(mixed $index): bool
    {
        return is_int($index) && $index >= 0 && $index < $this->count();
    }
    
    protected function getRecordDataByIndex(int $index): array
    {
        if (!$this->offsetExists($index)) {
            throw new \InvalidArgumentException("Array does not contain index '{$index}'");
        }
        return $this->getRecords()[$index];
    }
    
    /**
     * Count amount of DB records to be fetched
     * Note: not same as totalCount() - that one does not take in account LIMIT and OFFSET
     */
    public function count(): int
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
     */
    public function setIgnoreLeftJoinsForCount(bool $ignoreLeftJoinsForCount): static
    {
        $this->ignoreLeftJoinsForCount = $ignoreLeftJoinsForCount;
        return $this;
    }
    
    /**
     * Count DB records ignoring LIMIT and OFFSET options
     * @param bool|null $ignoreLeftJoins - null: use $this->ignoreLeftJoinsForCount | bool: change $this->ignoreLeftJoinsForCount
     */
    public function totalCount(?bool $ignoreLeftJoins = null): int
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
     */
    protected function invalidateCount(): static
    {
        $this->recordsCount = null;
        $this->recordsCountTotal = null;
        return $this;
    }
}
