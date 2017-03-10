<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\RecordNotFoundException;
use Swayok\Utils\StringUtils;

abstract class Record implements RecordInterface, \ArrayAccess, \Iterator, \Serializable {

    /**
     * @var RecordValue[]
     */
    protected $values = [];
    /**
     * @var Record[]|RecordsSet[]
     */
    protected $relatedRecords = [];
    /**
     * @var bool
     */
    protected $isCollectingUpdates = false;
    /**
     * Collected when value is updated during $this->isCollectingUpdates === true
     * @var RecordValue[]
     */
    protected $valuesBackup = [];
    /**
     * @var int
     */
    protected $iteratorIdx = 0;
    /**
     * @var bool
     */
    protected $trustDbDataMode = false;

    /**
     * Create new record with values from $data array
     * @param array $data
     * @param bool $isFromDb
     * @param bool $haltOnUnknownColumnNames
     * @return static
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
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
     * @return TableStructure
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     */
    static public function getTableStructure() {
        return static::getTable()->getStructure();
    }

    /**
     * @return Column[] - key = column name
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumns() {
        return static::getTableStructure()->getColumns();
    }

    /**
     * @return Column[] - key = column name
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @return Column
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasColumn($name) {
        return static::getTableStructure()->hasColumn($name);
    }

    /**
     * @return Column
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPrimaryKeyColumn() {
        return static::getTableStructure()->getPkColumn();
    }

    /**
     * @return string
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getPrimaryKeyColumnName() {
        return static::getPrimaryKeyColumn()->getName();
    }

    /**
     * @return Relation[]
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::getTableStructure()->getRelations();
    }

    /**
     * @param string $name
     * @return Relation
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasRelation($name) {
        return static::getTableStructure()->hasRelation($name);
    }

    /**
     * @return Column[]
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getFileColumns() {
        return static::getTableStructure()->getFileColumns();
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        return static::getTableStructure()->hasFileColumns();
    }

    /**
     * All values marked as "received from DB" will not be normalized and validated but record
     * will be not allowed to be saved to prevent possible issues.
     * This mode is designed to speed up DB data processing when you need to iterate over large number of records
     * where values are not intended to be modified and saved.
     * @return $this
     */
    public function enableTrustModeForDbData() {
        $this->trustDbDataMode = true;
        return $this;
    }

    /**
     * All values marked as "received from DB" will be normalized and validated (record is allowed to be saved)
     * @return $this
     */
    public function disableTrustModeForDbData() {
        $this->trustDbDataMode = false;
        return $this;
    }

    /**
     * Resets all values and related records
     * @return $this
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function reset() {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first'
            );
        }
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
     * @param Column $column
     * @return RecordValue
     */
    protected function createValueObject(Column $column) {
        return RecordValue::create($column, $this);
    }

    /**
     * @param string|Column $column
     * @return $this
     * @throws \PeskyORM\Exception\OrmException
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
     * @param string|Column $column
     * @return RecordValue
     * @throws \PeskyORM\Exception\OrmException
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
     * @param string|Column $column
     * @param null $format
     * @return mixed
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getValue($column, $format = null) {
        $column = $column instanceof Column ? $column : static::getColumn($column);
        return call_user_func($column->getValueGetter(), $this->getValueObject($column), $format);
    }

    /**
     * @param string $columnName
     * @param mixed $default
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getValueIfExistsInDb($columnName, $default = null) {
        return ($this->existsInDb() && isset($this->$columnName)) ? $this->$columnName : $default;
    }

    /**
     * @param string|Column $column
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getOldValue($column) {
        return $this->getValueObject($column)->getOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function hasOldValue($column) {
        return $this->getValueObject($column)->hasOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function isOldValueWasFromDb($column) {
        return $this->getValueObject($column)->isOldValueWasFromDb();
    }

    /**
     * Check if there is a value for $columnName
     * @param string|Column $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     */
    public function hasValue($column, $trueIfThereIsDefaultValue = false) {
        return $this->_hasValue($this->getValueObject($column), $trueIfThereIsDefaultValue);
    }

