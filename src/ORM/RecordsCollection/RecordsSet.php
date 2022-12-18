<?php

declare(strict_types=1);

namespace PeskyORM\ORM\RecordsCollection;

use Closure;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;

class RecordsSet extends RecordsArray implements SelectedRecordsCollectionInterface
{
    protected SelectQueryBuilderInterface $select;
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
     * $dbSelect will be cloned to avoid possible problems when original
     * SelectQueryBuilderInterface instance is changed outside RecordsSet.
     * It will also allow optimised iteration using pagination.
     */
    public function __construct(
        TableInterface $table,
        SelectQueryBuilderInterface $dbSelect,
        bool $disableDbRecordDataValidation = false
    ) {
        parent::__construct(
            $table,
            [],
            true,
            $disableDbRecordDataValidation
        );
        $this->setSelect($dbSelect);
    }

    protected function injectHasManyRelationDataIntoRecords(
        RelationInterface $relation,
        array $columnsToSelect = ['*']
    ): void {
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
    protected function setSelect(SelectQueryBuilderInterface $dbSelect): static
    {
        $this->resetRecords();
        $this->select = clone $dbSelect;
        return $this;
    }

    public function getSelect(): SelectQueryBuilderInterface
    {
        return $this->select;
    }

    /**
     * Append more conditions to OrmSelect.
     * @param array $conditions
     * @param bool $returnNewRecordSet
     *      - true: will return new RecordSet with new OrmSelect
     *          instead of changing current RecordSet
     *      - false: append conditions to current RecordSet;
     *          this will reset current state of RecordSet (count, records)
     * @param bool $resetOriginalRecordSet
     *      - true: used only when $returnNewRecordSet is true and
     *          will reset current RecordSet (count, records)
     * @return static
     */
    public function appendConditions(
        array $conditions,
        bool $returnNewRecordSet,
        bool $resetOriginalRecordSet = false
    ): static {
        if ($returnNewRecordSet) {
            $newSelect = clone $this->select;
            $newSelect->where($conditions, true);
            $newSet = new static($this->table, $newSelect);
            if ($resetOriginalRecordSet) {
                $this->resetRecords();
            }
            return $newSet;
        }

        // update OrmSelect and reset RecordSet
        $this->setSelect($this->select->where($conditions, true));
        return $this;
    }

    /**
     * Replace records ordering in OrmSelect.
     * Note: deletes already selected records and selects new.
     */
    public function replaceOrdering(
        DbExpr|array|string $column,
        bool $orderAscending = true
    ): static {
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

    public function isDbQueryAlreadyExecuted(): bool
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
        array|Closure|null $closureOrColumnsList = null,
        bool $enableReadOnlyMode = true
    ): array {
        if ($closureOrColumnsList) {
            return $this->getDataFromEachObject(
                $closureOrColumnsList,
                $enableReadOnlyMode
            );
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
            throw new \InvalidArgumentException(
                "Array does not contain index '{$index}'"
            );
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
     * This can speed up a count query for simple cases when joins are not nested
     * @see SelectQueryBuilderInterface::fetchCount()
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
        if (
            $ignoreLeftJoins !== null
            && $this->ignoreLeftJoinsForCount !== $ignoreLeftJoins
        ) {
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
