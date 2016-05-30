<?php

namespace PeskyORM\ORM;

use PeskyORM\ORM\Exception\InvalidDataException;
use PeskyORM\ORM\Exception\InvalidTableColumnConfigException;

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
     * Resets all values and related records
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function reset() {
        $this->values = [];
        $this->relatedRecords = [];
        $this->cleanUpdates();
        foreach (static::getTableStructure()->getColumns() as $columnName => $column) {
            $this->values[$columnName] = DbRecordValue::create($column, $this);
        }
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
        $relationTableName = $relation->getForeignTableName();
        if ($relation->getType() === DbTableRelation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = DbRecordsSet::createFromArray(
                    DbClassesManager::i()->getTableInstance($relationTableName),
                    $relatedRecord
                );
            }
        } else if (is_array($relatedRecord)) {
            $relatedRecord = DbClassesManager::i()->newRecord($relationTableName)
                ->fromData($relatedRecord, $isFromDb, $haltOnUnknownColumnNames);
        } else if ($relatedRecord instanceof DbRecord) {
            if ($relatedRecord->getTable()->getTableName() !== $relationTableName) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of DbRecord class for a '$relationTableName' DB table"
                );
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of DbRecord class for a '$relationTableName' DB table"
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
        $relatedTable = DbClassesManager::i()->getTableInstance($relation->getForeignTableName());
        $conditions = array_merge(
            [$relation->getForeignColumn() => $this->getColumnValue($relation->getLocalColumn())],
            $relation->getAdditionalJoinConditions()
        );
        if ($relation->getType() === DbTableRelation::HAS_MANY) {
            $this->relatedRecords[$relationName] = $relatedTable->select('*', $conditions);
        } else {
            $this->relatedRecords[$relationName] = DbClassesManager::i()->newRecord($relation->getForeignTableName());
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
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function fromPrimaryKey($pkValue, array $readRelatedRecords = []) {
        $hasOneAndBelongsToRelations = [];
        $hasManyRelations = [];
        foreach ($readRelatedRecords as $relationName) {
            if ($this->getRelation($relationName)->getType() === DbTableRelation::HAS_MANY) {
                $hasManyRelations[] = $relationName;
            } else {
                $hasOneAndBelongsToRelations[] = $relationName;
            }
        }
        $record = $this->getTable()->selectOne('*', [
            $this->getPrimaryKeyColumnName() => $pkValue,
            'CONTAIN' => $hasOneAndBelongsToRelations
        ]);
        if (!empty($record)) {
            $this->fromDbData($record);
            foreach ($hasManyRelations as $relationName) {
                $this->readRelatedRecord($relationName);
            }
        }
        return $this;
    }

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function fromDb(array $conditionsAndOptions) {
        $record = $this->getTable()->selectOne('*', $conditionsAndOptions);
        if (!empty($record)) {
            return $this->fromDbData($record);
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
        foreach ($columnsToSave as $colName) {
            if ($this->hasColumnValue($colName)) {
                $data[$colName] = $this->getColumnValue($colName);
            }
        }
        $errors = $this->validateData($data, $columnsToSave);
        if (!empty($errors)) {
            throw new InvalidDataException($errors);
        }
        $filesColumns = $this->getFileColumns();
        $files = array_intersect($data, $filesColumns);
        $data = array_diff($data, $filesColumns);
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
        if (!empty($files)) {
            foreach ($files as $colName => $value) {
                if (!$filesColumns[$colName]->hasValueSaver()) {
                    throw new InvalidTableColumnConfigException("File column '$colName' must have a value saver");
                }
                call_user_func($filesColumns[$colName]->getValueSaver(), $this->getColumnValueObject($colName));
            }
        }
    }

    /**
     * Validate data
     * @param array $data
     * @param array $columnsNames - column names to validate. If col
     * @return array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function validateData(array $data, array $columnsNames = []) {
        if (!count($columnsNames)) {
            $columnsNames = array_keys($data);
        }
        $errors = [];
        foreach ($columnsNames as $columnName) {
            $column = $this->getColumn($columnName);
            if (array_key_exists($columnName, $data)) {
                $colErrors = $column->validateValue($data[$columnName]);
            } else if ($column->isValueRequired()) {
                $colErrors = [
                    DbRecordValueHelpers::getErrorMessage(
                        $column->getValidationErrorsLocalization(),
                        $column::VALUE_IS_REQUIRED
                    )
                ];
            }
            if (!empty($colErrors)) {
                $errors[$columnName] = $colErrors;
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
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @since 5.0.0
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
     * @param string $key
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
     * @param mixed $key
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if ($this->hasRelation($key)) {
            $this->getRelatedRecord($key);
        } else {
            $this->getColumnValue($key);
        }
    }

    /**
     * @param mixed $key
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

    public function __get($name) {
        return $this->offsetGet($name);
    }

    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    public function __isset($name) {
        return $this->offsetExists($name);
    }

    public function __call($name, $arguments) {
        $validName = preg_match('%^set([A-Z][a-zA-Z0-9]*)$%', $name, $nameParts);
        if (!$validName) {
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
        // todo: finish this - $nameParts
    }


}