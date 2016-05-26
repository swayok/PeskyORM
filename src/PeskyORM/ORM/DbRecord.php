<?php

namespace PeskyORM\ORM;

abstract class DbRecord {

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
     * @var DbRecordValue[]
     */
    protected $values = [];
    /**
     * @var DbRecord[]
     */
    protected $relatedRecords = [];

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
     * Resets all values and related records
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function reset() {
        $this->values = [];
        $this->relatedRecords = [];
        foreach (static::getTableStructure()->getColumns() as $columnName => $column) {
            $this->values[$columnName] = DbRecordValue::create($column, $this);
        }
    }

    /**
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
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getColumnValue($columnName) {
        return $this->getColumnValueObject($columnName)->getValue();
    }

    /**
     * @param string $columnName
     * @return bool
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function hasColumnValue($columnName) {
        return $this->getColumnValueObject($columnName)->hasValue();
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
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
     */
    public function hasRelatedRecord($relationName) {
        return array_key_exists($relationName, $this->relatedRecords);
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
        foreach ($data as $columnNameOreRelationName => $value) {
            if (static::hasColumn($columnNameOreRelationName)) {
                $this->setColumnValue($columnNameOreRelationName, $value, $isFromDb);
            } else if (static::hasRelation($columnNameOreRelationName)) {
                $this->setRelatedRecord($columnNameOreRelationName, $value, $isFromDb, $haltOnUnknownColumnNames);
            } else if ($haltOnUnknownColumnNames) {
                throw new \InvalidArgumentException(
                    "\$data argument contains unknown column name or relation name: '$columnNameOreRelationName'"
                );
            }
        }
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    private function fromPrimaryKey($pkValue, array $readRelatedRecords = []) {
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
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    private function fromDb(array $conditionsAndOptions) {
        $record = $this->getTable()->selectOne('*', $conditionsAndOptions);
        if (!empty($record)) {
            return $this->fromDbData($record);
        }
        return $this;
    }

}