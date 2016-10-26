<?php

namespace PeskyORM\ORM;

class DbRecordsArray implements \ArrayAccess, \Iterator, \Countable  {

    /**
     * @var DbTable
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
     * @var DbRecord[]
     */
    protected $dbRecords = [];
    /**
     * @var bool
     */
    protected $isFromDb = false;
    /**
     * @var bool
     */
    protected $dbRecordInstanceReuseEnabled = false;
    /**
     * @var DbRecord
     */
    protected $dbRecordForIteration = null;
    /**
     * @var int
     */
    protected $currentDbRecordIndex = -1;

    /**
     * @param DbTableInterface $table
     * @param array $records
     * @param bool $isFromDb - true: records are from db. works only if $dbSelectOrRecords is array
     * @throws \InvalidArgumentException
     */
    public function __construct(DbTableInterface $table, array $records, $isFromDb) {
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
     * @return DbRecord
     */
    protected function getDbRecordObjectForIteration() {
        if ($this->dbRecordForIteration === null) {
            $this->dbRecordForIteration = $this->table->newRecord();
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
     * @return $this
     */
    public function enableDbRecordInstanceReuseDuringIteration() {
        $this->dbRecordInstanceReuseEnabled = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableDbRecordInstanceReuseDuringIteration() {
        $this->dbRecordInstanceReuseEnabled = false;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDbRecordInstanceReuseDuringIterationEnabled() {
        return $this->dbRecordInstanceReuseEnabled;
    }

    /**
     * @return bool
     */
    public function isRecordsFromDb() {
        return $this->isFromDb;
    }

    /**
     * @return array[]
     */
    public function toArrays() {
        return $this->getRecords();
    }

    /**
     * @return DbRecord[]
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
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
     * @param int $index - record's index
     * @return DbRecord
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function convertToObject($index) {
        if (empty($this->dbRecords[$index])) {
            return $this->table->newRecord()->fromData($this->getRecordDataByIndex($index), $this->isRecordsFromDb());
        }
        return $this->dbRecords[$index];
    }

    /**
     * Return the current element
     * @return DbRecord
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
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
     * @return DbRecord
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetGet($index) {
        if ($this->isDbRecordInstanceReuseDuringIterationEnabled()) {
            $dbRecord = $this->getDbRecordObjectForIteration();
            if ($this->position !== $this->currentDbRecordIndex) {
                $dbRecord->fromData($this->getRecordDataByIndex($this->position), $this->isRecordsFromDb());
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
}