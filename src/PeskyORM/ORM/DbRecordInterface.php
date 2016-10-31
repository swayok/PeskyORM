<?php

namespace PeskyORM\ORM;

interface DbRecordInterface {

    /**
     * Create new empty record
     * @return static
     */
    static public function newEmptyRecord();

    /**
     * @return DbTableInterface
     */
    static public function getTable();

    /**
     * Resets all values and related records
     * @return $this
     */
    public function reset();

    /**
     * Get a value from specific $columnName with optional $format
     * @param string $columnName
     * @param null $format - change value format (list of formats depend on DbColumn type and config)
     * @return mixed
     */
    public function getValue($columnName, $format = null);

    /**
     * Check if there is a value for $columnName
     * @param string $columnName
     * @param bool $checkDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    public function hasValue($columnName, $checkDefaultValue = false);

    /**
     * Set a $value for $columnName
     * @param string $columnName
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     */
    public function setValue($columnName, $value, $isFromDb);

    /**
     * Get a value of the primary key column
     * @return mixed
     */
    public function getPrimaryKeyValue();

    /**
     * Check if there is a value for primary key column
     * @return bool
     */
    public function hasPrimaryKeyValue();

    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     */
    public function existsInDb($useDbQuery = false);

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return DbRecord|DbRecordsSet
     */
    public function getRelatedRecord($relationName, $loadIfNotSet = false);

    /**
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName - name of relation defined in DbTableStructure
     * @return $this
     */
    public function readRelatedRecord($relationName);

    /**
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     */
    public function isRelatedRecordAttached($relationName);

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function fromData(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true);

    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     * @param array $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function fromDbData(array $data);

    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * @param int|float|string $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     */
    public function fromPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []);

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []);

    /**
     * Reload data for current record.
     * Note: record must exist in DB
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \PeskyORM\ORM\Exception\RecordNotFoundException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []);

    /**
     * Read values for specific columns
     * @param array $columns - columns to read
     * @return $this
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\RecordNotFoundException
     */
    public function readColumns(array $columns = []);

    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true);

    /**
     * Start collecting column updates
     * To save collected values use commit(); to restore previous values use rollback()
     * Notes:
     * - commit() and rollback() will throw exception if used without begin()
     * - save() will throw exception if used after begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function begin();

    /**
     * Restore values updated since begin()
     * Note: throws exception if used without begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function rollback();

    /**
     * Save values changed since begin() to DB
     * Note: throws exception if used without begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function commit();

    /**
     * Save all values and requested relations to Db
     * Note: throws exception if used after begin() but before commit() or rollback()
     * @param array $relationsToSave
     * @return $this
     * @throws \BadMethodCallException
     */
    public function save(array $relationsToSave = []);

    /**
     * Save requested relations to DB
     * Note: this Record must exist in DB
     * @param array $relationsToSave
     * @throws \BadMethodCallException
     */
    public function saveRelations(array $relationsToSave = []);

    /**
     * Delete current Record from DB
     * Note: this Record must exist in DB
     * @param bool $resetAllValuesAfterDelete - true: will reset Record (default) | false: only primary key value will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @return $this
     * @throws \BadMethodCallException
     */
    public function delete($resetAllValuesAfterDelete = true, $deleteFiles = true);

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
        $loadRelatedRecordsIfNotSet = false,
        $withFilesInfo = true
    );

    /**
     * Get required values as array but exclude file columns
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @return array
     */
    public function toArrayWitoutFiles(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        $loadRelatedRecordsIfNotSet = false
    );

    /**
     * Collect default values for the columns
     * Note: if there is no default value for a column - null will be returned
     * @param array $columns - empty: return values for all columns
     * @param bool $ignoreColumnsThatDoNotExistInDB - true: if column does not exist in DB - its value will not be returned
     * @param bool $nullifyDbExprValues - true: if default value is DbExpr - replace it by null
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getDefaults(array $columns = [], $ignoreColumnsThatDoNotExistInDB = true, $nullifyDbExprValues = true);


}