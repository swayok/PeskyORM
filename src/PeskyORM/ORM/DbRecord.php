<?php

namespace PeskyORM\ORM;

use PeskyORM\ORM\Exception\InvalidDataException;
use PeskyORM\ORM\Exception\InvalidTableColumnConfigException;
use PeskyORM\ORM\Exception\RecordNotFoundException;
use Swayok\Utils\StringUtils;

abstract class DbRecord implements \ArrayAccess, \Iterator {

    /**
     * Full class name of a DbTable object for this record
     * @var string
     */
    static protected $tableClass = null;
    /**
     * @var DbTable
     */
    static protected $table = null;
    /**
     * @var DbTableStructure
     */
    static protected $tableStructure = null;
    /**
     * @var array
     */
    static protected $fileColumnsNames = null;

    /**
     * @var DbRecordValue[]
     */
    protected $values = [];
    /**
     * @var DbRecord[]|DbRecordsSet[]
     */
    protected $relatedRecords = [];
    /**
     * @var bool
     */
    protected $isCollectingUpdates = false;
    /**
     * Collected when value is updated during $this->isCollectingUpdates === true
     * @var DbRecordValue[]
     */
    protected $valuesBackup = [];
    /**
     * @var int
     */
    protected $iteratorIdx = 0;

    /**
     * Create new record with values from $data array
     * @param array $data
     * @param bool $isFromDb
     * @param bool $haltOnUnknownColumnNames
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function fromArray(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        return static::newEmptyRecord()->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
    }

    /**
     * Create new record and load values from DB using $pkValue
     * @param mixed $pkValue
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function read($pkValue) {
        return static::newEmptyRecord()->fromPrimaryKey($pkValue);
    }

    /**
     * Create new record and find values in DB using $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function find(array $conditionsAndOptions) {
        return static::newEmptyRecord()->fromDb($conditionsAndOptions);
    }

    /**
     * Create new empty record
     * @return static
     */
    static public function newEmptyRecord() {
        return new static();
    }

    /**
     * Create new empty record (shortcut)
     * @return DbRecord
     */
    static public function _() {
        return static::newEmptyRecord();
    }

    public function __construct() {
        $this->reset();
    }

    /**
     * @return DbTable
     * @throws \BadMethodCallException
     */
    static public function getTable() {
        if (static::$table === null) {
            if (empty(static::$tableClass)) {
                throw new \BadMethodCallException(get_called_class() . '::$tableClass property is not defined');
            }
            static::$table = call_user_func(static::$tableClass, 'getInstance');
        }
        return static::$table;
    }

    /**
     * @return DbTableStructure
     * @throws \BadMethodCallException
     */
    static public function getTableStructure() {
        if (static::$tableStructure === null) {
            static::$tableStructure = static::getTable()->getStructure();
        }
        return static::$tableStructure;
    }

    /**
     * @return DbTableColumn[]
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumns() {
        return static::getTableStructure()->getColumns();
    }

    /**
     * @param string $name
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($name) {
        return static::getTableStructure()->getColumn($name);
    }

    /**
     * @param string $name
     * @return bool
     * @throws \BadMethodCallException
     */
    static public function hasColumn($name) {
        return static::getTableStructure()->hasColumn($name);
    }

    /**
     * @return DbTableColumn
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPrimaryKeyColumn() {
        return static::getTableStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getPrimaryKeyColumnName() {
        return static::getPrimaryKeyColumn()->getName();
    }

    /**
     * @return DbTableRelation[]
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::getTableStructure()->getRelations();
    }

    /**
     * @param string $name
     * @return DbTableRelation
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($name) {
        return static::getTableStructure()->getRelation($name);
    }

    /**
     * @param string $name
     * @return bool
     * @throws \BadMethodCallException
     */
    static public function hasRelation($name) {
        return static::getTableStructure()->hasRelation($name);
    }

    /**
     * @return DbTableColumn[]
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getFileColumns() {
        return static::getTableStructure()->getFileColumns();
    }

    /**
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        return static::getTableStructure()->hasFileColumns();
    }

    /**
     * Resets all values and related records
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function reset() {
        $this->values = [];
        $this->relatedRecords = [];
        $this->iteratorIdx = 0;
        $this->cleanUpdates();
        foreach (static::getTableStructure()->getColumns() as $columnName => $column) {
            $this->values[$columnName] = DbRecordValue::create($column, $this);
        }
        return $this;
    }

    /**
     * @param string $columnName
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function resetValue($columnName) {
        $this->values = DbRecordValue::create($this->getColumn($columnName), $this);
        return $this;
    }

    /**
     * Clean properties related to updated columns
     */
    protected function cleanUpdates() {
        $this->valuesBackup = [];
        $this->isCollectingUpdates = false;
    }

