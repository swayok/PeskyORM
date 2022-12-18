<?php

declare(strict_types=1);

namespace PeskyORM\ORM\RecordsCollection;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;

interface RecordsCollectionInterface extends \Countable, \ArrayAccess, \Iterator
{
    /**
     * Count fetched records
     */
    public function count(): int;

    /**
     * Count total records in DB
     */
    public function totalCount(): int;

    /**
     * Reset stored data
     * Note: RecordsArray instance won't be usable after this
     * while RecordsSet can fetch data again
     */
    public function resetRecords(): static;

    /**
     * Fetch data for HAS MANY relation and inject it into records.
     * There should be a single DB query to get all required data for
     * all records.
     * This method addesses N+1 DB queries problem so that there
     * will be only 2 DB queries.
     * @see SelectQueryBuilderInterface::columns() - $columnsToSelect
     */
    public function injectHasManyRelationData(
        string $relationName,
        array $columnsToSelect = ['*']
    ): static;

    /**
     * Checks if DB query already executed and data received.
     */
    public function isDbQueryAlreadyExecuted(): bool;

    /**
     * Optimize iteration to reuse RecordInterface instance
     * and disable validation for data received from DB.
     * @see self::enableDbRecordInstanceReuseDuringIteration()
     * @see self::disableDbRecordDataValidation()
     */
    public function optimizeIteration(): static;

    /**
     * Collection will create a single instance of RecordInterface
     * and use it for iteration.
     * This way iteration will be a bit faster and consume less memory.
     * Make sure you do not use this RecordInterface instance outside of
     * iteration! Its contents are changing on each cycle.
     */
    public function enableDbRecordInstanceReuseDuringIteration(): static;

    public function disableDbRecordInstanceReuseDuringIteration(): static;

    /**
     * @see RecordInterface::enableTrustModeForDbData()
     */
    public function enableDbRecordDataValidation(): static;

    /**
     * @see RecordInterface::disableTrustModeForDbData()
     */
    public function disableDbRecordDataValidation(): static;

    /**
     * @see RecordInterface::enableReadOnlyMode()
     */
    public function enableReadOnlyMode(): static;

    /**
     * @see RecordInterface::disableReadOnlyMode()
     */
    public function disableReadOnlyMode(): static;

    /**
     * @see self::getDataFromEachObject()
     * When $closureOrColumnsList in null - records will be returned as arrays.
     */
    public function toArrays(
        array|\Closure|null $closureOrColumnsList = null,
        bool $enableReadOnlyMode = true
    ): array;

    /**
     * Convert arrays to objects and return.
     * @return RecordInterface[]
     */
    public function toObjects(): array;

    /**
     * Get some specific data from each object.
     * When $closureOrColumnsList is array it expects list of columns
     * compatible with RecordInterface->toArray().
     * $closureOrColumnsList closure signature:
     * function (RecordInterface $record): mixed {}
     * Function can return anything.
     * You can return KeyValuePair from closure to get associative array in result.
     * This method should activate enableDbRecordInstanceReuseDuringIteration()
     * for better performance.
     * @see KeyValuePair
     * @see RecordInterface::toArray()
     * @see RecordInterface::enableReadOnlyMode() - $enableReadOnlyMode = true
     * @see RecordInterface::disableDbRecordDataValidation() - $enableReadOnlyMode = false
     * @see self::enableDbRecordInstanceReuseDuringIteration()
     */
    public function getDataFromEachObject(
        array|\Closure $closureOrColumnsList,
        bool $enableReadOnlyMode = true
    ): array;

    /**
     * Get $columnName values from all records.
     * $filter closure must be compatible with array_filter().
     */
    public function getValuesForColumn(
        string $columnName,
        mixed $defaultValue = null,
        \Closure $filter = null
    ): array;

    /**
     * Filter records and create new RecordsArray from remaining records.
     * $filter closure must be compatible with array_filter().
     */
    public function filterRecords(
        \Closure $filter,
        bool $resetOriginalRecordsArray = false
    ): RecordsCollectionInterface;

    /**
     * Find single record by $columnName and $expectedValue within selected records.
     * Returns null if no matching record found
     */
    public function findOne(
        string $columnName,
        mixed $expectedValue,
        bool $asObject
    ): RecordInterface|array|null;

    /**
     * Get first record
     */
    public function first(): RecordInterface;

    /**
     * Get last record
     */
    public function last(): RecordInterface;
}
