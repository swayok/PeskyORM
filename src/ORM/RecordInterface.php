<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

interface RecordInterface
{
    
    /**
     * Create new empty record
     * @return static
     */
    public static function newEmptyRecord();
    
    public static function getTable(): TableInterface;
    
    public static function hasColumn(string $name): bool;
    
    /**
     * @param string $name
     * @param string|null $format - filled when $name is something like 'timestamp_as_date' (returns 'date')
     * @return Column
     */
    public static function getColumn(string $name, string &$format = null): Column;
    
    public static function getPrimaryKeyColumnName(): string;
    
    public static function hasPrimaryKeyColumn(): bool;
    
    public static function getPrimaryKeyColumn(): Column;
    
    public static function getRelations(): array;
    
    public static function hasRelation(string $name): bool;
    
    public static function getRelation(string $name): Relation;
    
    /**
     * Resets all values and related records
     * @return static
     */
    public function reset();
    
    /**
     * Get a value from specific $columnName with optional $format
     * @param string|Column $column
     * @param null|string $format - change value format (list of formats depend on Column type and config)
     * @return mixed
     */
    public function getValue($column, ?string $format = null);
    
    /**
     * Check if there is a value for $columnName
     * @param string $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    public function hasValue($column, bool $trueIfThereIsDefaultValue = false): bool;
    
    /**
     * Set a $value for $columnName
     * @param string|Column $column
     * @param mixed $value
     * @param boolean $isFromDb
     * @return static
     */
    public function updateValue($column, $value, bool $isFromDb);
    
    /**
     * Get a value of the primary key column
     * @return mixed
     */
    public function getPrimaryKeyValue();
    
    /**
     * Check if there is a value for primary key column
     */
    public function hasPrimaryKeyValue(): bool;
    
    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     */
    public function existsInDb(bool $useDbQuery = false): bool;
    
    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return Record|RecordsSet
     */
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false);
    
    /**
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName - name of relation defined in TableStructure
     * @return static
     */
    public function readRelatedRecord(string $relationName);
    
    /**
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     */
    public function isRelatedRecordAttached(string $relationName): bool;
    
    /**
     * @param string|Relation $relationName
     * @param array|Record|RecordsArray $relatedRecord
     * @param bool|null $isFromDb - true: marks values as loaded from DB | null: autodetect
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return static
     */
    public function updateRelatedRecord($relationName, $relatedRecord, ?bool $isFromDb = null, bool $haltOnUnknownColumnNames = true);
    
    /**
     * Remove related record
     * @param string $relationName
     * @return static
     */
    public function unsetRelatedRecord(string $relationName);
    
    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return static
     */
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true);
    
    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     * @return static
     */
    public function fromDbData(array $data);
    
    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * @param int|float|string $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return static
     */
    public function fetchByPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []);
    
    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return static
     */
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []);
    
    /**
     * Reload data for current record.
     * Note: record must exist in DB
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return static
     */
    public function reload(array $columns = [], array $readRelatedRecords = []);
    
    /**
     * Read values for specific columns
     * @param array $columns - columns to read
     * @return static
     */
    public function readColumns(array $columns = []);
    
    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return static
     */
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true);
    
    /**
     * Start collecting column updates
     * To save collected values use commit(); to restore previous values use rollback()
     * Notes:
     * - commit() and rollback() will throw exception if used without begin()
     * - save() will throw exception if used after begin()
     * @return static
     */
    public function begin();
    
    /**
     * Record is currently collecting updates to be saved by Record->commit().
     * Returns true if Record->begin() was already called but Record->commit() or Record->rollback() was not called yet
     * @return bool
     */
    public function isCollectingUpdates(): bool;
    
    /**
     * Restore values updated since begin()
     * Note: throws exception if used without begin()
     * @return static
     */
    public function rollback();
    
    /**
     * Save values changed since begin() to DB
     * Note: throws exception if used without begin()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return static
     */
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false);
    
    /**
     * Save all values and requested relations to Db
     * Note: throws exception if used after begin() but before commit() or rollback()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return static
     */
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false);
    
    /**
     * Save requested relations to DB
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     */
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false);
    
    /**
     * Delete current Record from DB
     * Note: this Record must exist in DB
     * @param bool $resetAllValuesAfterDelete - true: will reset Record (default) | false: only primary key value will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @return static
     */
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true);
    
    /**
     * Get required values as array
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @return array
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
     * @return array
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
     * @return array
     */
    public function getDefaults(array $columns = [], bool $ignoreColumnsThatCannotBeSetManually = true, bool $nullifyDbExprValues = true): array;
    
    /**
     * @return static
     */
    public function enableReadOnlyMode();
    
    /**
     * @return static
     */
    public function disableReadOnlyMode();
    
    public function isReadOnly(): bool;
    
    /**
     * @return static
     */
    public function enableTrustModeForDbData();
    
    /**
     * @return static
     */
    public function disableTrustModeForDbData();
    
    public function isTrustDbDataMode(): bool;
    
    
}
