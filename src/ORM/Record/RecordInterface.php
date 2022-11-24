<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

interface RecordInterface
{
    
    /**
     * Create new empty record
     */
    public static function newEmptyRecord(): static;
    
    public static function getTable(): TableInterface;
    
    public static function hasColumn(string $name): bool;
    
    /**
     * @param string $name
     * @param string|null $format - filled when $name is something like 'timestamp_as_date' (returns 'date')
     */
    public static function getColumn(string $name, string &$format = null): TableColumnInterface;
    
    public static function getPrimaryKeyColumnName(): string;
    
    public static function hasPrimaryKeyColumn(): bool;
    
    public static function getPrimaryKeyColumn(): TableColumnInterface;
    
    public static function getRelations(): array;
    
    public static function hasRelation(string $name): bool;
    
    public static function getRelation(string $name): RelationInterface;
    
    /**
     * Resets all values and related records
     */
    public function reset(): static;
    
    /**
     * Get a value from specific $columnName with optional $format
     * @param string|TableColumnInterface $column
     * @param null|string $format - change value format (list of formats depend on TableColumn type and config)
     */
    public function getValue(string|TableColumnInterface $column, ?string $format = null): mixed;
    
    /**
     * Check if there is a value for $columnName
     * @param string|TableColumnInterface $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     */
    public function hasValue(string|TableColumnInterface $column, bool $trueIfThereIsDefaultValue = false): bool;
    
    /**
     * Set a $value for $columnName
     */
    public function updateValue(string|TableColumnInterface $column, mixed $value, bool $isFromDb): static;
    
    /**
     * Get a value of the primary key column
     */
    public function getPrimaryKeyValue(): int|float|string|null;
    
    /**
     * Check if there is a value for primary key column
     */
    public function hasPrimaryKeyValue(): bool;
    
    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     */
    public function existsInDb(bool $useDbQuery = false): bool;
    
    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     */
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false): RecordsSet|RecordsArray|RecordInterface;
    
    /**
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName - name of relation defined in TableStructure
     */
    public function readRelatedRecord(string $relationName): static;
    
    /**
     * Check if related object(s) are stored in this Record
     */
    public function isRelatedRecordAttached(string $relationName): bool;
    
    /**
     * @param string|RelationInterface $relationName
     * @param array|RecordInterface|RecordsArray|RecordsSet $relatedRecord
     * @param bool|null $isFromDb - true: marks values as loaded from DB | null: autodetect
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     */
    public function updateRelatedRecord(
        string|RelationInterface $relationName,
        array|RecordInterface|RecordsArray|RecordsSet $relatedRecord,
        ?bool $isFromDb = null,
        bool $haltOnUnknownColumnNames = true
    ): static;
    
    /**
     * Remove related record
     */
    public function unsetRelatedRecord(string $relationName): static;
    
    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     */
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static;
    
    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     */
    public function fromDbData(array $data): static;
    
    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     */
    public function fetchByPrimaryKey(int|float|string $pkValue, array $columns = [], array $readRelatedRecords = []): static;
    
    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     */
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []): static;
    
    /**
     * Reload data for current record.
     * Note: record must exist in DB
     */
    public function reload(array $columns = [], array $readRelatedRecords = []): static;
    
    /**
     * Read values for specific columns
     */
    public function readColumns(array $columns = []): static;
    
    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     */
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static;
    
    /**
     * Start collecting column updates
     * To save collected values use commit(); to restore previous values use rollback()
     * Notes:
     * - commit() and rollback() will throw exception if used without begin()
     * - save() will throw exception if used after begin()
     */
    public function begin(): static;
    
    /**
     * Record is currently collecting updates to be saved by Record->commit().
     * Returns true if Record->begin() was already called but Record->commit() or Record->rollback() was not called yet
     */
    public function isCollectingUpdates(): bool;
    
    /**
     * Restore values updated since begin()
     * Note: throws exception if used without begin()
     */
    public function rollback(): static;
    
    /**
     * Save values changed since begin() to DB
     * Note: throws exception if used without begin()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     */
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static;
    
    /**
     * Save all values and requested relations to Db
     * Note: throws exception if used after begin() but before commit() or rollback()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     */
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static;
    
    /**
     * Save requested relations to DB
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     */
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): void;
    
    /**
     * Delete current Record from DB
     * Note: this Record must exist in DB
     * @param bool $resetAllValuesAfterDelete - true: will reset Record (default) | false: only primary key value will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     */
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true): static;
    
    /**
     * Get required values as array
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false,
        bool $withFilesInfo = true
    ): array;
    
    /**
     * Get required values as array but exclude file columns
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     */
    public function toArrayWithoutFiles(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false
    ): array;
    
    /**
     * Collect default values for the columns
     * Note: if there is no default value for a column - null will be returned
     * @param array $columns - empty: return values for all columns
     * @param bool $ignoreColumnsThatCannotBeSetManually - true: if column does not exist in DB - its value will not be returned
     * @param bool $nullifyDbExprValues - true: if default value is DbExpr - replace it by null
     */
    public function getDefaults(array $columns = [], bool $ignoreColumnsThatCannotBeSetManually = true, bool $nullifyDbExprValues = true): array;
    
    /**
     * Enable read only mode. In this mode incoming data is not processed in any way and Record works like an array
     * but maintains most getters functionality including relations.
     * Usage of value formatters are allowed ({column}_as_array, {column}_as_object, etc.)
     * Relations returned as similar read only Records or RecordArrays.
     * In this mode you're able to use Record's methods that do not modify Record's data.
     */
    public function enableReadOnlyMode(): static;
    
    public function disableReadOnlyMode(): static;
    
    public function isReadOnly(): bool;
    
    /**
     * All values marked as "received from DB" will not be normalized and validated but record
     * will be not allowed to be saved to prevent possible issues.
     * This mode is designed to speed up DB data processing when you need to iterate over large number of records
     * where values are not intended to be modified and saved.
     */
    public function enableTrustModeForDbData(): static;
    
    /**
     * All values marked as "received from DB" will be normalized and validated (record is allowed to be saved)
     */
    public function disableTrustModeForDbData(): static;
    
    public function isTrustDbDataMode(): bool;
    
    
}