    /**
     * @param RecordValue $value
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return mixed
     */
    protected function _hasValue(RecordValue $value, $trueIfThereIsDefaultValue = false) {
        return call_user_func($value->getColumn()->getValueExistenceChecker(), $value, $trueIfThereIsDefaultValue);
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function isValueFromDb($column) {
        return $this->getValueObject($column)->isItFromDb();
    }

    /**
     * @param string|Column $column
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     */
    public function updateValue($column, $value, $isFromDb) {
        $valueContainer = $this->getValueObject($column);
        if (!$isFromDb && !$valueContainer->getColumn()->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "It is forbidden to modify or set value of a '{$valueContainer->getColumn()->getName()}' column"
            );
        }
        if ($this->isCollectingUpdates && $isFromDb) {
            throw new \BadMethodCallException("It is forbidden to set value with \$isFromDb === true after begin()");
        }
        $column = $column instanceof Column ? $column : static::getColumn($column);
        if ($column->isItPrimaryKey()) {
            if ($value === null) {
                return $this->unsetPrimaryKeyValue();
            } else if (!$isFromDb) {
                throw new \InvalidArgumentException('It is forbidden to set primary key value when $isFromDb === false');
            }
        }
        if ($isFromDb && !$column->isItPrimaryKey() && !$this->existsInDb()) {
            throw new \InvalidArgumentException(
                "Attempt to set a value for column [{$column->getName()}] with flag \$isFromDb === true while record does not exist in DB"
            );
        }
        if ($column->isItPrimaryKey() && $valueContainer->hasValue() && $valueContainer->isItFromDb()) {
            // backup pk value only if it was from db
            $prevPkValue = $valueContainer->getValue();
        }
        if ($this->isCollectingUpdates && !array_key_exists($column->getName(), $this->valuesBackup)) {
            $this->valuesBackup[$column->getName()] = clone $valueContainer;
        }
        call_user_func($column->getValueSetter(), $value, (bool) $isFromDb, $this->getValueObject($column), $this->trustDbDataMode);
        if (!$valueContainer->isValid()) {
            throw new InvalidDataException([$column->getName() => $valueContainer->getValidationErrors()]);
        }
        if (
            isset($prevPkValue)
            && (
                !$valueContainer->hasValue()
                || $prevPkValue !== $valueContainer->getValue()
            )
        ) {
            // this will trigger only when previus pk value (that was from db) was changed or removed
            $this->onPrimaryKeyChangeForRecordReceivedFromDb($prevPkValue);
        }
        return $this;
    }

