<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Exception\InvalidDataException;
use PeskyORM\ORM\Exception\RecordNotFoundException;
use Swayok\Utils\StringUtils;

abstract class DbRecord implements DbRecordInterface, \ArrayAccess, \Iterator, \Countable {

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
     * @return static
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function fromArray(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        return static::newEmptyRecord()->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
    }

    /**
     * Create new record and load values from DB using $pkValue
     * @param mixed $pkValue
     * @param array $columns
     * @param array $readRelatedRecords
     * @return static
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function read($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return static::newEmptyRecord()->fromPrimaryKey($pkValue, $columns, $readRelatedRecords);
    }

    /**
     * Create new record and find values in DB using $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns
     * @param array $readRelatedRecords
     * @return static
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function find(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        return static::newEmptyRecord()->fromDb($conditionsAndOptions, $columns, $readRelatedRecords);
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
     * @return static
     */
    static public function _() {
        return static::newEmptyRecord();
    }

    /**
     * Create new empty record (shortcut)
     * @return static
     */
    static public function new1() {
        return static::newEmptyRecord();
    }

    public function __construct() {
        $this->reset();
    }

    /**
     * @return DbTableStructure
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     */
    static public function getTableStructure() {
        return static::getTable()->getStructure();
    }

    /**
     * @return DbTableColumn[] - key = column name
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumns() {
        return static::getTableStructure()->getColumns();
    }

    /**
     * @return DbTableColumn[] - key = column name
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatExistInDb() {
        static $columns = null;
        if ($columns === null) {
            $columns = [];
            $allColumns = static::getColumns();
            foreach ($allColumns as $name => $column) {
                if ($column->isItExistsInDb()) {
                    $columns[$name] = $column;
                }
            }
        }
        return $columns;
    }

    /**
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatDoNotExistInDb() {
        static $columns = null;
        if ($columns === null) {
            $columns = [];
            $allColumns = static::getColumns();
            foreach ($allColumns as $name => $column) {
                if (!$column->isItExistsInDb()) {
                    $columns[$name] = $column;
                }
            }
        }
        return $columns;
    }

    /**
     * @param string $name
     * @return DbTableColumn
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getColumn($name) {
        return static::getTableStructure()->getColumn($name);
    }

    /**
     * @param string $name
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasColumn($name) {
        return static::getTableStructure()->hasColumn($name);
    }

    /**
     * @return DbTableColumn
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPrimaryKeyColumn() {
        return static::getTableStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getPrimaryKeyColumnName() {
        return static::getPrimaryKeyColumn()->getName();
    }

    /**
     * @return DbTableRelation[]
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::getTableStructure()->getRelations();
    }

    /**
     * @param string $name
     * @return DbTableRelation
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($name) {
        return static::getTableStructure()->getRelation($name);
    }

    /**
     * @param string $name
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasRelation($name) {
        return static::getTableStructure()->hasRelation($name);
    }

    /**
     * @return DbTableColumn[]
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getFileColumns() {
        return static::getTableStructure()->getFileColumns();
    }

    /**
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        return static::getTableStructure()->hasFileColumns();
    }

    /**
     * Resets all values and related records
     * @return $this
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function reset() {
        $this->values = [];
        $this->relatedRecords = [];
        $this->iteratorIdx = 0;
        $this->cleanUpdates();
        foreach (static::getTableStructure()->getColumns() as $columnName => $column) {
            $this->values[$columnName] = $this->createValueObject($column);
        }
        return $this;
    }

    /**
     * @param DbTableColumn $column
     * @return DbRecordValue
     */
    protected function createValueObject(DbTableColumn $column) {
        return DbRecordValue::create($column, $this);
    }

    /**
     * @param string|DbTableColumn $column
     * @return $this
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function resetValue($column) {
        if (is_string($column)) {
            $this->values[$column] = $this->createValueObject(static::getColumn($column));
        } else {
            $this->values[$column->getName()] = $this->createValueObject($column);
        }
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
     * @param string|DbTableColumn $column
     * @return DbRecordValue
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function getValueObject($column) {
        if (is_string($column)) {
            static::getColumn($column); // to validate if there is such column
            return $this->values[$column];
        } else {
            return $this->values[$column->getName()];
        }
    }

    /**
     * @param string|DbTableColumn $columnName
     * @param null $format
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getValue($columnName, $format = null) {
        $column = $columnName instanceof DbTableColumn ? $columnName : static::getColumn($columnName);
        return call_user_func($column->getValueGetter(), $this->getValueObject($column), $format);
    }

    /**
     * Check if there is a value for $columnName
     * @param string|DbTableColumn $columnName
     * @param bool $checkDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     */
    public function hasValue($columnName, $checkDefaultValue = false) {
        return $this->_hasValue($this->getValueObject($columnName), $checkDefaultValue);
    }

