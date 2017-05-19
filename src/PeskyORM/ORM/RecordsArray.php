<?php

namespace PeskyORM\ORM;

use Psr\Log\InvalidArgumentException;

class RecordsArray implements \ArrayAccess, \Iterator, \Countable  {

    /**
     * @var TableInterface
     */
    protected $table;
    /**
     * @var array
     */
    protected $records = [];
    /**
     * @var
     */
    protected $position = 0;
    /**
     * @var RecordInterface[]
     */
    protected $dbRecords = [];
    /**
     * @var bool
     */
    protected $isFromDb = null;
    /**
     * @var bool
     */
    protected $dbRecordInstanceReuseEnabled = false;
    /**
     * @var bool
     */
    protected $dbRecordInstanceDisablesValidation = false;
    /**
     * @var RecordInterface
     */
    protected $dbRecordForIteration = null;
    /**
     * @var int
     */
    protected $currentDbRecordIndex = -1;

    /**
     * @param TableInterface $table
     * @param array $records
     * @param bool $isFromDb|null - true: records are from db | null - autodetect
     * @throws \InvalidArgumentException
     */
    public function __construct(TableInterface $table, array $records, $isFromDb = null) {
        $this->table = $table;
        if (count($records)) {
            /** @noinspection ForeachSourceInspection */
            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new \InvalidArgumentException('$dbSelectOrRecords must contain only arrays');
                }
            }
            $this->records = array_values($records);
        }
        $this->isFromDb = $isFromDb;
    }

    /**
     * @return RecordInterface
     */
    protected function getDbRecordObjectForIteration() {
        if ($this->dbRecordForIteration === null) {
            $this->dbRecordForIteration = $this->table->newRecord();
        }
        if ($this->isDbRecordInstanceHasDisabledDbDataValidation()) {
            $this->dbRecordForIteration->enableTrustModeForDbData();
        } else {
            $this->dbRecordForIteration->disableTrustModeForDbData();
        }
        return $this->dbRecordForIteration;
    }

    /**
     * @return array
     */
    protected function getRecords() {
        return $this->records;
    }

    /**
     * @param $index
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getRecordDataByIndex($index) {
        if (!$this->offsetExists($index)) {
            throw new \InvalidArgumentException("Array does not contain index '{$index}'");
        }
        return $this->records[$index];
    }

    /**
     * @param bool $disableDataValidationInRecord - true: also disable DB data validation in record to speedup
     * iteration even more
     * @return $this
     */
    public function enableDbRecordInstanceReuseDuringIteration($disableDataValidationInRecord = false) {
        $this->dbRecordInstanceReuseEnabled = true;
        $this->dbRecordInstanceDisablesValidation = (bool)$disableDataValidationInRecord;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableDbRecordInstanceReuseDuringIteration() {
        $this->dbRecordInstanceReuseEnabled = false;
        $this->dbRecordInstanceDisablesValidation = false;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDbRecordInstanceReuseDuringIterationEnabled() {
        return $this->dbRecordInstanceReuseEnabled;
    }

    /**
     * @return mixed
     */
    public function isDbRecordInstanceHasDisabledDbDataValidation() {
        return $this->dbRecordInstanceDisablesValidation;
    }

    /**
     * @return bool|null - null: mixed
     */
    public function isRecordsFromDb() {
        return $this->isFromDb;
    }

    /**
     * @param array $data
     * @return bool
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function autodetectIfRecordIsFromDb(array $data) {
        $pkName = $this->table->getTableStructure()->getPkColumnName();
        return array_key_exists($pkName, $data) && $data[$pkName] !== null;
    }

    /**
     * @return array[]
     */
    public function toArrays() {
        return $this->getRecords();
    }

    /**
     * @return RecordInterface[]
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function toObjects() {
        $count = $this->count();
        if ($count !== count($this->dbRecords)) {
            for ($i = 0; $i < $count; $i++) {
                $this->convertToObject($i);
            }
        }
        return $this->dbRecords;
    }

    /**
     * Get some specific data from each object
     * @param string|\Closure $closureOrObjectsMethod
     *  - string: object's method name. You can provide additional args via $argumentsForMethod
     *  - \Closure: function (RecordInterface $record) { return $record->toArray(); }
     * @param array $argumentsForMethod - pass this arguments to object's method. Not used if $argumentsForMethod is closure
     * @param bool $disableDbRecordDataValidation - true: disable DB data validation in record to speedup
     * @return array
     * @throws \Psr\Log\InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getDataFromEachObject($closureOrObjectsMethod, array $argumentsForMethod = [], $disableDbRecordDataValidation = true) {
        $closure = $closureOrObjectsMethod;
        if (is_string($closure)) {
            $closure = function (RecordInterface $record) use ($closureOrObjectsMethod, $argumentsForMethod) {
                return call_user_func_array([$record, $closureOrObjectsMethod], $argumentsForMethod);
            };
        } else if (!($closure instanceof \Closure)) {
            throw new InvalidArgumentException('$callback argument must be a string (method name) or closure');
        }
        $data = [];
        $backupReuse = $this->dbRecordInstanceReuseEnabled;
        $backupValidation = $this->dbRecordInstanceDisablesValidation;
        $this->enableDbRecordInstanceReuseDuringIteration($disableDbRecordDataValidation);
        for ($i = 0; $i < $this->countTotal(); $i++) {
            $data[] = $closure($this->offsetGet($i));
        }
        $this->dbRecordInstanceReuseEnabled = $backupReuse;
        $this->dbRecordInstanceDisablesValidation = $backupValidation;
        return $data;
    }

    /**
     * @param int $index - record's index
     * @return RecordInterface
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function convertToObject($index) {
        if (empty($this->dbRecords[$index])) {
            $data = $this->getRecordDataByIndex($index);
            $isFromDb = $this->isRecordsFromDb();
            if ($isFromDb === null) {
                $isFromDb = $this->autodetectIfRecordIsFromDb($data);
            }
            $record = $this->table->newRecord();
            $pkColumnName = $this->table->getTableStructure()->getPkColumnName();
            if (!$isFromDb && !empty($data[$pkColumnName])) {
                // primary key value is set but $isFromDb === false. This usually means that all data except
                // primery key is not from db
                $record->updateValue($pkColumnName, $data[$pkColumnName], true);
                unset($data[$pkColumnName]);
                $record->updateValues($data, false);
            } else {
                $record->fromData($data, $isFromDb);
            }
            return $record;
        }
        return $this->dbRecords[$index];
    }

    /**
     * Return the current element
     * @return RecordInterface
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function current() {
        if (!$this->offsetExists($this->position)) {
            return null;
        }
        return $this->offsetGet($this->position);
    }

    /**
     * Move forward to next element
     */
    public function next() {
        $this->position++;
    }

    /**
     * Return the key of the current element
     */
    public function key() {
        return $this->position;
    }

    /**
     * Checks if current position is valid
     * @return boolean - true on success or false on failure.
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function valid() {
        return $this->offsetExists($this->position);
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * Whether a record with specified index exists
     * @param mixed $index - an offset to check for.
     * @return boolean - true on success or false on failure.
     */
    public function offsetExists($index) {
        return array_key_exists($index, $this->getRecords());
    }

    /**
     * @param int $index - The offset to retrieve.
     * @return RecordInterface
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetGet($index) {
        if ($this->isDbRecordInstanceReuseDuringIterationEnabled()) {
            $dbRecord = $this->getDbRecordObjectForIteration();
            if ($this->position !== $this->currentDbRecordIndex) {
                $data = $this->getRecordDataByIndex($this->position);
                $isFromDb = $this->isRecordsFromDb();
                if ($isFromDb === null) {
                    $isFromDb = $this->autodetectIfRecordIsFromDb($data);
                }
                $dbRecord->fromData($data, $isFromDb);
            }
            return $dbRecord;
        } else {
            return $this->convertToObject($index);
        }
    }

    /**
     * Offset to set
     * @param int $index
     * @param mixed $value
     * @throws \BadMethodCallException
     */
    public function offsetSet($index, $value) {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }

    /**
     * Offset to unset
     * @param int $index
     * @throws \BadMethodCallException
     */
    public function offsetUnset($index) {
        throw new \BadMethodCallException('DbRecordSet cannot be modified');
    }

    /**
     * Count elements of an object
     * @return int
     */
    public function count() {
        return count($this->getRecords());
    }

    /**
     * Count elements of an object
     * @return int
     */
    public function countTotal() {
        return $this->count();
    }
}