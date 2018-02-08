<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\RecordNotFoundException;
use Swayok\Utils\StringUtils;

/**
 * @method static Table getTable()
 */
abstract class Record implements RecordInterface, \ArrayAccess, \Iterator, \Serializable {

    /**
     * @var TableStructureInterface[]
     */
    private static $tableStructures = [];
    /**
     * @var array
     */
    private static $columns = [];

    /**
     * @var RecordValue[]
     */
    protected $values = [];
    /**
     * @var Record[]|RecordInterface[]|RecordsSet[]
     */
    protected $relatedRecords = [];
    /**
     * @var null|bool
     */
    private $existsInDb = null;
    /**
     * @var null
     */
    private $existsInDbReally = null;
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
     * @var bool
     */
    protected $isReadOnly = false;
    /**
     * @var array
     */
    protected $readOnlyData = [];

    /**
     * Create new record with values from $data array
     * @param array $data
     * @param bool $isFromDb
     * @param bool $haltOnUnknownColumnNames
     * @return static
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
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
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\Exception\OrmException
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
     * Create new empty record with enabled TrustModeForDbData
     * @return static
     */
    static public function newEmptyRecordForTrustedDbData() {
        $record = static::newEmptyRecord();
        $record->enableTrustModeForDbData();
        return $record;
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
     * @return TableStructure|TableStructureInterface
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function getTableStructure() {
        if (!isset(self::$tableStructures[static::class])) {
            self::$tableStructures[static::class] = static::getTable()->getStructure();
        }
        return self::$tableStructures[static::class];
    }

    /**
     * @return Column[] - key = column name
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumns() {
        return self::getCachedColumnsOrRelations();
    }

    /**
     * @return Column[] - key = column name
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatExistInDb() {
        return self::getCachedColumnsOrRelations('db_columns');
    }

    /**
     * @return Column[] - key = column name
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getColumnsThatDoNotExistInDb() {
        return self::getCachedColumnsOrRelations('not_db_columns');
    }

    /**
     * @param string $key
     * @return Column[]|Column|Relation[]
     */
    static private function getCachedColumnsOrRelations($key = 'columns') {
        // significantly decreases execution time on heavy ORM usage (proved by profilig with xdebug)
        if (!isset(self::$columns[static::class])) {
            $tableStructure = static::getTableStructure();
            self::$columns[static::class] = [
                'columns' => $tableStructure::getColumns(),
                'db_columns' => $tableStructure::getColumnsThatExistInDb(),
                'not_db_columns' => $tableStructure::getColumnsThatDoNotExistInDb(),
                'file_columns' => $tableStructure::getFileColumns(),
                'pk_column' => $tableStructure::getPkColumn(),
                'relations' => $tableStructure::getRelations(),
            ];
        }
        return self::$columns[static::class][$key];
    }

    /**
     * @param string $name
     * @return Column
     * @throws \InvalidArgumentException
     */
    static public function getColumn($name) {
        $columns = static::getColumns();
        if (!isset($columns[$name])) {
            throw new \InvalidArgumentException(
                "There is no column '$name' in " . get_class(static::getTableStructure())
            );
        }
        return $columns[$name];
    }

    /**
     * @param string $name
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasColumn($name) {
        return isset(static::getCachedColumnsOrRelations()[$name]);
    }

    /**
     * @return Column
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getPrimaryKeyColumn() {
        return static::getCachedColumnsOrRelations('pk_column');
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getPrimaryKeyColumnName() {
        return static::getPrimaryKeyColumn()->getName();
    }

    /**
     * @return Relation[]
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelations() {
        return static::getCachedColumnsOrRelations('relations');
    }

    /**
     * @param string $name
     * @return Relation
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function getRelation($name) {
        $relations = static::getRelations();
        if (!isset($relations[$name])) {
            throw new \InvalidArgumentException(
                "There is no relation '$name' in " . get_class(static::getTableStructure())
            );
        }
        return $relations[$name];
    }

    /**
     * @param string $name
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function hasRelation($name) {
        return isset(static::getRelations()[$name]);
    }

    /**
     * @return Column[]
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getFileColumns() {
        return static::getCachedColumnsOrRelations('file_columns');
    }

    /**
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function hasFileColumns() {
        return count(static::getFileColumns()) > 0;
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
        $this->readOnlyData = [];
        $this->relatedRecords = [];
        $this->iteratorIdx = 0;
        $this->cleanUpdates();
        return $this;
    }

    /**
     * @param Column $column
     * @return RecordValue
     */
    protected function createValueObject(Column $column) {
        return new RecordValue($column, $this);
    }

    /**
     * @param string|Column $column
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function resetValue($column) {
        unset($this->values[is_string($column) ? $column : $column->getName()]);
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
     * @param string|Column $colNameOrConfig
     * @return RecordValue
     */
    protected function getValueContainer($colNameOrConfig) {
        return is_string($colNameOrConfig)
            ? $this->getValueContainerByColumnName($colNameOrConfig)
            : $this->getValueContainerByColumnConfig($colNameOrConfig);
    }

    /**
     * Warning: do not use it to get/set/check value!
     * @param string $colName
     * @return RecordValue
     */
    protected function getValueContainerByColumnName($colName) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode.');
        }
        if (!isset($this->values[$colName])) {
            $this->values[$colName] = $this->createValueObject(static::getColumn($colName));
        }
        return $this->values[$colName];
    }

    /**
     * Warning: do not use it to get/set/check value!
     * @param Column $column
     * @return RecordValue
     */
    protected function getValueContainerByColumnConfig(Column $column) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode.');
        }
        $colName = $column->getName();
        if (!isset($this->values[$colName])) {
            $this->values[$colName] = $this->createValueObject($column);
        }
        return $this->values[$colName];
    }

    /**
     * @param string|Column $column
     * @param null $format
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getValue($column, $format = null) {
        return $this->_getValue(is_string($column) ? static::getColumn($column) : $column, $format);
    }

    /**
     * @param Column $column
     * @param null|string $format
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function _getValue(Column $column, $format) {
        if ($this->isReadOnly()) {
            return array_key_exists($column->getName(), $this->readOnlyData)
                ? $this->readOnlyData[$column->getName()]
                : null;
        } else {
            return call_user_func(
                $column->getValueGetter(),
                $this->getValueContainerByColumnConfig($column),
                $format
            );
        }
    }

    /**
     * @param string $columnName
     * @param mixed $default
     * @return mixed
     * @throws \UnexpectedValueException
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getOldValue($column) {
        return $this->getValueContainer($column)->getOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function hasOldValue($column) {
        return $this->getValueContainer($column)->hasOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function isOldValueWasFromDb($column) {
        return $this->getValueContainer($column)->isOldValueWasFromDb();
    }

    /**
     * Check if there is a value for $columnName
     * @param string|Column $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function hasValue($column, $trueIfThereIsDefaultValue = false) {
        return $this->_hasValue(is_string($column) ? static::getColumn($column) : $column, $trueIfThereIsDefaultValue);
    }

    /**
     * @param Column $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return mixed
     */
    protected function _hasValue(Column $column, $trueIfThereIsDefaultValue) {
        if ($this->isReadOnly()) {
            return array_key_exists($column->getName(), $this->readOnlyData);
        } else {
            return call_user_func(
                $column->getValueExistenceChecker(),
                $this->getValueContainerByColumnConfig($column),
                $trueIfThereIsDefaultValue
            );
        }
    }

    /**
     * @param string|Column $column
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function isValueFromDb($column) {
        return $this->getValueContainer($column)->isItFromDb();
    }

    /**
     * @param string|Column $column
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function updateValue($column, $value, $isFromDb) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }
        if (is_string($column)) {
            $column = static::getColumn($column);
        }
        return $this->_updateValue($column, $value, $isFromDb);
    }

    /**
     * @param string|Column $column
     * @param mixed $value
     * @param boolean $isFromDb
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function _updateValue(Column $column, $value, $isFromDb) {
        $valueContainer = $this->getValueContainerByColumnConfig($column);
        if (!$isFromDb && !$column->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "It is forbidden to modify or set value of a '{$valueContainer->getColumn()->getName()}' column"
            );
        }
        if ($this->isCollectingUpdates && $isFromDb) {
            throw new \BadMethodCallException('It is forbidden to set value with $isFromDb === true after begin()');
        }

        if ($column->isItPrimaryKey()) {
            if ($value === null) {
                return $this->unsetPrimaryKeyValue();
            } else if (!$isFromDb) {
                throw new \InvalidArgumentException('It is forbidden to set primary key value when $isFromDb === false');
            }
            $this->existsInDb = true;
            $this->existsInDbReally = null;
        } else if ($isFromDb && !$this->existsInDb()) {
            throw new \InvalidArgumentException(
                "Attempt to set a value for column [{$column->getName()}] with flag \$isFromDb === true while record does not exist in DB"
            );
        }
        $colName = $column->getName();
        $prevPkValue = null;
        // backup existing pk value
        if ($column->isItPrimaryKey() && $valueContainer->hasValue() /*&& $valueContainer->isItFromDb()*/) {
            $prevPkValue = $valueContainer->getValue();
        }
        if ($this->isCollectingUpdates && !isset($this->valuesBackup[$colName])) {
            $this->valuesBackup[$colName] = clone $valueContainer;
        }
        call_user_func($column->getValueSetter(), $value, (bool)$isFromDb, $valueContainer, $this->trustDbDataMode);
        if (!$valueContainer->isValid()) {
            throw new InvalidDataException([$colName => $valueContainer->getValidationErrors()]);
        }
        if (
            $prevPkValue !== null
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    public function unsetValue($column) {
        $oldValueObject = $this->getValueContainer($column);
        if ($oldValueObject->hasValue()) {
            $column = $oldValueObject->getColumn();
            $colName = $column->getName();
            $this->values[$colName] = $this->createValueObject($column);
            $this->values[$colName]->setOldValue($oldValueObject);
            if ($column->isItPrimaryKey()) {
                $this->onPrimaryKeyChangeForRecordReceivedFromDb($oldValueObject->getValue());
            }
        }
        return $this;
    }

    /**
     * Unset primary key value
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function unsetPrimaryKeyValue() {
        $this->existsInDb = false;
        $this->existsInDbReally = false;
        return $this->unsetValue(static::getPrimaryKeyColumn());
    }

    /**
     * Erase related records when primary key received from db was changed or removed + mark all values as
     * received not from db
     * @param string|int|float $prevPkValue
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function onPrimaryKeyChangeForRecordReceivedFromDb($prevPkValue) {
        $this->relatedRecords = [];
        $this->existsInDb = null;
        $this->existsInDbReally = null;
        $pkColName = static::getPrimaryKeyColumnName();
        foreach ($this->values as $colName => $valueContainer) {
            if ($colName !== $pkColName && $valueContainer->hasValue()) {
                $valueContainer->setIsFromDb(false);
            }
        }
    }

    /**
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getPrimaryKeyValue() {
        return $this->_getValue(static::getPrimaryKeyColumn(), null);
    }

    /**
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function hasPrimaryKeyValue() {
        return $this->_hasValue(static::getPrimaryKeyColumn(), false);
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function existsInDb($useDbQuery = false) {
        if ($useDbQuery) {
            if ($this->existsInDbReally === null) {
                $this->existsInDb = $this->existsInDbReally = (
                    $this->_hasValue(static::getPrimaryKeyColumn(), false)
                    && $this->_existsInDbViaQuery()
                );
            }
            return $this->existsInDbReally;
        } else {
            if ($this->existsInDb === null) {
                $this->existsInDb = (
                    $this->_hasValue(static::getPrimaryKeyColumn(), false)
        //            && $this->getValueContainerByColumnConfig($pkColumn)->isItFromDb() //< pk cannot be not from db
                    && (!$useDbQuery || $this->_existsInDbViaQuery())
                );
            }
            return $this->existsInDb;
        }
    }

    /**
     * Check if current Record exists in DB using DB query
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     */
    protected function _existsInDbViaQuery() {
        return static::getTable()->hasMatchingRecord([
            static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()
        ]);
    }

    /**
     * @param string|Relation $relationName
     * @param array|Record|RecordsArray $relatedRecord
     * @param bool|null $isFromDb - true: marks values as loaded from DB | null: autodetect
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws InvalidDataException
     */
    public function updateRelatedRecord($relationName, $relatedRecord, $isFromDb = null, $haltOnUnknownColumnNames = true) {
        /** @var Relation $relation */
        $relation = is_string($relationName) ? static::getRelation($relationName) : $relationName;
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === Relation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = RecordsSet::createFromArray($relationTable, $relatedRecord, $isFromDb, $this->trustDbDataMode);
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
            $data = $relatedRecord;
            $relatedRecord = $relationTable->newRecord();
            if ($this->trustDbDataMode) {
                $relatedRecord->enableTrustModeForDbData();
            }
            if (!empty($data)) {
                $relatedRecord->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
            }
        } else if ($relatedRecord instanceof self) {
            if ($relatedRecord::getTable()->getName() !== $relationTable) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of Record class for the '{$relationTable->getName()}' DB table"
                );
            }
            if ($this->trustDbDataMode) {
                $relatedRecord->enableTrustModeForDbData();
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of Record class for the '{$relationTable->getName()}' DB table"
            );
        }
        $this->relatedRecords[$relation->getName()] = $relatedRecord;
        return $this;
    }

    /**
     * Remove related record
     * @param string $name
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @return $this
     */
    public function unsetRelatedRecord($name) {
        static::getRelation($name);
        unset($this->relatedRecords[$name]);
        return $this;
    }

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return Record|RecordsSet
     * @throws \PDOException
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
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function readRelatedRecord($relationName) {
        $relation = static::getRelation($relationName);
        if (!$this->isRelatedRecordCanBeRead($relation)) {
            throw new \BadMethodCallException(
                "Record has not enough data to read related record '{$relationName}'. "
                    . "You need to provide a value for '{$relation->getLocalColumnName()}' column."
            );
        }
        $fkValue = $this->getValue($relation->getLocalColumnName());
        $relatedTable = $relation->getForeignTable();
        if ($fkValue === null) {
            $relatedRecord = $relatedTable->newRecord();
            if ($this->isReadOnly()) {
                $relatedRecord->enableReadOnlyMode();
            }
        } else {
            $conditions = array_merge(
                [$relation->getForeignColumnName() => $this->getValue($relation->getLocalColumnName())],
                $relation->getAdditionalJoinConditions(static::getTable())
            );
            if ($relation->getType() === Relation::HAS_MANY) {
                $relatedRecord = $relatedTable->select('*', $conditions, function (OrmSelect $select) use ($relatedTable) {
                    $select->orderBy($relatedTable->getPkColumnName(), true);
                });
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
            } else {
                $relatedRecord = $relatedTable->newRecord();
                $data = $relatedTable->selectOne('*', $conditions);
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
                if (!empty($data)) {
                    $relatedRecord->fromData($data, true, true);
                }
            }
        }
        if ($this->isReadOnly()) {
            $this->relatedRecords[$relationName] = $relatedRecord;
        } else {
            $this->readOnlyData[$relationName] = $relatedRecord;
        }
        return $this;
    }

    /**
     * Testif there are enough data to load related record
     * @param string|Relation $relationName
     * @return bool
     * @throws \UnexpectedValueException
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
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function isRelatedRecordAttached($relationName) {
        static::getRelation($relationName);
        return array_key_exists($relationName, $this->isReadOnly() ? $this->readOnlyData : $this->relatedRecords);
    }

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return $this
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
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
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
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
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \PeskyORM\Exception\OrmException
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
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
     */
    public function updateValues(array $data, $isFromDb = false, $haltOnUnknownColumnNames = true) {
        if ($this->isReadOnly()) {
            if (!$isFromDb) {
                throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
            } else {
                $this->readOnlyData = $data;
                return $this;
            }
        }

        $pkColumn = static::getPrimaryKeyColumn();
        if ($isFromDb && !$this->existsInDb()) {
            // first set pk column value
            if (array_key_exists($pkColumn->getName(), $data)) {
                $this->_updateValue($pkColumn, $data[$pkColumn->getName()], true);
                unset($data[$pkColumn->getName()]);
            } else {
                throw new \InvalidArgumentException(
                    'Values update failed: record does not exist in DB while $isFromDb argument is \'true\'.'
                    . ' Possibly you\'ve missed a primary key value in $data argument.'
                );
            }
        }
        $columns = static::getColumns();
        $relations = static::getRelations();
        foreach ($data as $columnNameOrRelationName => $value) {
            if (isset($columns[$columnNameOrRelationName])) {
                $this->_updateValue($columns[$columnNameOrRelationName], $value, $isFromDb);
            } else if (isset($relations[$columnNameOrRelationName])) {
                $this->updateRelatedRecord(
                    $relations[$columnNameOrRelationName],
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
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
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
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \BadMethodCallException
     */
    public function begin() {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if ($this->isCollectingUpdates) {
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
     * Note: throws exception if used without begin()
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \Exception
     */
    public function commit(array $relationsToSave = [], $deleteNotListedRelatedRecords = false) {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to commit changed values: changes collecting was not started'
            );
        }
        $columnsToSave = array_keys($this->valuesBackup);
        $this->cleanUpdates();
        $this->saveToDb($columnsToSave);
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave, $deleteNotListedRelatedRecords);
        }
        return $this;
    }

    /**
     * Get names of all columns that can be saved to db
     * @return array
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getAllColumnsWithUpdatableValues() {
        $columnsNames = [];
        foreach (static::getColumns() as $columnName => $column) {
            if ($column->isValueCanBeSetOrChanged() && $column->isItExistsInDb()) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }

    /**
     * Get names of all columns that should automatically update values on each save
     * Note: throws exception if used without begin()
     * @return array
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
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function save(array $relationsToSave = [], $deleteNotListedRelatedRecords = false) {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to save data after begin(). You must call commit() or rollback()'
            );
        }
        $this->saveToDb($this->getAllColumnsWithUpdatableValues());
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave, $deleteNotListedRelatedRecords);
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
     * @throws \UnexpectedValueException
     * @throws \Exception
     */
    protected function saveToDb(array $columnsToSave = []) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if ($this->trustDbDataMode) {
            throw new \BadMethodCallException('Saving is not alowed when trusted mode for DB data is enabled');
        }
        if (empty($columnsToSave)) {
            // nothing to save
            return;
        }
        $diff = array_diff($columnsToSave, array_keys(static::getColumns()));
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Exception
     */
    protected function performDataSave($isUpdate, array $data) {
        $table = static::getTable();
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            if ($isUpdate) {
                unset($data[static::getPrimaryKeyColumnName()]);
                $updatedData = (array)$table::update(
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
                $this->updateValues($table::insert($data, true), true);
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function collectValuesForSave(array &$columnsToSave, $isUpdate) {
        $data = [];
        // collect values that are not from DB
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if (
                $column->isItExistsInDb()
                && !$column->isItPrimaryKey()
                && $this->_hasValue($column, true)
                && !$this->getValueContainerByColumnConfig($column)->isItFromDb()
            ) {
                $data[$columnName] = $this->_getValue($column, null);
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
     * @throws \UnexpectedValueException
     */
    protected function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, $isUpdate) {
        $updatedColumns = array_merge(
            array_keys($dataSavedToDb),
            array_intersect(array_keys(static::getColumnsThatDoNotExistInDb()), $columnsToSave)
        );
        foreach ($updatedColumns as $columnName) {
            $column = static::getColumn($columnName);
            $valueObject = $this->getValueContainerByColumnConfig($column);
            call_user_func(
                $column->getValueSavingExtender(),
                $valueObject,
                $isUpdate,
                $updatesReceivedFromDb
            );
            $valueObject->pullDataForSavingExtender();
        }
    }

    /**
     * Called after all data collected and validated
     * Warning: $data is not modifiable here! Use $this->collectValuesForSave() if you need to modify it
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    static public function validateValue($column, $value, $isFromDb = false) {
        if (!is_string($column)) {
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
            $columnErrors = $column->validateValue($value, false);
            if (!empty($columnErrors)) {
                $errors[$columnName] = $columnErrors;
            }
        }
        return $errors;
    }

    /**
     * Save requested relations to DB
     * @param array $relationsToSave
     * @param bool $deleteNotListedRelatedRecords - works only for HAS MANY relations
     *      - true: delete related records that exist in db if their pk value is not listed in current set of records
     *      - false: ignore related records that exist in db but their pk value is not listed in current set of records
     *      Example: there are 3 records in DB: 1, 2, 3. You're trying to save records 2 and 3 (record 1 is absent).
     *      If $deleteNotListedRelatedRecords === true then record 1 will be deleted; else - it will remain untouched
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\DbException
     * @throws \UnexpectedValueException
     * @throws \Exception
     */
    public function saveRelations(array $relationsToSave = [], $deleteNotListedRelatedRecords = false) {
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
                    $fkColName = $relations[$relationName]->getForeignColumnName();
                    $fkValue = $this->getValue($relations[$relationName]->getLocalColumnName());
                    if ($deleteNotListedRelatedRecords) {
                        $pkValues = [];
                        foreach ($relatedRecord as $recordObj) {
                            if ($recordObj->hasPrimaryKeyValue()) {
                                $pkValues[] = $recordObj->getPrimaryKeyValue();
                            }
                        }
                        // delete related records that are not listed in current records list but exist in DB
                        $conditions = [
                            $fkColName => $fkValue,
                        ];
                        if (!empty($pkValues)) {
                            $conditions[$relations[$relationName]->getForeignTable()->getPkColumnName() . ' !='] = $pkValues;
                        }
                        $relations[$relationName]->getForeignTable()->delete($conditions);
                    }
                    foreach ($relatedRecord as $recordObj) {
                        // placed here to avoid uniqueness fails connected to deleted records
                        $recordObj
                            ->updateValue($fkColName, $fkValue, false)
                            ->save();
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
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function delete($resetAllValuesAfterDelete = true, $deleteFiles = true) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if (!$this->hasPrimaryKeyValue()) {
            throw new \BadMethodCallException('It is impossible to delete record has no primary key value');
        } else {
            $this->beforeDelete();
            $table = static::getTable();
            $alreadyInTransaction = $table::inTransaction();
            if (!$alreadyInTransaction) {
                $table::beginTransaction();
            }
            try {
                $table::delete([static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]);
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
                    $this->getValueContainerByColumnConfig($column),
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
     *      '*' as the value for index 0: add all related records (if $loadRelatedRecordsIfNotSet === false - only loaded records will be added)
     * @param bool $loadRelatedRecordsIfNotSet - true: read all missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @return array
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        $loadRelatedRecordsIfNotSet = false,
        $withFilesInfo = true
    ) {
        // normalize column names
        if (empty($columnsNames) || (count($columnsNames) === 1 && $columnsNames[0] === '*')) {
            $columnsNames = array_keys(static::getColumns());
        } else if (in_array('*', $columnsNames, true)) {
            $columnsNames = array_merge($columnsNames, array_keys(static::getColumns()));
            foreach ($columnsNames as $index => $relationName) {
                if ($relationName === '*') {
                    unset($columnsNames[$index]);
                    break;
                }
            }
        }
        // normalize relation names
        if (
            array_key_exists(0, $relatedRecordsNames)
            && count($relatedRecordsNames) === 1
            && $relatedRecordsNames[0] === '*'
        ) {
            if ($loadRelatedRecordsIfNotSet) {
                $relatedRecordsNames = array_keys(static::getTableStructure()->getRelations());
            } else {
                $relatedRecordsNames = array_keys($this->relatedRecords);
            }
        }
        // collect data for columns
        $data = [];
        foreach ($columnsNames as $index => $columnName) {
            if (!is_int($index) || is_array($columnName) || static::hasRelation($columnName)) {
                // it is actually relation
                if (is_int($index)) {
                    $relatedRecordsNames[] = $columnName;
                } else {
                    $relatedRecordsNames[$index] = $columnName;
                }
            } else {
                $data[$columnName] = $this->getColumnValueForToArray($columnName, !$withFilesInfo, $notSet);
                if ($notSet) {
                    unset($data[$columnName]);
                }
            }
        }
        // collect data for relations
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
            if ($relatedRecord instanceof self) {
                if ($relatedRecord->existsInDb()) {
                    $data[$relatedRecordName] = $withFilesInfo
                        ? $relatedRecord->toArray($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet)
                        : $relatedRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                }
            } else {
                /** @var RecordsSet $relatedRecord*/
                $relatedRecord->enableDbRecordInstanceReuseDuringIteration();
                if ($this->trustDbDataMode) {
                    $relatedRecord->disableDbRecordDataValidation();
                }
                $data[$relatedRecordName] = [];
                foreach ($relatedRecord as $relRecord) {
                    $data[$relatedRecordName][] = $withFilesInfo
                        ? $relRecord->toArray($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet)
                        : $relRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                }
                $relatedRecord->disableDbRecordInstanceReuseDuringIteration();
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
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function getColumnValueForToArray($columnName, $returnNullForFiles = false, &$notSet = null) {
        if ($this->isReadOnly()) {
            if (array_key_exists($columnName, $this->readOnlyData)) {
                return $this->readOnlyData[$columnName];
            } else {
                return $this->createValueObject(static::getColumn($columnName))->getDefaultValue();
            }
        }
        $column = static::getColumn($columnName);
        $notSet = true;
        if ($column->isPrivateValue()) {
            return null;
        }
        if ($column->isItAFile()) {
            if (!$returnNullForFiles && $this->_hasValue($column, false)) {
                $notSet = false;
                return $this->_getValue($column, 'array');
            }
        } else {
            if ($this->existsInDb()) {
                if ($this->_hasValue($column, false)) {
                    $notSet = false;
                    $val = $this->_getValue($column, null);
                    return ($val instanceof DbExpr) ? null : $val;
                }
            } else {
                $notSet = false; //< there is always a value when record does not eist in DB
                // if default value not provided directly it is considered to be null when record does not exist is DB
                if ($this->_hasValue($column, true)) {
                    $val = $this->_getValue($column, null);
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
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
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
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getDefaults(array $columns = [], $ignoreColumnsThatDoNotExistInDB = true, $nullifyDbExprValues = true) {
        if (count($columns) === 0) {
            $columns = array_keys(static::getColumns());
        }
        $values = array();
        foreach ($columns as $columnName) {
            $column = static::getColumn($columnName);
            if ($ignoreColumnsThatDoNotExistInDB && !$column->isItExistsInDb()) {
                continue;
            } else {
                $values[$columnName] = $this->getValueContainerByColumnConfig($column)->getDefaultValueOrNull();
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
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetExists($key) {
        if ($this->isReadOnly()) {
            return array_key_exists($key, $this->readOnlyData);
        } else if (static::hasColumn($key)) {
            return $this->_hasValue(static::getColumn($key), false);
        } else if (static::hasRelation($key)) {
            if (!$this->isRelatedRecordCanBeRead($key)) {
                return false;
            }
            $record = $this->getRelatedRecord($key, true);
            return $record instanceof RecordInterface ? $record->existsInDb() : $record->count();
        } else {
            throw new \InvalidArgumentException(
                'There is no column or relation with name ' . $key . ' in ' . static::class
            );
        }
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param mixed $key - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if ($this->isReadOnly()) {
            if (array_key_exists($key, $this->readOnlyData)) {
                if (is_array($this->readOnlyData[$key]) && static::hasRelation($key)) {
                    $relation = static::getRelation($key);
                    if ($relation->getType() === $relation::HAS_MANY) {
                        $relatedRecord = RecordsSet::createFromArray(static::getTable(), $this->readOnlyData[$key], true);
                        $relatedRecord->enableReadOnlyMode();
                    } else {
                        $relatedRecord = $relation
                            ->getForeignTable()
                            ->newRecord()
                            ->enableReadOnlyMode()
                            ->fromDbData($this->readOnlyData[$key]);
                    }
                    $this->readOnlyData[$key] = $relatedRecord;
                }
                return $this->readOnlyData[$key];
            } else if (preg_match('%^(.+)_as_(.*)$%is', $key, $parts)) {
                list(, $colName, $format) = $parts;
                if (array_key_exists($colName, $this->readOnlyData)) {
                    $value = $this->readOnlyData[$colName];
                    $column = static::getColumn($colName);
                    $valueContainer = $this->createValueObject($column)->setRawValue($value, $value, true);
                    return call_user_func($column->getValueFormatter(), $valueContainer, $format);
                } else {
                    return null;
                }
            } else if (self::hasRelation($key)) {
                return $this->getRelatedRecord($key, true);
            } else {
                return null;
            }
        } else if (static::hasColumn($key)) {
            return $this->_getValue(static::getColumn($key), null);
        } else if (static::hasRelation($key)) {
            return $this->getRelatedRecord($key, true);
        } else if (preg_match('%^(.+)_as_(.*)$%is', $key, $parts)) {
            return $this->_getValue(static::getColumn($parts[1]), $parts[2]);
        } else {
            throw new \InvalidArgumentException(
                'There is no column or relation with name ' . $key . ' in ' . static::class
            );
        }
    }

    /**
     * @param mixed $key - column name or relation name
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws InvalidDataException
     */
    public function offsetSet($key, $value) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if (static::hasColumn($key)) {
            $this->_updateValue(static::getColumn($key), $value, $key === static::getPrimaryKeyColumnName());
        } else if (static::hasRelation($key)) {
            $this->updateRelatedRecord($key, $value, null);
        } else {
            throw new \InvalidArgumentException(
                'There is no column or relation with name ' . $key . ' in ' . static::class
            );
        }
    }

    /**
     * @param string $key
     * @return Record
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function offsetUnset($key) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if (static::hasColumn($key)) {
            return $this->unsetValue($key);
        } else if (static::hasRelation($key)) {
            return $this->unsetRelatedRecord($key);
        } else {
            throw new \InvalidArgumentException(
                'There is no column or relation with name ' . $key . ' in ' . static::class
            );
        }
    }

    /**
     * @param $name - column name, column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \PDOException
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
     * @throws \PDOException
     * @throws InvalidDataException
     */
    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    /**
     * @param $name - column name or relation name
     * @return bool
     * @throws \PDOException
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
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws InvalidDataException
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
                    && !($value instanceof self)
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
            $column = static::getColumn($columnName);
            $isFromDb = array_key_exists(1, $arguments)
                ? (bool)$arguments[1]
                : $column->isItPrimaryKey(); //< make pk key be "from DB" by default or it will crash
            $this->_updateValue($column, $value, $isFromDb);
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
            $this->getValueContainerByColumnName($name)->unserialize($value);
        }
    }

    /**
     * Enable read only mode. In this mode incoming data is not processed in any way and Record works like an array
     * but maintains most getters functionality including relations.
     * Usage of value formatters are allowed ({column}_as_array, {column}_as_object, etc.)
     * Relations returned as similar read only Records or RecordArrays.
     * In this mode you're able to use Record's methods that do not modify Record's data.
     * @return $this
     */
    public function enableReadOnlyMode() {
        if ($this->existsInDb()) {
            $this->readOnlyData = $this->toArray([], ['*']);
        }
        $this->isReadOnly = true;
        return $this;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Disable read only mode.
     * @return $this
     */
    public function disableReadOnlyMode() {
        $this->isReadOnly = false;
        $this->reset();
        if (!empty($this->readOnlyData)) {
            $this->updateValues($this->readOnlyData, true);
        }
        $this->readOnlyData = [];
        return $this;
    }

    /**
     * @return bool
     */
    public function isReadOnly() {
        return $this->isReadOnly;
    }

}