    /**
     * @param DbRecordValue $value
     * @param bool $checkDefaultValue - true: returns true if there is no value set but column has default value
     * @return mixed
     */
    protected function _hasValue(DbRecordValue $value, $checkDefaultValue = false) {
        return call_user_func($value->getColumn()->getValueExistenceChecker(), $value, $checkDefaultValue);
    }

    /**
     * @param string|DbTableColumn $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function isValueFromDb($column) {
        return $this->getValueObject($column)->isItFromDb();
    }

    /**
     * @param string|DbTableColumn $columnName
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setValue($columnName, $value, $isFromDb) {
        $valueContainer = $this->getValueObject($columnName);
        if (!$isFromDb && !$valueContainer->getColumn()->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException("It is forbidden to modify or set value of a '{$columnName}' column");
        }
        $column = $columnName instanceof DbTableColumn ? $columnName : static::getColumn($columnName);
        if ($this->isCollectingUpdates && !array_key_exists($column->getName(), $this->valuesBackup)) {
            $this->valuesBackup[$column->getName()] = clone $valueContainer;
        }
        call_user_func($column->getValueSetter(), $value, (bool) $isFromDb, $this->getValueObject($column));
        if (!$valueContainer->isValid()) {
            throw new InvalidDataException([$column->getName() => $valueContainer->getValidationErrors()]);
        }
        return $this;
    }

    /**
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getPrimaryKeyValue() {
        return $this->getValue(static::getPrimaryKeyColumn());
    }

    /**
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function hasPrimaryKeyValue() {
        return $this->hasValue(static::getPrimaryKeyColumn(), false);
    }

    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function existsInDb($useDbQuery = false) {
        return (
            $this->hasPrimaryKeyValue()
            && $this->getValueObject(static::getPrimaryKeyColumn())->isItFromDb()
            && (!$useDbQuery || $this->_existsInDb())
        );
    }

    /**
     * Check if current Record exists in DB using DB query
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function _existsInDb() {
        return static::getTable()->hasMatchingRecord([
            static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()
        ]);
    }

    /**
     * @param string $relationName
     * @param array|DbRecord $relatedRecord
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     */
    protected function setRelatedRecord($relationName, $relatedRecord, $isFromDb, $haltOnUnknownColumnNames = true) {
        $relation = static::getRelation($relationName);
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === DbTableRelation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = DbRecordsSet::createFromArray($relationTable, $relatedRecord);
            }
        } else if (is_array($relatedRecord)) {
            $relatedRecord = $relationTable->newRecord()
                ->fromData($relatedRecord, $isFromDb, $haltOnUnknownColumnNames);
        } else if ($relatedRecord instanceof DbRecord) {
            if ($relatedRecord::getTable()->getName() !== $relationTable) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of DbRecord class for a '{$relationTable->getName()}' DB table"
                );
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of DbRecord class for a '{$relationTable->getName()}' DB table"
            );
        }
        $this->relatedRecords[$relationName] = $relatedRecord;
    }

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return DbRecord|DbRecordsSet
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getRelatedRecord($relationName, $loadIfNotSet = false) {
        if (!$this->isRelatedRecordAttached($relationName)) {
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
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function readRelatedRecord($relationName) {
        $relation = static::getRelation($relationName);
        $relatedTable = $relation->getForeignTable();
        $conditions = array_merge(
            [$relation->getForeignColumnName() => $this->getValue($relation->getLocalColumnName())],
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
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function isRelatedRecordAttached($relationName) {
        static::getRelation($relationName);
        return array_key_exists($relationName, $this->relatedRecords);
    }

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromData(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        $this->reset();
        $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
        return $this;
    }

    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     * @param array $data
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromDbData(array $data) {
        return $this->fromData($data, true, true);
    }

    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * @param int|float|string $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function fromPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return $this->fromDb([static::getPrimaryKeyColumnName() => $pkValue], $columns, $readRelatedRecords);
    }

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        if (empty($columns)) {
            $columns = ['*'];
        } else {
            $columns[] = static::getPrimaryKeyColumnName();
        }
        $columnsFromRelations = [];
        $hasManyRelations = [];
        foreach ($readRelatedRecords as $relationName) {
            if (static::getRelation($relationName)->getType() === DbTableRelation::HAS_MANY) {
                $hasManyRelations[] = $relationName;
            } else {
                $columnsFromRelations[$relationName] = '*';
            }
        }
        $record = static::getTable()->selectOne(
            array_merge(array_unique($columns), $columnsFromRelations),
            $conditionsAndOptions
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
     * Reload data for current record.
     * Note: record must exist in DB
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\RecordNotFoundException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []) {
        if (!$this->existsInDb()) {
            throw new RecordNotFoundException('Record must exist in DB');
        }
        return $this->fromPrimaryKey($this->getPrimaryKeyValue(), $columns, $readRelatedRecords);
    }

    /**
     * Read values for specific columns
     * @param array $columns - columns to read
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws RecordNotFoundException
     */
    public function readColumns(array $columns = []) {
        if (!$this->existsInDb()) {
            throw new RecordNotFoundException('Record must exist in DB');
        }
        if (empty($columns)) {
            $this->reload($columns);
        } else {
            $data = static::getTable()->selectOne(
                $columns, 
                [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]
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
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        foreach ($data as $columnNameOrRelationName => $value) {
            if (static::hasColumn($columnNameOrRelationName)) {
                $this->setValue($columnNameOrRelationName, $value, $isFromDb);
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
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function merge(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        return $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
    }

    /**
     * Start collecting column updates
     * To save collected values use commit(); to restore previous values use rollback()
     * Notes:
     * - commit() and rollback() will throw exception if used without begin()
     * - save() will throw exception if used after begin()
     * @return $this
     * @throws \PeskyORM\ORM\Exception\RecordNotFoundException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     */
    public function begin() {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException('Attempt to begin collecting changes when already collecting changes');
        } else if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Trying to begin collecting changes on not existing record');
        }
        $this->isCollectingUpdates = true;
        $this->valuesBackup = [];
        return $this;
    }

    /**
     * Restore values updated since begin()
     * Note: throws exception if used without begin()
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
     * Save values changed since begin() to DB
     * @return $this
     * @throws \UnexpectedValueException
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
     * Get names of all columns that can be saved to db
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllColumnsWithUpdatableValues() {
        $columnsNames = [];
        foreach (static::getColumns() as $columnName => $column) {
            if ($column->isValueCanBeSetOrChanged() && ($column->isItExistsInDb() || $column->isItAFile())) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }

    /**
     * Get names of all columns that should automatically update values on each save
     * Note: throws exception if used without begin()
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllAutoUpdatingColumns() {
        $columnsNames = [];
        foreach (static::getColumns() as $columnName => $column) {
            if ($column->isAutoUpdatingValue() && $column->isItExistsInDb()) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }

    /**
     * Save all values and requested relations to Db
     * Note: throws exception if used after begin() but before commit() or rollback()
     * @param array $relationsToSave
     * @return $this
     * @throws \UnexpectedValueException
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
     * @param array $columnsToSave
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\Core\DbException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     * @throws \UnexpectedValueException
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
            $columnsToSave[] = static::getPrimaryKeyColumnName();
        }
        $data = [];
        // collect values that are not from DB
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if ($column->isItExistsInDb()) {
                $valueObject = $this->getValueObject($column);
                if ($this->_hasValue($valueObject, true) && !$valueObject->isItFromDb()) {
                    $data[$columnName] = $this->getValue($column);
                }
            } else {
                unset($data[$columnName]);
            }
        }
        // collect auto updates
        $autoUpdatingColumns = $this->getAllAutoUpdatingColumns();
        foreach ($autoUpdatingColumns as $columnName) {
            $column = static::getColumn($columnName);
            if ($column->isItExistsInDb()) {
                $data[$columnName] = static::getColumn($columnName)->getAutoUpdateForAValue();
            }
        }
        $errors = $this->validateNewData($data, $columnsToSave);
        if (!empty($errors)) {
            throw new InvalidDataException($errors);
        }
        if (empty($data) && !$isUpdate) {
            $data = [
                static::getPrimaryKeyColumnName() => static::getTable()->getExpressionToSetDefaultValueForAColumn()
            ];
        }
        $errors = $this->beforeSave($columnsToSave, $data, $isUpdate);
        if (!empty($errors)) {
            throw new InvalidDataException($errors);
        }
        $updatedData = [];
        if (!empty($data)) {
            $table = static::getTable();
            $alreadyInTransaction = $table::inTransaction();
            if (!$alreadyInTransaction) {
                $table::beginTransaction();
            }
            if ($isUpdate) {
                try {
                    $updatedData = static::getTable()->update(
                        $data,
                        [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()],
                        true
                    );
                } catch (\PDOException $exc) {
                    if ($table::inTransaction()) {
                        $table::rollBackTransaction();
                    }
                    throw $exc;
                }
            } else {
                $updatedData = static::getTable()->insert($data, true);
            }
            if (!$alreadyInTransaction) {
                $table::commitTransaction();
            }
        }
        $this->updateValues($updatedData, true);
        // run column saving extenders
        foreach ($columnsToSave as $columnName) {
            call_user_func(
                static::getColumn($columnName)->getValueSavingExtender(),
                $this->getValueObject($columnName),
                $isUpdate,
                $updatedData,
                $this
            );
        }
        $this->afterSave(!$isUpdate);
    }

    /**
     * Called after all data collected and validated
     * @param array $columnsToSave
     * @param array $data
     * @param bool $isUpdate
     * @return array - errors
     */
    protected function beforeSave(array $columnsToSave, array $data, $isUpdate) {
        return [];
    }

    /**
     * For child classes
     * Called after successful save() and commit()
     * @param boolean $isCreated - true: new record was created; false: old record was updated
     */
    protected function afterSave($isCreated) {

    }

    /**
     * Validate a value
     * @param string $columnName
     * @param mixed $value
     * @param bool $isFromDb
     * @return array - errors
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     */
    static public function validateValue($columnName, $value, $isFromDb = false) {
        return static::getColumn($columnName)->validateValue($value, $isFromDb);
    }

    /**
     * Validate data
     * @param array $data
     * @param array $columnsNames - column names to validate. If col
     * @return array
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function validateNewData(array $data, array $columnsNames = []) {
        if (!count($columnsNames)) {
            $columnsNames = array_keys($data);
        }
        $errors = [];
        foreach ($columnsNames as $columnName) {
            $column = static::getColumn($columnName);
            $value = array_key_exists($columnName, $data) ? $data[$columnName] : null;
            $columnErrors = $column->validateValue($value, false);
            if (!empty($columnErrors)) {
                $errors[$columnName] = $columnErrors;
            }
        }
        return $errors;
    }

    /**
     * ave requested relations to DB
     * Note: this Record must exist in DB
     * @param array $relationsToSave
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\Core\DbException
     * @throws \UnexpectedValueException
     */
    public function saveRelations(array $relationsToSave = []) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException(
                'It is impossible to save related objects of a record that does not exist in DB'
            );
        }
        $relations = static::getRelations();
        $diff = array_diff($relationsToSave, array_keys($relations));
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$relationsToSave argument contains unknown relations: ' .implode(', ', $diff)
            );
        }
        foreach ($relationsToSave as $relationName) {
            if ($this->isRelatedRecordAttached($relationName)) {
                $record = $this->getRelatedRecord($relationName);
                if ($record instanceof DbRecord) {
                    $record->setValue(
                        $relations[$relationName]->getForeignColumnName(),
                        $this->getValue($relations[$relationName]->getLocalColumnName()),
                        false
                    );
                    $record->save();
                } else {
                    foreach ($record as $recordObj) {
                        $recordObj->setValue(
                            $relations[$relationName]->getForeignColumnName(),
                            $this->getValue($relations[$relationName]->getLocalColumnName()),
                            false
                        );
                        $recordObj->save();
                    }
                }
            }
        }
    }

    /**
     * Delete current Record from DB
     * Note: this Record must exist in DB
     * @param bool $resetAllValuesAfterDelete - true: will reset Record (default) | false: only primary key value will be reset
     * @param bool $deleteFiles - true: delete all attached files | false: do not delete attached files
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function delete($resetAllValuesAfterDelete = true, $deleteFiles = true) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Unable to delete record that does not exist in DB');
        } else {
            $this->beforeDelete();
            $table = static::getTable();
            $alreadyInTransaction = $table::inTransaction();
            if (!$alreadyInTransaction) {
                $table::beginTransaction();
            }
            try {
                $table->delete([static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]);
            } catch (\PDOException $exc) {
                if ($table::inTransaction()) {
                    $table::rollBackTransaction();
                }
                throw $exc;
            }
            $this->afterDelete(); //< transaction may be closed there
            if (!$alreadyInTransaction && $table::inTransaction()) {
                $table::commitTransaction();
            }
            foreach (static::getColumns() as $columnName => $column) {
                call_user_func(
                    $column->getValueDeleteExtender(),
                    $this->getValueObject($column),
                    $this,
                    $deleteFiles
                );
            }
        }
        // note: related objects delete must be managed only by database relations (foreign keys), not here
        if ($resetAllValuesAfterDelete) {
            $this->reset();
        } else {
            $this->resetValue(static::getPrimaryKeyColumn());
        }
        return $this;
    }

    /**
     * To terminate delete - throw exception
     */
    protected function beforeDelete() {

    }

    /**
     * Called after successful delete but before columns values resetted
     * (for child classes)
     */
    protected function afterDelete() {

    }

    /**
     * Get required values as array
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames
     *  - empty: do not add any relations
     *  - array: contains key-value or index-value pairs:
     *      key-value: key is relation name and value is array containing column names of related record to return
     *      index-value or value without key: value is relation name, will return all columns of the related record
     * @param bool $loadRelatedRecordsIfNotSet - true: read all missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @return array
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
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
        $fileColumns = static::getFileColumns();
        foreach ($columnsNames as $columnName) {
            $column = static::getColumn($columnName);
            if (array_key_exists($column->getName(), $fileColumns)) {
                if ($withFilesInfo) {
                    $data[$column->getName()] = $this->getValue($column, 'array');
                }
            } else if ($this->hasValue($column, true)) {
                $data[$columnName] = $this->getValue($column);
                if ($data[$columnName] instanceof DbExpr) {
                    $data[$columnName] = null;
                }
            } else {
                $data[$columnName] = null;
            }
        }
        foreach ($relatedRecordsNames as $relatedRecordName => $relatedRecordColumns) {
            if (is_int($relatedRecordName)) {
                $relatedRecordName = $relatedRecordColumns;
                $relatedRecordColumns = [];
            }
            if (!is_array($relatedRecordColumns)) {
                throw new \InvalidArgumentException(
                    "Columns list for relation '{$relatedRecordName}' must be an array. "
                        . gettype($relatedRecordName) . ' given.'
                );
            }
            $relatedRecord = $this->getRelatedRecord($relatedRecordName, $loadRelatedRecordsIfNotSet);
            if ($relatedRecord instanceof DbRecord) {
                $data[$relatedRecordName] = $withFilesInfo
                    ? $relatedRecord->toArray($relatedRecordColumns)
                    : $relatedRecord->toArrayWitoutFiles($relatedRecordColumns);
            } else {
                /** @var DbRecordsSet $relatedRecord*/
                $relatedRecord->enableDbRecordInstanceReuseDuringIteration();
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
     * Get required values as array but exclude file columns
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @return array
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
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
    public function getDefaults(array $columns = [], $ignoreColumnsThatDoNotExistInDB = true, $nullifyDbExprValues = true) {
        if (count($columns) === 0) {
            $columns = array_keys($this->values);
        }
        $values = array();
        foreach ($columns as $columnName) {
            $column = static::getColumn($columnName);
            if ($ignoreColumnsThatDoNotExistInDB && !$column->isItExistsInDb()) {
                continue;
            } else {
                $values[$columnName] = $this->getValueObject($column)->getDefaultValueOrNull();
                if ($nullifyDbExprValues && $values[$columnName] instanceof DbExpr) {
                    $values[$columnName] = null;
                }
            }
        }
        return $values;
    }

    /**
     * Return the current element
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function current() {
        $key = $this->key();
        return $key!== null ? $this->getValue($key) : null;
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function key() {
        if ($this->valid()) {
            return array_keys(static::getColumns())[$this->iteratorIdx];
        } else {
            return null;
        }
    }

    /**
     * Checks if current position is valid
     * @return boolean
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function valid() {
        return array_key_exists($this->iteratorIdx, array_keys(static::getColumns()));
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetExists($key) {
        return (
            static::hasColumn($key) && $this->hasValue($key)
            || static::hasRelation($key) && $this->isRelatedRecordAttached($key)
        );
    }

    /**
     * @param mixed $key - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if (static::hasRelation($key)) {
            return $this->getRelatedRecord($key);
        } else {
            if (!static::hasColumn($key) && preg_match('%^(.+)_as_()%is', $key, $parts)) {
                return $this->getValue($parts[1], $parts[2]);
            } else {
                return $this->getValue($key);
            }
        }
    }

    /**
     * @param mixed $key - column name or relation name
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     */
    public function offsetSet($key, $value) {
        if (static::hasRelation($key)) {
            $this->setRelatedRecord($key, $value, false);
        } else {
            $this->setValue($key, $value, false);
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
     * Count elements of an object
     * @return integer
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     */
    public function count() {
        return count(static::getColumns());
    }

    /**
     * @param $name - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
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
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     */
    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    /**
     * @param $name - column name or relation name
     * @return bool
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
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
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
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
        if (static::hasRelation($nameParts[1])) {
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
            if (!static::hasColumn($columnName)) {
                throw new \BadMethodCallException(
                    "Magic method '{$name}()' is not linked with any column or relation"
                );
            }
            $this->setValue($columnName, $value, $isFromDb);
        }
        return $this;
    }

}