    /**
     * @param string|Column $column
     * @return $this
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    public function unsetValue($column) {
        $oldValueObject = $this->getValueObject($column);
        if ($oldValueObject->hasValue()) {
            $column = $oldValueObject->getColumn();
            $this->values[$column->getName()] = $this->createValueObject($column);
            $this->values[$column->getName()]->setOldValue($oldValueObject);
            if ($column->getName() === static::getPrimaryKeyColumnName()) {
                $this->onPrimaryKeyChangeForRecordReceivedFromDb($oldValueObject->getValue());
            }
        }
        return $this;
    }

    /**
     * Unset primary key value
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function unsetPrimaryKeyValue() {
        return $this->unsetValue(static::getPrimaryKeyColumnName());
    }

    /**
     * Erase related records when primary key received from db was changed or removed + mark all values as
     * received not from db
     * @param string|int|float $prevPkValue
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    protected function onPrimaryKeyChangeForRecordReceivedFromDb($prevPkValue) {
        $this->relatedRecords = [];
        $pkColName = static::getPrimaryKeyColumnName();
        foreach ($this->values as $colName => $valueContainer) {
            if ($colName !== $pkColName && $valueContainer->hasValue()) {
                $valueContainer->setIsFromDb(false);
            }
        }
    }

    /**
     * @return mixed
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getPrimaryKeyValue() {
        return $this->getValue(static::getPrimaryKeyColumn());
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @param array|Record|RecordsArray $relatedRecord
     * @param bool|null $isFromDb - true: marks values as loaded from DB | null: autodetect
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     */
    public function updateRelatedRecord($relationName, $relatedRecord, $isFromDb = null, $haltOnUnknownColumnNames = true) {
        $relation = static::getRelation($relationName);
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === Relation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = RecordsSet::createFromArray($relationTable, $relatedRecord, $isFromDb);
            } else if (!($relatedRecord instanceof RecordsArray)) {
                throw new \InvalidArgumentException(
                    '$relatedRecord argument for HAS MANY relation must be array or instance of ' . RecordsArray::class
                );
            }
        } else if (is_array($relatedRecord)) {
            if ($isFromDb === null) {
                $pkName = $relationTable->getPkColumnName();
                $isFromDb = array_key_exists($pkName, $relatedRecord) && $relatedRecord[$pkName] !== null;
            }
            if (!empty($relatedRecord)) {
                $relatedRecord = $relationTable
                    ->newRecord()
                    ->fromData($relatedRecord, $isFromDb, $haltOnUnknownColumnNames);
            } else {
                $relatedRecord = $relationTable->newRecord();
            }
        } else if ($relatedRecord instanceof Record) {
            if ($relatedRecord::getTable()->getName() !== $relationTable) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of Record class for the '{$relationTable->getName()}' DB table"
                );
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of Record class for the '{$relationTable->getName()}' DB table"
            );
        }
        $this->relatedRecords[$relationName] = $relatedRecord;
        return $this;
    }

    /**
     * Remove related record
     * @param string $name
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function unsetRelatedRecord($name) {
        static::getRelation($name);
        unset($this->relatedRecords[$name]);
    }

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return Record|RecordsSet
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function readRelatedRecord($relationName) {
        $relation = static::getRelation($relationName);
        if (!$this->isRelatedRecordCanBeRead($relation)) {
            throw new \BadMethodCallException(
                "Record has not enough data to read related record '{$relationName}'. "
                    . "You need to provide a value for '{$relation->getLocalColumnName()}' column."
            );
        }
        $relatedTable = $relation->getForeignTable();
        $conditions = array_merge(
            [$relation->getForeignColumnName() => $this->getValue($relation->getLocalColumnName())],
            $relation->getAdditionalJoinConditions()
        );
        if ($relation->getType() === Relation::HAS_MANY) {
            $this->relatedRecords[$relationName] = $relatedTable->select('*', $conditions, function (OrmSelect $select) use ($relatedTable) {
                $select->orderBy($relatedTable->getPkColumnName(), true);
            });
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
     * Testif there are enough data to load related record
     * @param string|Relation $relationName
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function isRelatedRecordCanBeRead($relationName) {
        $relation = $relationName instanceof Relation
            ? $relationName
            : static::getRelation($relationName);
        return $this->hasValue($relation->getLocalColumnName());
    }

    /**
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        if (empty($columns)) {
            $columns = array_keys(static::getColumnsThatExistInDb());
        } else {
            $columns[] = static::getPrimaryKeyColumnName();
        }
        $columnsFromRelations = [];
        $hasManyRelations = [];
        /** @var Relation[] $relations */
        $relations = [];
        foreach ($readRelatedRecords as $relationName => $realtionColumns) {
            if (is_int($relationName)) {
                $relationName = $realtionColumns;
                $realtionColumns = ['*'];
            }
            $relations[$relationName] = static::getRelation($relationName);
            if (static::getRelation($relationName)->getType() === Relation::HAS_MANY) {
                $hasManyRelations[] = $relationName;
            } else {
                $columnsFromRelations[$relationName] = (array)$realtionColumns;
            }
        }
        $record = static::getTable()->selectOne(
            array_merge(array_unique($columns), $columnsFromRelations),
            $conditionsAndOptions
        );
        if (empty($record)) {
            $this->reset();
        } else {
            // clear not existing relations
            foreach ($columnsFromRelations as $relationName => $unused) {
                $fkColName = $relations[$relationName]->getForeignColumnName();
                if (
                    !array_key_exists($relationName, $record)
                    || !array_key_exists($fkColName, $record[$relationName])
                    || $record[$relationName][$fkColName] === null
                ) {
                    $record[$relationName] = [];
                }
            }
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\RecordNotFoundException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
        $data = static::getTable()->selectOne(
            empty($columns) ? '*' : $columns,
            [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]
        );
        if (empty($data)) {
            throw new RecordNotFoundException(
                "Record with primary key '{$this->getPrimaryKeyValue()}' was not found in DB"
            );
        }
        $this->updateValues($data, true);
        return $this;
    }

    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        $pkColName = static::getPrimaryKeyColumnName();
        if ($isFromDb && !$this->existsInDb()) {
            // first set pk column value
            if (array_key_exists($pkColName, $data)) {
                $this->updateValue($pkColName, $data[$pkColName], true);
                unset($data[$pkColName]);
            } else {
                throw new \InvalidArgumentException(
                    'Values update failed: record does not exist in DB while $isFromDb argument is \'true\'.'
                    . ' Possibly you\'ve missed a primary key value in $data argument.'
                );
            }
        }
        foreach ($data as $columnNameOrRelationName => $value) {
            if (static::hasColumn($columnNameOrRelationName)) {
                $this->updateValue($columnNameOrRelationName, $value, $isFromDb);
            } else if (static::hasRelation($columnNameOrRelationName)) {
                $this->updateRelatedRecord(
                    $columnNameOrRelationName,
                    $value,
                    $isFromDb ? null : false,
                    $haltOnUnknownColumnNames
                );
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\RecordNotFoundException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function commit($relationsToSave = []) {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to commit changed values: changes collecting was not started'
            );
        }
        $columnsToSave = array_keys($this->valuesBackup);
        $this->cleanUpdates();
        $this->saveToDb($columnsToSave);
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave);
        }
        return $this;
    }

    /**
     * Get names of all columns that can be saved to db
     * @return array
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllColumnsWithAutoUpdatingValues() {
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
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \UnexpectedValueException
     */
    protected function saveToDb(array $columnsToSave = []) {
        if ($this->trustDbDataMode) {
            throw new \BadMethodCallException('Saving is not alowed when trusted mode for DB data is enabled');
        }
        if (empty($columnsToSave)) {
            // nothing to save
            return;
        }
        $diff = array_diff($columnsToSave, array_keys($this->values));
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains unknown columns: ' . implode(', ', $diff)
            );
        }
        $diff = array_diff($columnsToSave, $this->getAllColumnsWithUpdatableValues());
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains columns that cannot be saved to DB: '  . implode(', ', $diff)
            );
        }
        $isUpdate = $this->existsInDb();
        $data = $this->collectValuesForSave($columnsToSave, $isUpdate);
        $updatedData = [];
        if (!empty($data)) {
            $errors = $this->validateNewData($data, $columnsToSave, $isUpdate);
            if (!empty($errors)) {
                throw new InvalidDataException($errors);
            }
            $errors = $this->beforeSave($columnsToSave, $data, $isUpdate);
            if (!empty($errors)) {
                throw new InvalidDataException($errors);
            }

            if (!$this->performDataSave($isUpdate, $data)) {
                return;
            }
        }
        // run column saving extenders
        $this->runColumnSavingExtenders($columnsToSave, $data, $updatedData, $isUpdate);
        $this->cleanCacheAfterSave(!$isUpdate);
        $this->afterSave(!$isUpdate);
    }

    /**
     * @param bool $isUpdate
     * @param array $data
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    protected function performDataSave($isUpdate, array $data) {
        $table = static::getTable();
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            if ($isUpdate) {
                /** @var array $updatedData */
                $updatedData = $table->update(
                    $data,
                    [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()],
                    true
                );
                if (count($updatedData)) {
                    $updatedData = $updatedData[0];
                    $unknownColumns = array_diff_key($updatedData, static::getColumns());
                    if (count($unknownColumns) > 0) {
                        throw new \UnexpectedValueException(
                            'Database table "' . static::getTableStructure()->getTableName()
                                . '" contains columns that are not described in ' . get_class(static::getTableStructure())
                                . '. Unknown columns: "' . implode('", "', array_keys($unknownColumns)) . '"'
                        );
                    }
                    $this->updateValues($updatedData, true);
                } else {
                    // this means that record does not exist anymore
                    $this->reset();
                    return false;
                }
            } else {
                $this->updateValues($table->insert($data, true), true);
            }
        } catch (\Exception $exc) {
            if ($table::inTransaction()) {
                $table::rollBackTransaction();
            }
            throw $exc;
        }
        if (!$alreadyInTransaction) {
            $table::commitTransaction();
        }
        return true;
    }

    /**
     * @param array $columnsToSave
     * @param bool $isUpdate
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    protected function collectValuesForSave(array $columnsToSave, $isUpdate) {
        $data = [];
        // collect values that are not from DB
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if ($column->isItExistsInDb() && !$column->isItPrimaryKey()) {
                $valueObject = $this->getValueObject($column);
                if ($this->_hasValue($valueObject, true) && !$valueObject->isItFromDb()) {
                    $data[$columnName] = $this->getValue($column);
                }
            }
        }
        if (count($data) === 0) {
            return [];
        }
        // collect auto updates
        $autoUpdatingColumns = $this->getAllColumnsWithAutoUpdatingValues();
        foreach ($autoUpdatingColumns as $columnName) {
            $data[$columnName] = static::getColumn($columnName)->getAutoUpdateForAValue();
        }
        // set pk value
        $data[static::getPrimaryKeyColumnName()] = $isUpdate
            ? $this->getPrimaryKeyValue()
            : static::getTable()->getExpressionToSetDefaultValueForAColumn();
        return $data;
    }

    /**
     * @param array $columnsToSave
     * @param array $dataSavedToDb
     * @param array $updatesReceivedFromDb
     * @param $isUpdate
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    protected function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, $isUpdate) {
        $updatedColumns = array_merge(
            array_keys($dataSavedToDb),
            array_intersect(array_keys(static::getColumnsThatDoNotExistInDb()), $columnsToSave)
        );
        foreach ($updatedColumns as $columnName) {
            $valueObject = $this->getValueObject($columnName);
            call_user_func(
                static::getColumn($columnName)->getValueSavingExtender(),
                $valueObject,
                $isUpdate,
                $updatesReceivedFromDb
            );
            $valueObject->pullDataForSavingExtender();
        }
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
     * @param bool $isCreated - true: new record was created; false: old record was updated
     */
    protected function afterSave($isCreated) {

    }

    /**
     * Clean cache related to this record after saving it's data to DB
     * @param bool $isCreated
     */
    protected function cleanCacheAfterSave($isCreated) {

    }

    /**
     * Validate a value
     * @param string|Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return array - errors
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    static public function validateValue($column, $value, $isFromDb = false) {
        if (!($column instanceof Column)) {
            $column = static::getColumn($column);
        }
        return $column->validateValue($value, $isFromDb);
    }

    /**
     * Validate data
     * @param array $data
     * @param array $columnsNames - column names to validate. If col
     * @param bool $isUpdate
     * @return array
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function validateNewData(array $data, array $columnsNames = [], $isUpdate = false) {
        if (!count($columnsNames)) {
            $columnsNames = array_keys($data);
        }
        $errors = [];
        foreach ($columnsNames as $columnName) {
            $column = static::getColumn($columnName);
            if (array_key_exists($columnName, $data)) {
                $value = $data[$columnName];
            } else if ($isUpdate) {
                continue;
            } else {
                $value = null;
            }
            $columnErrors = $this->validateValue($column, $value, false);
            if (!empty($columnErrors)) {
                $errors[$columnName] = $columnErrors;
            }
        }
        return $errors;
    }

    /**
     * Save requested relations to DB
     * Note: this Record must exist in DB
     * @param array $relationsToSave
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\DbException
     * @throws \UnexpectedValueException
     */
    public function saveRelations(array $relationsToSave = []) {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException(
                'It is impossible to save related objects of a record that does not exist in DB'
            );
        }
        $relations = static::getRelations();
        if (count($relationsToSave) === 1 && $relationsToSave[0] === '*') {
            $relationsToSave = array_keys($relations);
        } else {
            $diff = array_diff($relationsToSave, array_keys($relations));
            if (count($diff)) {
                throw new \InvalidArgumentException(
                    '$relationsToSave argument contains unknown relations: ' . implode(', ', $diff)
                );
            }
        }
        foreach ($relationsToSave as $relationName) {
            if ($this->isRelatedRecordAttached($relationName)) {
                $relatedRecord = $this->getRelatedRecord($relationName);
                if ($relations[$relationName]->getType() === $relations[$relationName]::HAS_ONE) {
                    $relatedRecord->updateValue(
                        $relations[$relationName]->getForeignColumnName(),
                        $this->getValue($relations[$relationName]->getLocalColumnName()),
                        false
                    );
                    $relatedRecord->save();
                } else if ($relations[$relationName]->getType() === $relations[$relationName]::BELONGS_TO) {
                    $relatedRecord->save();
                    $this->updateValue(
                        $relations[$relationName]->getLocalColumnName(),
                        $relatedRecord->getValue($relations[$relationName]->getForeignColumnName()),
                        false
                    );
                    $this->saveToDb([$relations[$relationName]->getLocalColumnName()]);
                } else {
                    foreach ($relatedRecord as $recordObj) {
                        $recordObj->updateValue(
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function delete($resetAllValuesAfterDelete = true, $deleteFiles = true) {
        if (!$this->hasPrimaryKeyValue()) {
            throw new \BadMethodCallException('It is impossible to delete record has no primary key value');
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
            $this->cleanCacheAfterDelete();
            if (!$alreadyInTransaction && $table::inTransaction()) {
                $table::commitTransaction();
            }
            foreach (static::getColumns() as $columnName => $column) {
                call_user_func(
                    $column->getValueDeleteExtender(),
                    $this->getValueObject($column),
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
     * To clean cached data related to record
     */
    protected function cleanCacheAfterDelete() {

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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        $loadRelatedRecordsIfNotSet = false,
        $withFilesInfo = true
    ) {
        if (empty($columnsNames) || (count($columnsNames) === 1 && $columnsNames[0] === '*')) {
            $columnsNames = array_keys($this->values);
        }
        $data = [];
        foreach ($columnsNames as $columnName) {
            $data[$columnName] = $this->getColumnValueForToArray($columnName, !$withFilesInfo, $notSet);
            if ($notSet) {
                unset($data[$columnName]);
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
            if ($relatedRecord instanceof Record) {
                $data[$relatedRecordName] = $withFilesInfo
                    ? $relatedRecord->toArray($relatedRecordColumns)
                    : $relatedRecord->toArrayWithoutFiles($relatedRecordColumns);
            } else {
                /** @var RecordsSet $relatedRecord*/
                $relatedRecord->enableDbRecordInstanceReuseDuringIteration();
                $data[$relatedRecordName] = [];
                foreach ($relatedRecord as $relRecord) {
                    $data[$relatedRecordName][] = $withFilesInfo
                        ? $relRecord->toArray($relatedRecordColumns)
                        : $relRecord->toArrayWithoutFiles($relatedRecordColumns);
                }
            }
        }
        return $data;
    }

    /**
     * Get column value if it is set or null in any other cases
     * @param string $columnName
     * @param bool $returnNullForFiles - false: return file information for file column | true: return null for file column
     * @param bool $notSet
     * @return mixed
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getColumnValueForToArray($columnName, $returnNullForFiles = false, &$notSet = null) {
        $column = static::getColumn($columnName);
        $notSet = true;
        if ($column->isPrivateValue()) {
            return null;
        } else if ($column->isItAFile()) {
            if (!$returnNullForFiles && $this->hasValue($column, false)) {
                $notSet = false;
                return $this->getValue($column, 'array');
            }
        } else {
            if ($this->existsInDb()) {
                if ($this->hasValue($column, false)) {
                    $notSet = false;
                    $val = $this->getValue($column);
                    return ($val instanceof DbExpr) ? null : $val;
                }
            } else {
                $notSet = false; //< there is always a value when record does not eist in DB
                // if default value not provided directly it is considered to be null when record does not exist is DB
                if ($this->hasValue($column, true)) {
                    $val = $this->getValue($column);
                    return ($val instanceof DbExpr) ? null : $val;
                }
            }
        }
        return null;
    }

    /**
     * Get required values as array but exclude file columns
     * @param array $columnsNames - empty: return all columns
     * @param array $relatedRecordsNames - empty: do not add any relations
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     */
    public function toArrayWithoutFiles(
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function current() {
        $key = $this->key();
        if ($key === null) {
            return null;
        }
        return $key !== null ? $this->getColumnValueForToArray($key) : null;
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetExists($key) {
        if (static::hasRelation($key)) {
            if (!$this->isRelatedRecordCanBeRead($key)) {
                return false;
            }
            $record = $this->getRelatedRecord($key, true);
            return $record instanceof Record ? $record->existsInDb() : $record->count();
        } else if (static::hasColumn($key)) {
            return $this->hasValue($key);
        } else {
            static::getColumn($key); //< to throw column not exist exception
            return false;
        }
    }

    /**
     * @param mixed $key - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if (static::hasRelation($key)) {
            return $this->getRelatedRecord($key, true);
        } else {
            if (!static::hasColumn($key) && preg_match('%^(.+)_as_(.*)$%is', $key, $parts)) {
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     */
    public function offsetSet($key, $value) {
        if (static::hasRelation($key)) {
            $this->updateRelatedRecord($key, $value, null);
        } else {
            $this->updateValue($key, $value, $key === static::getPrimaryKeyColumnName());
        }
    }

    /**
     * @param string $key
     * @return Record
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function offsetUnset($key) {
        if (static::hasRelation($key)) {
            return $this->unsetRelatedRecord($key);
        } else {
            return $this->unsetValue($key);
        }
    }

    /**
     * @param $name - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     */
    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    /**
     * @param $name - column name or relation name
     * @return bool
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __isset($name) {
        return $this->offsetExists($name);
    }

    /**
     * @param string $name - column name or relation name
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function __unset($name) {
        $this->offsetUnset($name);
    }

    /**
     * Supports only methods starting with 'set' and ending with column name or relation name
     * @param string $name - something like 'setColumnName' or 'setRelationName'
     * @param array $arguments - 1 required, 2 accepted. 1st - value, 2nd - $isFromDb
     * @return $this
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __call($name, array $arguments) {
        $isValidName = preg_match('%^set([A-Z][a-zA-Z0-9]*)$%', $name, $nameParts);
        if (!$isValidName) {
            throw new \BadMethodCallException(
                "Magic method '{$name}(\$value, \$isFromDb = false)' is forbidden. You can magically call only methods starting with 'set', for example: setId(1)"
            );
        } else if (count($arguments) > 2) {
            throw new \InvalidArgumentException(
                "Magic method '{$name}(\$value, \$isFromDb = false)' accepts only 2 arguments, but " . count($arguments) . ' arguments passed'
            );
        } else if (array_key_exists(1, $arguments) && !is_bool($arguments[1])) {
            throw new \InvalidArgumentException(
                "2nd argument for magic method '{$name}(\$value, \$isFromDb = false)' must be a boolean and reflects if value received from DB"
            );
        }
        $value = $arguments[0];
        if (static::hasRelation($nameParts[1])) {
            if (
                (
                    !is_array($value)
                    && !is_object($value)
                )
                || (
                    is_object($value)
                    && !($value instanceof Record)
                    && !($value instanceof RecordsSet)
                )
            ) {
                throw new \InvalidArgumentException(
                    "1st argument for magic method '{$name}(\$value, \$isFromDb = false)' must be an array or instance of Record class or RecordsSet class"
                );
            }
            $isFromDb = array_key_exists(1, $arguments) ? (bool)$arguments[1] : null;
            $this->updateRelatedRecord($nameParts[1], $value, $isFromDb);
        } else {
            $columnName = StringUtils::underscore($nameParts[1]);
            if (!static::hasColumn($columnName)) {
                throw new \BadMethodCallException(
                    "Magic method '{$name}(\$value, \$isFromDb = false)' is not linked with any column or relation"
                );
            }
            $isFromDb = array_key_exists(1, $arguments)
                ? (bool)$arguments[1]
                : $columnName === static::getPrimaryKeyColumnName(); //< make pk key be from DB by default or it will crash
            $this->updateValue($columnName, $value, $isFromDb);
        }
        return $this;
    }

    /**
     * String representation of object
     * Note: it does not save relations to prevent infinite loops
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize() {
        $data = [];
        foreach ($this->values as $name => $value) {
            $data[$name] = $value->serialize();
        }
        return json_encode($data);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @since 5.1.0
     */
    public function unserialize($serialized) {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        $this->reset();
        /** @var array $data */
        foreach ($data as $name => $value) {
            $this->values[$name]->unserialize($value);
        }
    }

}