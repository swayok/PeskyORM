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

    const COLUMN_NAME_WITH_FORMAT_REGEXP = '%^(.+)_as_(.+)$%is';

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
    private $existsInDb;
    /**
     * @var null
     */
    private $existsInDbReally;
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
     */
    static public function fromArray(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        return static::newEmptyRecord()->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
    }

    /**
     * Create new record and load values from DB using $pkValue
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected @see \PeskyORM\ORM\Column::valueIsHeavy(). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * @param mixed $pkValue
     * @param array $columns
     * @param array $readRelatedRecords
     * @return static
     */
    static public function read($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return static::newEmptyRecord()->fetchByPrimaryKey($pkValue, $columns, $readRelatedRecords);
    }

    /**
     * Create new record and find values in DB using $conditionsAndOptions
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected @see \PeskyORM\ORM\Column::valueIsHeavy(). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * @param array $conditionsAndOptions
     * @param array $columns
     * @param array $readRelatedRecords
     * @return static
     */
    static public function find(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        return static::newEmptyRecord()->fetch($conditionsAndOptions, $columns, $readRelatedRecords);
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
        //$this->reset();
    }

    /**
     * @return TableStructure|TableStructureInterface
     */
    static public function getTableStructure() {
        if (!isset(self::$tableStructures[static::class])) {
            self::$tableStructures[static::class] = static::getTable()->getStructure();
        }
        return self::$tableStructures[static::class];
    }

    /**
     * @param bool $includeFormats - include columns formats ({column}_as_array, etc.)
     * @return Column[] - key = column name
     */
    static public function getColumns(bool $includeFormats = false): array {
        return self::getCachedColumnsOrRelations($includeFormats ? 'columns_and_formats' : 'columns');
    }

    /**
     * @return Column[] - key = column name
     */
    static public function getColumnsThatExistInDb(): array {
        return self::getCachedColumnsOrRelations('db_columns');
    }

    /**
     * @return Column[] - key = column name
     */
    static public function getColumnsThatDoNotExistInDb(): array {
        return self::getCachedColumnsOrRelations('not_db_columns');
    }

    /**
     * @param string $key
     * @return mixed
     */
    static private function getCachedColumnsOrRelations(string $key = 'columns') {
        // significantly decreases execution time on heavy ORM usage (proved by profilig with xdebug)
        if (!isset(self::$columns[static::class])) {
            $tableStructure = static::getTableStructure();
            $columns = $tableStructure::getColumns();
            self::$columns[static::class] = [
                'columns' => $columns,
                'columns_and_formats' => [],
                'db_columns' => $tableStructure::getColumnsThatExistInDb(),
                'not_db_columns' => $tableStructure::getColumnsThatDoNotExistInDb(),
                'file_columns' => $tableStructure::getFileColumns(),
                'pk_column' => $tableStructure::getPkColumn(),
                'relations' => $tableStructure::getRelations(),
            ];
            foreach ($columns as $columnName => $column) {
                self::$columns[static::class]['columns_and_formats'][$columnName] = [
                    'format' => null,
                    'column' => $column
                ];
                /** @var ColumnClosuresInterface $closuresClass */
                $closuresClass = $column->getClosuresClass();
                $formats = $closuresClass::getValueFormats($column);
                foreach ($formats as $format) {
                    self::$columns[static::class]['columns_and_formats'][$columnName . '_as_' . $format] = [
                        'format' => $format,
                        'column' => $column
                    ];
                }
            }
        }
        return self::$columns[static::class][$key];
    }

    /**
     * @param string $name
     * @param string|null $format - filled when $name is something like 'timestamp_as_date' (returns 'date')
     * @return Column
     * @throws \InvalidArgumentException
     */
    static public function getColumn(string $name, string &$format = null) {
        $columns = static::getColumns(true);
        if (!isset($columns[$name])) {
            throw new \InvalidArgumentException(
                "There is no column '$name' in " . get_class(static::getTableStructure())
            );
        }
        $format = $columns[$name]['format'];
        return $columns[$name]['column'];
    }

    /**
     * @param string $name
     * @return bool
     */
    static public function hasColumn($name): bool {
        return isset(static::getColumns(true)[$name]);
    }

    /**
     * @return Column
     */
    static public function getPrimaryKeyColumn() {
        return static::getCachedColumnsOrRelations('pk_column');
    }

    /**
     * @return string
     */
    static public function getPrimaryKeyColumnName(): string {
        return static::getPrimaryKeyColumn()->getName();
    }

    /**
     * @return Relation[]
     */
    static public function getRelations(): array {
        return static::getCachedColumnsOrRelations('relations');
    }

    /**
     * @param string $name
     * @return Relation
     * @throws \InvalidArgumentException
     */
    static public function getRelation(string $name) {
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
     */
    static public function hasRelation($name): bool {
        return isset(static::getRelations()[$name]);
    }

    /**
     * @return Column[]
     */
    static public function getFileColumns(): array {
        return static::getCachedColumnsOrRelations('file_columns');
    }

    static public function hasFileColumns(): bool {
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

    protected function isTrustDbDataMode(): bool {
        return $this->trustDbDataMode;
    }

    /**
     * Resets all values and related records
     * @return $this
     * @throws \BadMethodCallException
     */
    public function reset() {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first'
            );
        }
        $this->values = [];
        $this->valuesBackup = [];
        $this->readOnlyData = [];
        $this->relatedRecords = [];
        $this->iteratorIdx = 0;
        $this->existsInDb = null;
        $this->existsInDbReally = null;
        $this->cleanUpdates();
        return $this;
    }

    /**
     * @param Column $column
     * @return RecordValue
     */
    protected function createValueObject(Column $column): RecordValue {
        return new RecordValue($column, $this);
    }

    /**
     * @param string|Column $column
     * @return $this
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
    protected function getValueContainer($colNameOrConfig): RecordValue {
        return is_string($colNameOrConfig)
            ? $this->getValueContainerByColumnName($colNameOrConfig)
            : $this->getValueContainerByColumnConfig($colNameOrConfig);
    }

    /**
     * Warning: do not use it to get/set/check value!
     * @param string $columnName
     * @return RecordValue
     */
    protected function getValueContainerByColumnName(string $columnName): RecordValue {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode.');
        }
        if (!isset($this->values[$columnName])) {
            $this->values[$columnName] = $this->createValueObject(static::getColumn($columnName));
        }
        return $this->values[$columnName];
    }

    /**
     * Warning: do not use it to get/set/check value!
     * @param Column $column
     * @return RecordValue
     */
    protected function getValueContainerByColumnConfig(Column $column): RecordValue {
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
     * @param null|string $format
     * @return mixed
     */
    public function getValue($column, ?string $format = null) {
        return $this->_getValue(is_string($column) ? static::getColumn($column) : $column, $format);
    }

    /**
     * @param Column $column
     * @param null|string $format
     * @return mixed
     */
    protected function _getValue(Column $column, ?string $format) {
        if ($this->isReadOnly()) {
            $value = array_key_exists($column->getName(), $this->readOnlyData)
                ? $this->readOnlyData[$column->getName()]
                : null;
            if (empty($format)) {
                return $value;
            } else {
                return call_user_func(
                    $column->getValueGetter(),
                    $this->createValueObject($column)->setRawValue($value, $value, true),
                    $format
                );
            }
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
     */
    public function getValueIfExistsInDb(string $columnName, $default = null) {
        return ($this->existsInDb() && isset($this->$columnName)) ? $this->$columnName : $default;
    }

    /**
     * @param string|Column $column
     * @return mixed
     */
    public function getOldValue($column) {
        return $this->getValueContainer($column)->getOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     */
    public function hasOldValue($column): bool {
        return $this->getValueContainer($column)->hasOldValue();
    }

    /**
     * @param string|Column $column
     * @return bool
     */
    public function isOldValueWasFromDb($column): bool {
        return $this->getValueContainer($column)->isOldValueWasFromDb();
    }

    /**
     * Check if there is a value for $columnName
     * @param string|Column $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    public function hasValue($column, bool $trueIfThereIsDefaultValue = false): bool {
        return $this->_hasValue(is_string($column) ? static::getColumn($column) : $column, $trueIfThereIsDefaultValue);
    }

    /**
     * @param Column $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    protected function _hasValue(Column $column, bool $trueIfThereIsDefaultValue): bool {
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
     */
    public function isValueFromDb($column): bool {
        return $this->getValueContainer($column)->isItFromDb();
    }

    /**
     * @param string|Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return $this
     * @throws \BadMethodCallException
     */
    public function updateValue($column, $value, bool $isFromDb) {
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
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function _updateValue(Column $column, $value, bool $isFromDb) {
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
        call_user_func($column->getValueSetter(), $value, (bool)$isFromDb, $valueContainer, $this->isTrustDbDataMode());
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
     */
    public function getPrimaryKeyValue() {
        return $this->_getValue(static::getPrimaryKeyColumn(), null);
    }

    /**
     * @return bool
     */
    public function hasPrimaryKeyValue(): bool {
        return $this->_hasValue(static::getPrimaryKeyColumn(), false);
    }

    /**
     * Check if current Record exists in DB
     * @param bool $useDbQuery - false: use only primary key value to check existence | true: use db query
     * @return bool
     */
    public function existsInDb(bool $useDbQuery = false): bool {
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
     * @return bool
     */
    protected function _existsInDbViaQuery(): bool {
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
     */
    public function updateRelatedRecord($relationName, $relatedRecord, ?bool $isFromDb = null, bool $haltOnUnknownColumnNames = true) {
        /** @var Relation $relation */
        $relation = is_string($relationName) ? static::getRelation($relationName) : $relationName;
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === Relation::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = RecordsSet::createFromArray($relationTable, $relatedRecord, $isFromDb, $this->isTrustDbDataMode());
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
            } else if (!($relatedRecord instanceof RecordsArray)) {
                throw new \InvalidArgumentException(
                    '$relatedRecord argument for HAS MANY relation must be array or instance of ' . RecordsArray::class
                );
            }
        } else if (is_array($relatedRecord)) {
            if ($isFromDb === null) {
                $pkName = $relationTable::getPkColumnName();
                $isFromDb = array_key_exists($pkName, $relatedRecord) && $relatedRecord[$pkName] !== null;
            }
            $data = $relatedRecord;
            $relatedRecord = $relationTable->newRecord();
            if ($this->isTrustDbDataMode()) {
                $relatedRecord->enableTrustModeForDbData();
            }
            if ($this->isReadOnly()) {
                $relatedRecord->enableReadOnlyMode();
            }
            if (!empty($data)) {
                $relatedRecord->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
            }
        } else if ($relatedRecord instanceof self) {
            if ($relatedRecord::getTable()->getName() !== $relationTable::getName()) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of Record class for the '{$relationTable::getName()}' DB table"
                );
            }
            if ($this->isTrustDbDataMode()) {
                $relatedRecord->enableTrustModeForDbData();
            }
            if ($this->isReadOnly()) {
                $relatedRecord->enableReadOnlyMode();
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of Record class for the '{$relationTable::getName()}' DB table"
            );
        }
        $this->relatedRecords[$relation->getName()] = $relatedRecord;
        return $this;
    }

    /**
     * Remove related record
     * @param string $relationName
     * @return $this
     */
    public function unsetRelatedRecord(string $relationName) {
        static::getRelation($relationName);
        unset($this->relatedRecords[$relationName]);
        return $this;
    }

    /**
     * Get existing related object(s) or read them
     * @param string $relationName
     * @param bool $loadIfNotSet - true: read relation data if it is not set
     * @return Record|RecordsSet
     * @throws \BadMethodCallException
     */
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false) {
        if (!$this->isRelatedRecordAttached($relationName)) {
            if ($loadIfNotSet) {
                $this->readRelatedRecord($relationName);
            } else {
                throw new \BadMethodCallException(
                    "Related record with name '$relationName' is not set and autoloading is disabled"
                );
            }
        } else if ($this->isReadOnly() && !isset($this->relatedRecords[$relationName])) {
            $this->updateRelatedRecord($relationName, $this->readOnlyData[$relationName], true, true);
        }
        return $this->relatedRecords[$relationName];
    }

    /**
     * Read related object(s). If there are already loaded object(s) - they will be overwritten
     * @param string $relationName
     * @return $this
     */
    public function readRelatedRecord(string $relationName) {
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
                $relation->getAdditionalJoinConditions(static::getTable(), null, true, $this)
            );
            if ($relation->getType() === Relation::HAS_MANY) {
                $relatedRecord = $relatedTable::select('*', $conditions, function (OrmSelect $select) use ($relatedTable) {
                    $select->orderBy($relatedTable::getPkColumnName(), true);
                });
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
            } else {
                $relatedRecord = $relatedTable->newRecord();
                $data = $relatedTable::selectOne('*', $conditions);
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
                if (!empty($data)) {
                    $relatedRecord->fromData($data, true, true);
                }
            }
        }
        $this->relatedRecords[$relationName] = $relatedRecord;
        return $this;
    }

    /**
     * Testif there are enough data to load related record
     * @param string|Relation $relation
     * @return bool
     */
    protected function isRelatedRecordCanBeRead($relation): bool {
        $relation = $relation instanceof Relation
            ? $relation
            : static::getRelation($relation);
        return $this->hasValue($relation->getLocalColumnName());
    }

    /**
     * Check if related object(s) are stored in this Record
     * @param string $relationName
     * @return bool
     */
    public function isRelatedRecordAttached(string $relationName): bool {
        static::getRelation($relationName);
        if ($this->isReadOnly() && isset($this->readOnlyData[$relationName])) {
            return true;
        }
        return isset($this->relatedRecords[$relationName]);
    }

    /**
     * Fill record values from passed $data.
     * Note: all existing record values will be removed
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown if there are unknown column names in $data
     * @return $this
     */
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        $this->reset();
        $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
        return $this;
    }

    /**
     * Fill record values from passed $data.
     * All values are marked as loaded from DB and any unknows column names will raise exception
     * @param array $data
     * @return $this
     */
    public function fromDbData(array $data) {
        return $this->fromData($data, true, true);
    }

    /**
     * @deprecated
     */
    public function fromPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return $this->fetchByPrimaryKey($pkValue, $columns, $readRelatedRecords);
    }

    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected @see \PeskyORM\ORM\Column::valueIsHeavy(). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * @param int|float|string $pkValue
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     */
    public function fetchByPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []) {
        return $this->fetch([static::getPrimaryKeyColumnName() => $pkValue], $columns, $readRelatedRecords);
    }

    /**
     * @deprecated
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
        return $this->fetch($conditionsAndOptions, $columns, $readRelatedRecords);
    }

    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected @see \PeskyORM\ORM\Column::valueIsHeavy(). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @param array $conditionsAndOptions
     * @param array $columns - empty: get all columns
     * @param array $readRelatedRecords - also read related records
     * @return $this
     */
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []) {
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
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected @see \PeskyORM\ORM\Column::valueIsHeavy(). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * @param array $columns - columns to read
     * @param array $readRelatedRecords - also read related records
     * @return $this
     * @throws RecordNotFoundException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []) {
        if (!$this->existsInDb()) {
            throw new RecordNotFoundException('Record must exist in DB');
        }
        return $this->fetchByPrimaryKey($this->getPrimaryKeyValue(), $columns, $readRelatedRecords);
    }

    /**
     * Read values for specific columns
     * @param array $columns - columns to read
     * @return $this
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        if ($this->isReadOnly()) {
            if (!$isFromDb) {
                throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
            } else {
                $this->readOnlyData = static::normalizeReadOnlyData($data);
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
                    null,
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
     */
    public function merge(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true) {
        return $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
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
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
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
     */
    protected function getAllColumnsWithUpdatableValues(): array {
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
     */
    protected function getAllColumnsWithAutoUpdatingValues(): array {
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
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
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
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    protected function saveToDb(array $columnsToSave = []) {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        } else if ($this->isTrustDbDataMode()) {
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
        try {
            $this->runColumnSavingExtenders($columnsToSave, $data, $updatedData, $isUpdate);
            $this->cleanCacheAfterSave(!$isUpdate);
            $this->afterSave(!$isUpdate, $columnsToSave);
        } catch (\Exception $exc) {
            static::getTable()::rollBackTransactionIfExists();
            throw $exc;
        }
    }

    /**
     * @param bool $isUpdate
     * @param array $data
     * @return bool
     * @throws \UnexpectedValueException
     */
    protected function performDataSave(bool $isUpdate, array $data): bool {
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
            $table::rollBackTransactionIfExists();
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
     */
    protected function collectValuesForSave(array &$columnsToSave, bool $isUpdate): array {
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
            if (!isset($data[$columnName])) {
                $data[$columnName] = static::getColumn($columnName)->getAutoUpdateForAValue();
            }
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
     */
    protected function runColumnSavingExtenders(array $columnsToSave, array $dataSavedToDb, array $updatesReceivedFromDb, bool $isUpdate) {
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
    protected function beforeSave(array $columnsToSave, array $data, bool $isUpdate) {
        return [];
    }

    /**
     * Called after successful save() and commit() even if nothing was really saved to database
     * @param bool $isCreated - true: new record was created; false: old record was updated
     * @param array $updatedColumns - list of updated columns
     */
    protected function afterSave(bool $isCreated, array $updatedColumns = []) {

    }

    /**
     * Clean cache related to this record after saving it's data to DB.
     * Called before afterSave()
     * @param bool $isCreated
     */
    protected function cleanCacheAfterSave(bool $isCreated) {

    }

    /**
     * Validate a value
     * @param string|Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return array - errors
     */
    static public function validateValue($column, $value, bool $isFromDb = false) {
        if (is_string($column)) {
            $column = static::getColumn($column);
        }
        return $column->validateValue($value, $isFromDb, false);
    }

    /**
     * Validate data
     * @param array $data
     * @param array $columnsNames - column names to validate. If col
     * @param bool $isUpdate
     * @return array
     */
    protected function validateNewData(array $data, array $columnsNames = [], bool $isUpdate = false) {
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
            $columnErrors = $column->validateValue($value, false, false);
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
     */
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false) {
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
     * @throws \BadMethodCallException
     */
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true) {
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
                $table::rollBackTransactionIfExists();
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
     * @param array $columnsNames
     *  - empty array: return values for all columns
     *  - array: contains index-string, key-string, key-array pairs:
     *      index-string: value is column name or relation name (returns all data from related record)
     *      key-string (renaming): key is a column name and value is a column alias (alters column name in resulting array)
     *      key-array (relation data): key is a relation name and value is an array containing column names of the relation
     *      '*' as the value for index 0: add all related records (if $loadRelatedRecordsIfNotSet === false - only loaded records will be added)
     * @param array $relatedRecordsNames
     *  - empty: do not add any relations
     *  - array: contains index-string, key-string, key-array pairs or single value = '*':
     *      '*' as the value for index === 0: add all related records (if $loadRelatedRecordsIfNotSet === false - only already loaded records will be added)
     *      index-string: value is relation name (returns all data from related record)
     *      key-array: key is relation name and value is array containing column names of related record to return
     * @param bool $loadRelatedRecordsIfNotSet - true: read all missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @return array
     * @throws \InvalidArgumentException
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false,
        bool $withFilesInfo = true
    ): array {
        // normalize column names
        if (empty($columnsNames) || (count($columnsNames) === 1 && isset($columnsNames[0]) && $columnsNames[0] === '*')) {
            $columnsNames = array_keys(static::getColumns());
        } else if (in_array('*', $columnsNames, true)) {
            $columnsNames = array_merge($columnsNames, array_keys(static::getColumns()));
            foreach ($columnsNames as $index => $relationName) {
                if ($relationName === '*') {
                    unset($columnsNames[$index]);
                    break;
                }
            }
        } else if (isset($columnsNames['*'])) {
            // exclude some columns from wildcard
            $columnsNames = array_merge(
                array_diff(array_keys(static::getColumns()), (array)$columnsNames['*']),
                $columnsNames
            );
            unset($columnsNames['*']);
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
                $relatedRecordsNames = array_keys(static::getTableStructure()->getRelations());
                if ($this->isReadOnly()) {
                    $relatedRecordsNames = array_intersect(
                        $relatedRecordsNames,
                        array_merge(array_keys($this->relatedRecords), array_keys($this->readOnlyData))
                    );
                } else {
                    $relatedRecordsNames = array_keys($this->relatedRecords);
                }
            }
        }
        // collect data for columns
        $data = [];
        foreach ($columnsNames as $index => $columnName) {
            if ((!is_int($index) && is_array($columnName)) || static::hasRelation($columnName)) {
                // it is actually relation
                if (is_int($index)) {
                    // get all data from related record
                    $relatedRecordsNames[] = $columnName;
                } else {
                    // get certain data form related record
                    $relatedRecordsNames[$index] = $columnName;
                }
            } else {
                $columnAlias = $columnName;
                if (!is_int($index)) {
                    $columnName = $index;
                }
                if (!static::hasColumn($columnName) && count($parts = explode('.', $columnName)) > 1) {
                    // $columnName = 'Relaion.column' or 'Relation.Subrelation.column'
                    $data[$columnAlias] = $this->getNestedValueForToArray($parts, $loadRelatedRecordsIfNotSet, !$withFilesInfo, $isset);
                } else {
                    $data[$columnAlias] = $this->getColumnValueForToArray($columnName, !$withFilesInfo, $isset);
                }
                if (is_bool($isset) && !$isset) {
                    unset($data[$columnAlias]);
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
                // ignore related records without non-default data
                if ($relatedRecord->existsInDb()) {
                    $data[$relatedRecordName] = $withFilesInfo
                        ? $relatedRecord->toArray($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet)
                        : $relatedRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                } else if ($relatedRecord->hasAnyNonDefaultValues()) {
                    // return related record only if there are any non-default value (column that do not exist in db are ignored)
                    $data[$relatedRecordName] = $relatedRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                }
            } else {
                /** @var RecordsSet $relatedRecord*/
                $relatedRecord->enableDbRecordInstanceReuseDuringIteration();
                if ($this->isTrustDbDataMode()) {
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

    protected function hasAnyNonDefaultValues(): bool {
        $columnsNames = static::getColumns();
        foreach ($columnsNames as $columnName => $column) {
            if ($column->isItExistsInDb() && $this->hasValue($column, false)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get column value if it is set or null in any other cases
     * @param string $columnName - value may be modiied to real column name instead of column name with format (like 'created_at_as_time')
     * @param bool $returnNullForFiles - false: return file information for file column | true: return null for file column
     * @param bool $isset - true: value is set | false: value is not set
     * @return mixed
     */
    protected function getColumnValueForToArray(&$columnName, bool $returnNullForFiles = false, ?bool &$isset = null) {
        $isset = false;
        $column = static::getColumn($columnName, $format);
        if ($column->isPrivateValue()) {
            return null;
        }
        if ($this->isReadOnly()) {
            if (array_key_exists($columnName, $this->readOnlyData)) {
                $isset = true;
                return $this->readOnlyData[$columnName];
            } else if ($format) {
                $valueContainer = $this->createValueObject($column);
                if (array_key_exists($column->getName(), $this->readOnlyData)) {
                    $isset = true;
                    $value = $this->readOnlyData[$column->getName()];
                    $valueContainer->setRawValue($value, $value, true);
                    return call_user_func($column->getValueFormatter(), $valueContainer, $format);
                } else {
                    $isset = true;
                    return null;
                }
            }
        }
        if ($column->isItAFile()) {
            if (!$returnNullForFiles && $this->_hasValue($column, false)) {
                $isset = true;
                return $this->_getValue($column, $format ?: 'array');
            }
        } else {
            if ($this->existsInDb()) {
                if ($this->_hasValue($column, false)) {
                    $isset = true;
                    $val = $this->_getValue($column, $format);
                    return ($val instanceof DbExpr) ? null : $val;
                }
            } else {
                $isset = true; //< there is always a value when record does not exist in DB
                // if default value not provided directly it is considered to be null when record does not exist is DB
                if ($this->_hasValue($column, true)) {
                    $val = $this->_getValue($column, $format);
                    return ($val instanceof DbExpr) ? null : $val;
                }
            }
        }
        return null;
    }

    /**
     * Get nested value if it is set or null in any other cases
     * @param array $parts - parts of nested path ('Relation.Subrelation.column' => ['Relation', 'Subrelation', 'column']
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @param bool $returnNullForFiles - false: return file information for file column | true: return null for file column
     * @param bool|null $isset - true: value is set | false: value is not set
     * @return mixed
     */
    protected function getNestedValueForToArray(array $parts, bool $loadRelatedRecordsIfNotSet, bool $returnNullForFiles = false, ?bool &$isset = null) {
        $relationName = array_shift($parts);
        $relatedRecord = $this->getRelatedRecord($relationName, $loadRelatedRecordsIfNotSet);
        if ($relatedRecord instanceof self) {
            // ignore related records without non-default data
            if ($relatedRecord->existsInDb() || $relatedRecord->hasAnyNonDefaultValues()) {
                if (count($parts) === 1) {
                    return $relatedRecord->getColumnValueForToArray($parts[0], $returnNullForFiles, $isset);
                } else {
                    return $relatedRecord->getNestedValueForToArray($parts, $loadRelatedRecordsIfNotSet, $returnNullForFiles, $isset);
                }
            }
            $isset = false;
            return null;
        } else {
            // record set - not supported
            throw new \InvalidArgumentException(
                'Has many relations are not supported. Trying to resolve: ' . $relationName . '.' . implode('.', $parts)
            );
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
     * @param bool $ignoreColumnsThatDoNotExistInDB - true: if column does not exist in DB - its value will not be returned
     * @param bool $nullifyDbExprValues - true: if default value is DbExpr - replace it by null
     * @return array
     */
    public function getDefaults(array $columns = [], $ignoreColumnsThatDoNotExistInDB = true, $nullifyDbExprValues = true): array {
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
     * @throws \InvalidArgumentException
     */
    public function offsetExists($key) {
        if ($this->isReadOnly()) {
            $exists = array_key_exists($key, $this->readOnlyData) || isset($this->relatedRecords[$key]);
            if ($exists) {
                return true;
            }
            if (preg_match(static::COLUMN_NAME_WITH_FORMAT_REGEXP, $key, $parts) && array_key_exists($parts[1], $this->readOnlyData)) {
                if (!$this->_hasValue(static::getColumn($parts[1]), false)) {
                    return false;
                }
                return $this->offsetGet($key) !== null;
            }
            return false;
        } else if (static::hasColumn($key)) {
            return $this->_hasValue(static::getColumn($key), false);
        } else if (static::hasRelation($key)) {
            if (!$this->isRelatedRecordCanBeRead($key)) {
                return false;
            }
            $record = $this->getRelatedRecord($key, true);
            return $record instanceof RecordInterface ? $record->existsInDb() : $record->count();
        } else if (preg_match(static::COLUMN_NAME_WITH_FORMAT_REGEXP, $key, $parts)) {
            if (!$this->_hasValue(static::getColumn($parts[1]), false)) {
                return false;
            }
            return $this->offsetGet($key) !== null;
        } else {
            throw new \InvalidArgumentException(
                'There is no column or relation with name ' . $key . ' in ' . static::class
            );
        }
    }

    /**
     * @param mixed $key - column name or column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function offsetGet($key) {
        if ($this->isReadOnly()) {
            if (self::hasRelation($key)) {
                return $this->getRelatedRecord($key, true);
            } else if (array_key_exists($key, $this->readOnlyData)) {
                return $this->readOnlyData[$key];
            } else if (preg_match(static::COLUMN_NAME_WITH_FORMAT_REGEXP, $key, $parts)) {
                [, $colName, $format] = $parts;
                if (array_key_exists($colName, $this->readOnlyData)) {
                    $value = $this->readOnlyData[$colName];
                    $column = static::getColumn($colName);
                    $valueContainer = $this->createValueObject($column)->setRawValue($value, $value, true);
                    return call_user_func($column->getValueFormatter(), $valueContainer, $format);
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else if (static::hasColumn($key)) {
            $column = static::getColumn($key);
            $format = null;
            if ($column->getName() !== $key) {
                $parts = explode('_as_', $key, 2);
                if (count($parts) === 2) {
                    $format = $parts[1];
                }
            }
            return $this->_getValue(static::getColumn($key), $format);
        } else if (static::hasRelation($key)) {
            return $this->getRelatedRecord($key, true);
        } else if (preg_match(static::COLUMN_NAME_WITH_FORMAT_REGEXP, $key, $parts)) {
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
     * @param $name - column name or column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     */
    public function __get($name) {
        return $this->offsetGet($name);
    }

    /**
     * @param $name - 'setColumnName' or 'setRelationName'
     * @param $value
     */
    public function __set($name, $value) {
        return $this->offsetSet($name, $value);
    }

    /**
     * @param $name - column name or relation name
     * @return bool
     */
    public function __isset($name) {
        return $this->offsetExists($name);
    }

    /**
     * @param string $name - column name or relation name
     */
    public function __unset($name) {
        $this->offsetUnset($name);
    }

    /**
     * Supports only methods starting with 'set' and ending with column name or relation name
     * @param string $name - something like 'setColumnName' or 'setRelationName'
     * @param array $arguments - 1 required, 2 accepted. 1st - value, 2nd - $isFromDb
     * @return $this
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
        $data = [
            'props' => [
                'existsInDb' => $this->existsInDb
            ],
            'values' => []
        ];
        foreach ($this->values as $name => $value) {
            $data['values'][$name] = $value->serialize();
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
     * @throws \InvalidArgumentException
     * @since 5.1.0
     */
    public function unserialize($serialized) {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        $this->reset();
        foreach ($data['props'] as $name => $value) {
            $this->$name = $value;
        }
        /** @var array $data */
        foreach ($data['values'] as $name => $value) {
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
    public function isReadOnly(): bool {
        return $this->isReadOnly;
    }

    /**
     * Normalizes readonly data so that numeric and bool values will not be strings
     * @param array $data
     * @return array
     */
    static public function normalizeReadOnlyData(array $data): array {
        $columns = static::getColumns();
        $relations = static::getRelations();
        foreach ($data as $key => $value) {
            if (isset($columns[$key])) {
                $data[$key] = RecordValueHelpers::normalizeValueReceivedFromDb($value, static::getColumn($key)->getType());
            } else if (isset($relations[$key])) {
                if (!is_array($value)) {
                    $data[$key] = $value;
                } else {
                    $data[$key] = $relations[$key]->getForeignTable()->newRecord()->normalizeReadOnlyData($value);
                }
            }
        }
        return $data;
    }

}
