<?php

namespace PeskyORM\ORM;

class TempRecord implements RecordInterface {

    protected $data = [];
    protected $existsInDb = false;
    protected $tableName;

    /**
     * Create new empty record
     * @return static
     */
    static public function newEmptyRecord() {
        return new static();
    }

    /**
     * @param array $data
     * @param bool $existsInDb
     * @param null|string $tableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function newTempRecord(array $data, $existsInDb = false, $tableName = null) {
        return static::newEmptyRecord()
            ->fromData($data, $existsInDb)
            ->setTableName($tableName);
    }

    /**
     * @return TableInterface
     */
    static public function getTable() {
        throw new \BadMethodCallException('Temp Record has not Table');
    }

    /**
     * @param string $name
     * @return bool
     */
    static public function hasColumn($name): bool {
        return false;
    }

    /**
     * @param string $name
     * @param string|null $format - filled when $name is something like 'timestamp_as_date' (returns 'date')
     * @return Column
     */
    static public function getColumn(string $name, string &$format = null) {
        throw new \BadMethodCallException('TempRecord has no Columns');
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setTableName($name) {
        $this->tableName = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Resets all values and related records
     * @return $this
     */
    public function reset() {
        $this->data = [];
        return $this;
    }

    /**
     * Get a value from specific $columnName with optional $format
     * @param string|Column $column
     * @param null|string $format - change value format (list of formats depend on Column type and config)
     * @return mixed
     */
    public function getValue($column, ?string $format = null) {
        return array_key_exists($column, $this->data) ? $this->data[$column] : null;
    }

    /**
     * Check if there is a value for $columnName
     * @param string $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    public function hasValue($column, bool $trueIfThereIsDefaultValue = false): bool {
        return array_key_exists($column, $this->data);
    }

    /**
     * Set a $value for $columnName
     * @param string|Column $column
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     */
    public function updateValue($column, $value, bool $isFromDb) {
        $this->data[$column] = $value;
        return $this;
    }

    /**
     * Get a value of the primary key column
     * @return mixed
     */
    public function getPrimaryKeyValue() {
        return null;
    }

    /**
     * Check if there is a value for primary key column
     * @return bool
     */
    public function hasPrimaryKeyValue(): bool {
        return false;
    }

    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     */
    public function existsInDb(bool $useDbQuery = false): bool {
        return $this->existsInDb;
    }

    /**
     * @param bool $exists
     * @return $this
     */
    public function setEsistsInDb($exists) {
        $this->existsInDb = (bool)$exists;
        return $this;
    }

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return Record|RecordsSet
     */
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false) {
        throw new \BadMethodCallException('TempRecord has no Relations');
    }

    /**
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName - name of relation defined in TableStructure
     * @return $this
     */
    public function readRelatedRecord(string $relationName) {
        return $this;
    }

    /**
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     */
    public function isRelatedRecordAttached(string $relationName): bool {
        return false;
    }

    /**
     * @param string $relationName
     * @param array|Record|RecordsArray $relatedRecord
     * @param bool|null $isFromDb - true: marks values as loaded from DB | null: autodetect
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     */
    public function updateRelatedRecord($relationName, $relatedRecord, ?bool $isFromDb = null, bool  $haltOnUnknownColumnNames = true) {
        return $this;
    }

    /**
     * Remove related record
     * @param string $relationName
     * @return $this
     */
    public function unsetRelatedRecord(string $relationName) {
        return $this;
    }

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        $this->data = $data;
        $this->setEsistsInDb($isFromDb);
        return $this;
    }

    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     * @param array $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function fromDbData(array $data) {
        return $this->fromData($data, true);
    }

    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * @param int|float|string $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \BadMethodCallException
     */
    public function fetchByPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \BadMethodCallException
     */
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Reload data for current record.
     * Note: record must exist in DB
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \BadMethodCallException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Read values for specific columns
     * @param array $columns - columns to read
     * @return $this
     * @throws \BadMethodCallException
     */
    public function readColumns(array $columns = []) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Start collecting column updates
     * To save collected values use commit(); to restore previous values use rollback()
     * Notes:
     * - commit() and rollback() will throw exception if used without begin()
     * - save() will throw exception if used after begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function begin() {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Restore values updated since begin()
     * Note: throws exception if used without begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function rollback() {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Save values changed since begin() to DB
     * Note: throws exception if used without begin()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return $this
     * @throws \BadMethodCallException
     */
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Save all values and requested relations to Db
     * Note: throws exception if used after begin() but before commit() or rollback()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return $this
     * @throws \BadMethodCallException
     */
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Save requested relations to DB
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @throws \BadMethodCallException
     */
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

    /**
     * Delete current Record from DB
     * Note: this Record must exist in DB
     * @param bool $resetAllValuesAfterDelete - true: will reset Record (default) | false: only primary key value will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @return $this
     * @throws \BadMethodCallException
     */
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true) {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }

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
    ): array {
        if (
            empty($columnsNames)
            || (count($columnsNames) === 1 && $columnsNames[0] === '*')
            || in_array('*', $columnsNames, true)
        ) {
            return $this->data;
        } else {
            $ret = [];
            foreach ($columnsNames as $key) {
                $ret[$key] = $this->getValue($key);
            }
            return $ret;
        }
    }

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
    ): array {
        return $this->toArray($columnsNames, $relatedRecordsNames, $loadRelatedRecordsIfNotSet, false);
    }

    /**
     * Collect default values for the columns
     * Note: if there is no default value for a column - null will be returned
     * @param array $columns - empty: return values for all columns
     * @param bool $ignoreColumnsThatCannotBeSetManually - true: if column does not exist in DB - its value will not be returned
     * @param bool $nullifyDbExprValues - true: if default value is DbExpr - replace it by null
     * @return array
     */
    public function getDefaults(array $columns = [], bool $ignoreColumnsThatCannotBeSetManually = true, bool $nullifyDbExprValues = true): array {
        return [];
    }
    
    public function enableReadOnlyMode() {
    
    }
    
    public function disableReadOnlyMode() {
    
    }
    
    public function isReadOnly(): bool {
        return false;
    }
    
    public function enableTrustModeForDbData() {
    
    }
    
    public function disableTrustModeForDbData() {
    
    }
    
    public function isTrustDbDataMode(): bool {
        return false;
    }
}