    /**
     * Warning: do not use it to get/set/check value!
     * @param string $columnName
     * @return DbRecordValue
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function getColumnValueObject($columnName) {
        return $this->values[static::getColumn($columnName)->getName()];
    }

    /**
     * @param string $columnName
     * @param null $format
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getColumnValue($columnName, $format = null) {
        return call_user_func(
            static::getColumn($columnName)->getValueGetter(),
            $this->getColumnValueObject($columnName),
            $format
        );
    }

    /**
     * @param string $columnName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function hasColumnValue($columnName) {
        return call_user_func(
            static::getColumn($columnName)->getValueExistenceChecker(),
            $this->getColumnValueObject($columnName)
        );
    }

    /**
     * @param string $columnName
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setColumnValue($columnName, $value, $isFromDb) {
        $valueContainer = $this->getColumnValueObject($columnName);
        if (!$isFromDb && !$valueContainer->getColumn()->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException("It is forbidden to modify or set value of a '{$columnName}' column");
        }
        $isCollectingUpdates = $this->isCollectingUpdates && $valueContainer->getColumn()->isItExistsInDb();
        if ($isCollectingUpdates && !array_key_exists($columnName, $this->valuesBackup)) {
            $this->valuesBackup[$columnName] = clone $valueContainer;
        }
        call_user_func(
            static::getColumn($columnName)->getValueSetter(),
            $value,
            (bool) $isFromDb,
            $this->getColumnValueObject($columnName)
        );
        return $this;
    }

    /**
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getPrimaryKeyValue() {
        return $this->getColumnValue(static::getPrimaryKeyColumnName());
    }

    /**
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function hasPrimaryKeyValue() {
        return $this->hasColumnValue(static::getPrimaryKeyColumnName());
    }

    /**
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function existsInDb() {
        return (
            $this->hasPrimaryKeyValue()
            && $this->getColumnValueObject(static::getPrimaryKeyColumnName())->isItFromDb()
        );
    }

    /**
     * @param string $relationName
     * @param array|DbRecord $relatedRecord
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function setRelatedRecord($relationName, $relatedRecord, $isFromDb, $haltOnUnknownColumnNames = true) {
        $relation = $this->getRelation($relationName);
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === DbTableRelation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = DbRecordsSet::createFromArray($relationTable, $relatedRecord);
            }
        } else if (is_array($relatedRecord)) {
            $relatedRecord = $relationTable->newRecord()
                ->fromData($relatedRecord, $isFromDb, $haltOnUnknownColumnNames);
        } else if ($relatedRecord instanceof DbRecord) {
            if ($relatedRecord->getTable()->getTableName() !== $relationTable) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of DbRecord class for a '{$relationTable->getTableName()}' DB table"
                );
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of DbRecord class for a '{$relationTable->getTableName()}' DB table"
            );
        }
        $this->relatedRecords[$relationName] = $relatedRecord;
    }

    /**
     * @param string $relationName
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function readRelatedRecord($relationName) {
        $relation = $this->getRelation($relationName);
        $relatedTable = $relation->getForeignTable();
        $conditions = array_merge(
            [$relation->getForeignColumn() => $this->getColumnValue($relation->getLocalColumn())],
            $relation->getAdditionalJoinConditions()
        );
        if ($relation->getType() === DbTableRelation::HAS_MANY) {
            $this->relatedRecords[$relationName] = $relatedTable->select('*', $conditions);
        } else {
            $this->relatedRecords[$relationName] = $relatedTable->newRecord();
            $data = $relatedTable->selectOne('*', $conditions);
            if (!empty($data)) {
                $this->relatedRecords[$relationName]->fromData($data, true, true);
            }
        }
        return $this;
    }

    /**
     * @param string $relationName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function hasRelatedRecord($relationName) {
        $this->getRelation($relationName);
        return array_key_exists($relationName, $this->relatedRecords);
    }

    /**
     * @param $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return DbRecord|DbRecordsSet
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getRelatedRecord($relationName, $loadIfNotSet = false) {
        if (!$this->hasRelatedRecord($relationName)) {
            if ($loadIfNotSet) {
                $this->readRelatedRecord($relationName);
            } else {
                throw new \BadMethodCallException(
                    "Related record with name '$relationName' is not set and autoloading is disabled"
                );
            }
        }
        return $this->relatedRecords[$relationName];
    }

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromData(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        $this->reset();
        $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
        return $this;
    }

    /**
     * Fill record values from passed $data. All values are marked as loaded from DB
     * @param array $data
     * @return DbRecord
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromDbData(array $data) {
        return $this->fromData($data, true, true);
    }

    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * @param mixed $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function fromPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return $this->fromDb([$this->getPrimaryKeyColumnName() => $pkValue], $columns, $readRelatedRecords);
    }

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        if (empty($columns)) {
            $columns = '*';
        }
        $hasOneAndBelongsToRelations = [];
        $hasManyRelations = [];
        foreach ($readRelatedRecords as $relationName) {
            if ($this->getRelation($relationName)->getType() === DbTableRelation::HAS_MANY) {
                $hasManyRelations[] = $relationName;
            } else {
                $hasOneAndBelongsToRelations[] = $relationName;
            }
        }
        $record = $this->getTable()->selectOne(
            $columns,
            array_merge(
                $conditionsAndOptions,
                ['CONTAIN' => $hasOneAndBelongsToRelations]
            )
        );
        if (!empty($record)) {
            $this->fromDbData($record);
            foreach ($hasManyRelations as $relationName) {
                $this->readRelatedRecord($relationName);
            }
        }
        return $this;
    }

    /**
     * Relaod data for current record.
     * Note: record must exist in DB
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Record must exist in DB');
        }
        return $this->fromPrimaryKey($this->getPrimaryKeyValue(), $columns, $readRelatedRecords);
    }

    /**
     * @param array $columns
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws RecordNotFoundException
     */
    public function readColumns(array $columns = []) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Record must exist in DB');
        }
        if (empty($columns)) {
            $this->reload($columns);
        } else {
            $data = $this->getTable()->selectOne(
                $columns, 
                [$this->getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]
            );
            if (empty($data)) {
                throw new RecordNotFoundException(
                    "Record with primary key '{$this->getPrimaryKeyValue()}' was not found in DB"
                );
            }
            $this->updateValues($data, true);
        }
        return $this;
    }

    /**
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        foreach ($data as $columnNameOrRelationName => $value) {
            if (static::hasColumn($columnNameOrRelationName)) {
                $this->setColumnValue($columnNameOrRelationName, $value, $isFromDb);
            } else if (static::hasRelation($columnNameOrRelationName)) {
                $this->setRelatedRecord($columnNameOrRelationName, $value, $isFromDb, $haltOnUnknownColumnNames);
            } else if ($haltOnUnknownColumnNames) {
                throw new \InvalidArgumentException(
                    "\$data argument contains unknown column name or relation name: '$columnNameOrRelationName'"
                );
            }
        }
        return $this;
    }

    /**
     * Start collecting column updates
     * @return $this
     * @throws \BadMethodCallException
     */
    public function begin() {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException('Attempt to begin collecting changes when already collecting changes');
        }
        $this->isCollectingUpdates = true;
        $this->valuesBackup = [];
        return $this;
    }

    /**
     * Restore values updated since $this->begin()
     * @return $this
     * @throws \BadMethodCallException
     */
    public function rollback() {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to rollback changed values: changes collecting was not started'
            );
        }
        if (!empty($this->valuesBackup)) {
            $this->values = array_replace($this->values, $this->valuesBackup);
        }
        $this->cleanUpdates();
        return $this;
    }

    /**
     * Save changed values to DB
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Core\DbException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function commit() {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to commit changed values: changes collecting was not started'
            );
        }
        $columnsToSave = array_keys($this->valuesBackup);
        $this->cleanUpdates();
        $this->saveToDb($columnsToSave);
        return $this;
    }

    /**
     * @param array $relationsToSave
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Core\DbException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function save(array $relationsToSave = []) {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to save data after begin(). You must call commit() or rollback()'
            );
        }
        $this->saveToDb($this->getAllColumnsWithUpdatableValues());
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave);
        }
        return $this;
    }

    /**
     * Get names of all columns that can be saved to db
     * @return array
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllColumnsWithUpdatableValues() {
        $columnsNames = [];
        foreach ($this->getColumns() as $columnName => $column) {
            if ($column->isValueCanBeSetOrChanged() && ($column->isItExistsInDb() || $column->isItAFile())) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }

    /**
     * Get names of all columns that should automatically update values on each save
     * @return array
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllAutoUpdatingColumns() {
        $columnsNames = [];
        foreach ($this->getColumns() as $columnName => $column) {
            if ($column->isAutoUpdatingValue()) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }

    /**
     * @param array $columnsToSave
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\Core\DbException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     */
    protected function saveToDb(array $columnsToSave = []) {
        if (empty($columnsToSave) && empty($relationsToSave)) {
            // nothing to save
            return;
        }
        $diff = array_diff(array_keys($this->values), $columnsToSave);
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains unknown columns: ' . implode(', ', $diff)
            );
        }
        $diff = array_diff($this->getAllColumnsWithUpdatableValues(), $columnsToSave);
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains columns that cannot be saved to DB: '  . implode(', ', $diff)
            );
        }
        $isUpdate = $this->existsInDb();
        if (!$isUpdate && $this->hasPrimaryKeyValue()) {
            $columnsToSave[] = $this->getPrimaryKeyColumnName();
        }
        $data = [];
        $columnsThatDoNotExistInDb = [];
        // collect values that are not from DB
        foreach ($columnsToSave as $columnName) {
            if ($this->getColumn($columnName)->isItExistsInDb()) {
                $valueObject = $this->getColumnValueObject($columnName);
                if ($valueObject->hasValue() && !$valueObject->isItFromDb()) {
                    $data[$columnName] = $this->getColumnValue($columnName);
                }
            } else {
                $columnsThatDoNotExistInDb[$columnName] = $columnName;
            }
        }
        // collect auto updates
        $autoUpdatingColumns = $this->getAllAutoUpdatingColumns();
        foreach ($autoUpdatingColumns as $columnName) {
            $data[$columnName] = static::getColumn($columnName)->getAutoUpdateForAValue();
        }
        $errors = $this->validateNewData($data, $columnsToSave);
        if (!empty($errors)) {
            throw new InvalidDataException($errors);
        }
        $data = array_diff($data, $columnsThatDoNotExistInDb);
        if (empty($data) && !$isUpdate) {
            $data = [
                $this->getPrimaryKeyColumnName() => $this->getTable()->getExpressionToSetDefaultValueForAColumn()
            ];
        }
        if (!empty($data)) {
            if ($isUpdate) {
                $newData = $this->getTable()->update(
                    $data, 
                    [$this->getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()],
                    true
                );
            } else {
                $newData = $this->getTable()->insert($data, true);
            }
            $this->updateValues($newData, true);
        }
        if (!empty($columnsThatDoNotExistInDb)) {
            $this->saveValuesForColumnsThatDoNotExistInDb($columnsThatDoNotExistInDb);
        }
        $this->afterSave(!$isUpdate);
    }

    /**
     * @param array $columnsToSave
     * @throws InvalidTableColumnConfigException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function saveValuesForColumnsThatDoNotExistInDb(array $columnsToSave) {
        foreach ($columnsToSave as $columnName) {
            $column = $this->getColumn($columnName);
            if (!$column->hasValueSaver()) {
                throw new InvalidTableColumnConfigException(
                    "Column '$columnName' must have a value saver function to save new values"
                );
            }
            call_user_func($column->getValueSaver(), $this->getColumnValueObject($columnName));
        }
    }

    /**
     * For child classes
     * Called after successful save() and commit()
     * @param boolean $isCreated - true: new record was created; false: old record was updated
     */
    protected function afterSave($isCreated) {

    }

    /**
     * Validate data
     * @param array $data
     * @param array $columnsNames - column names to validate. If col
     * @return array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function validateNewData(array $data, array $columnsNames = []) {
        if (!count($columnsNames)) {
            $columnsNames = array_keys($data);
        }
        $errors = [];
        foreach ($columnsNames as $columnName) {
            $column = $this->getColumn($columnName);
            $value = array_key_exists($columnName, $data) ? $data[$columnName] : null;
            $columnErrors = $column->validateNewValue($value);
            if (!empty($columnErrors)) {
                $errors[$columnName] = $columnErrors;
            }
        }
        return $errors;
    }

    /**
     * @param array $relationsToSave
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\Core\DbException
     */
    public function saveRelations(array $relationsToSave = []) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException(
                'It is impossible to save related objects of a record that does not exist in DB'
            );
        }
        $relations = $this->getRelations();
        $diff = array_diff($relationsToSave, array_keys($relations));
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$relationsToSave argument contains unknown relations: ' .implode(', ', $diff)
            );
        }
        foreach ($relationsToSave as $relationName) {
            if ($this->hasRelatedRecord($relationName)) {
                $record = $this->getRelatedRecord($relationName);
                if ($record instanceof DbRecord) {
                    $record->setColumnValue(
                        $relations[$relationName]->getForeignColumn(),
                        $this->getColumnValue($relations[$relationName]->getLocalColumn()),
                        false
                    );
                    $record->save();
                } else {
                    foreach ($record as $recordObj) {
                        $recordObj->setColumnValue(
                            $relations[$relationName]->getForeignColumn(),
                            $this->getColumnValue($relations[$relationName]->getLocalColumn()),
                            false
                        );
                        $recordObj->save();
                    }
                }
            }
        }
    }

    /**
     * Delete current object
     * @param bool $resetFields - true: will reset DbFields (default) | false: only primary key will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @return $this
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function delete($resetFields = true, $deleteFiles = true) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Unable to delete record that does not exist in DB');
        } else {
            $table = $this->getTable();
            $alreadyInTransaction = $table->inTransaction();
            if (!$alreadyInTransaction) {
                $table->beginTransaction();
            }
            try {
                $table->delete([$this->getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]);
            } catch (\PDOException $exc) {
                $table->rollBackTransaction();
                throw $exc;
            }
            $this->afterDelete(); //< transaction can be closed there
            if (!$alreadyInTransaction && $table->inTransaction()) {
                $table->commitTransaction();
            }
            $this->deleteColumnValuesThatDoesNotExistInDb($deleteFiles);
        }
        // note: related objects delete must be managed only by database relations (foreign keys), not here
        if ($resetFields) {
            $this->reset();
        } else {
            $this->resetValue($this->getPrimaryKeyColumnName());
        }
        return $this;
    }

    /**
     * Run value deleters for all columns taht does not exist in DB and has value deleter function
     * @param boolean $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function deleteColumnValuesThatDoesNotExistInDb($deleteFiles = true) {
        if (!$this->hasPrimaryKeyValue()) {
            throw new \BadMethodCallException(
                'Unable to delete files attached to a record that does not have a primary key value'
            );
        }
        foreach ($this->getColumns() as $columnName => $column) {
            if (!$column->isItExistsInDb() && $column->hasValueDeleter() && ($deleteFiles || !$column->isItAFile())) {
                call_user_func($column->getValueDeleter(), $this->getColumnValueObject($columnName));
            }
        }
    }

    /**
     * Called after successful delete but before columns values resetted
     * (for child classes)
     */
    protected function afterDelete() {

    }

    /**
     * Return the current element
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function current() {
        $key = $this->key();
        return $key!== null ? $this->getColumnValue($key) : null;
    }

    /**
     * Move forward to next element
     */
    public function next() {
        $this->iteratorIdx++;
    }

    /**
     * Return the key of the current element
     * @return mixed scalar on success, or null on failure.
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function key() {
        if ($this->valid()) {
            return array_keys($this->getColumns())[$this->iteratorIdx];
        } else {
            return null;
        }
    }

    /**
     * Checks if current position is valid
     * @return boolean
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function valid() {
        return array_key_exists($this->iteratorIdx, array_keys($this->getColumns()));
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind() {
        $this->iteratorIdx = 0;
    }

    /**
     * @param string $key - column name or relation name
     * @return boolean - true on success or false on failure.
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetExists($key) {
        return (
            $this->hasColumn($key) && $this->hasColumnValue($key)
            || $this->hasRelation($key) && $this->hasRelatedRecord($key)
        );
    }

    /**
     * @param mixed $key - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if ($this->hasRelation($key)) {
            return $this->getRelatedRecord($key);
        } else {
            if (!$this->hasColumn($key) && preg_match('%^(.+)_as_()%is', $key, $parts)) {
                return $this->getColumnValue($parts[1], $parts[2]);
            } else {
                return $this->getColumnValue($key);
            }
        }
    }

    /**
     * @param mixed $key - column name or relation name
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetSet($key, $value) {
        if ($this->hasRelation($key)) {
            $this->setRelatedRecord($key, $value, false);
        } else {
            $this->setColumnValue($key, $value, false);
        }
    }

    /**
     * @param mixed $key
     * @throws \BadMethodCallException
     */
    public function offsetUnset($key) {
        throw new \BadMethodCallException('It is forbidden to unset a value');
    }

    /**
     * @param $name - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __get($name) {
        return $this->offsetGet($name);
    }

    /**
     * @param $name - 'setColumnName' or 'setRelationName'
     * @param $value
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    /**
     * @param $name - column name or relation name
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __isset($name) {
        return $this->offsetExists($name);
    }

    /**
     * @param $name - column name or relation name
     * @throws \BadMethodCallException
     */
    public function __unset($name) {
        return $this->offsetUnset($name);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __call($name, array $arguments) {
        $isValidName = preg_match('%^set([A-Z][a-zA-Z0-9]*)$%', $name, $nameParts);
        if (!$isValidName) {
            throw new \BadMethodCallException(
                "Magic method '{$name}()' is forbidden. You can magically call only methods starting with 'set', for example: setId(1)"
            );
        } else if (count($arguments) > 2) {
            throw new \InvalidArgumentException(
                "Magic method '{$name}()' accepts only 2 arguments, but " . count($arguments) . ' arguments passed'
            );
        } else if (array_key_exists(1, $arguments) && !is_bool($arguments[1])) {
            throw new \InvalidArgumentException(
                "2nd argument for magic method '{$name}()' must be a boolean and reflects if value received from DB"
            );
        }
        list($value, $isFromDb) = $arguments;
        if ($this->hasRelation($nameParts[1])) {
            if (
                !is_object($value)
                || (
                    !($value instanceof DbRecord)
                    && !($value instanceof DbRecordsSet)
                )
            ) {
                throw new \InvalidArgumentException(
                    "1st argument for magic method '{$name}()' must be an instance of DbRecord class or DbRecordsSet class"
                );
            }
            $this->setRelatedRecord($nameParts[1], $value, $isFromDb);
        } else {
            $columnName = StringUtils::underscore($nameParts[1]);
            if (!$this->hasColumn($columnName)) {
                throw new \BadMethodCallException(
                    "Magic method '{$name}()' is not linked with any column or relation"
                );
            }
            $this->setColumnValue($columnName, $value, $isFromDb);
        }
        return $this;
    }

    /**
     * Get required values as array
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read all not set relations from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @return array
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        $loadRelatedRecordsIfNotSet = false,
        $withFilesInfo = true
    ) {
        if (empty($columnsNames)) {
            $columnsNames = array_keys($this->values);
        }
        $data = [];
        $fileColumns = $this->getFileColumns();
        foreach ($columnsNames as $columnsName) {
            if (array_key_exists($columnsName, $fileColumns)) {
                if ($withFilesInfo) {
                    /** @var DbFileInfo|DbImageFileInfo $fileInfo */
                    $fileInfo = $this->getColumnValue($columnsName);
                    $data[$columnsName] = $fileInfo->toArray();
                }
            } else {
                $data[$columnsName] = $this->getColumnValue($columnsName);
            }
        }
        foreach ($relatedRecordsNames as $relatedRecordName) {
            $relatedRecord = $this->getRelatedRecord($relatedRecordName, $loadRelatedRecordsIfNotSet);
            if ($relatedRecord instanceof DbRecord) {
                $data[$relatedRecordName] = $withFilesInfo
                    ? $relatedRecord->toArray()
                    : $relatedRecord->toArrayWitoutFiles();
            } else {
                /** @var DbRecordsSet $relatedRecord*/
                $relatedRecord->setSingleDbRecordIterationMode(true);
                $data[$relatedRecordName][] = [];
                foreach ($relatedRecord as $relRecord) {
                    $data[$relatedRecordName][] = $withFilesInfo
                        ? $relRecord->toArray()
                        : $relRecord->toArrayWitoutFiles();
                }
            }
        }
        return $data;
    }

    /**
     * Get required values as array
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read all not set relations from DB
     * @return array
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function toArrayWitoutFiles(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        $loadRelatedRecordsIfNotSet = false
    ) {
        return $this->toArray($columnsNames, $relatedRecordsNames, $loadRelatedRecordsIfNotSet, false);
    }

    /**
     * Collect default values for the columns
     * Note: ifthere is no default value for a column - null will be returned
     * @param array $columns - empty: return values for all columns
     * @param bool $ignoreColumnsThatDoNotExistInDB - true: if column does not exist in DB - its value will not be returned
     * @return array
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getDefaults(array $columns = [], $ignoreColumnsThatDoNotExistInDB = true) {
        if (empty($columns)) {
            $columns = array_keys($this->values);
        }
        $values = array();
        foreach ($columns as $columnName) {
            $column = $this->getColumn($columnName);
            if ($ignoreColumnsThatDoNotExistInDB && !$column->isItExistsInDb()) {
                continue;
            } else {
                $values[$columnName] = $column->hasDefaultValue() ? $column->getDefaultValue() : null;
            }
        }
        return $values;
    }

}