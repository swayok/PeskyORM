<?php

namespace PeskyORM\ORM;

class DbRecordsSet implements \ArrayAccess, \Iterator {

    /**
     * @var DbTable
     */
    protected $table;

    /**
     * @var DbSelect
     */
    protected $select;
    /**
     * @var array
     */
    protected $records = null;
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
    protected $singleDbRecordIteration = false;
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
     * @param boolean $isFromDb
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function createFromArray(DbTable $table, array $records, $isFromDb = false) {
        return new static($table, $records, $isFromDb);
    }

    /**
     * @param DbSelect $dbSelect
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function createFromDbSelect(DbSelect $dbSelect) {
        return new static($dbSelect->getTable(), $dbSelect);
    }

    /**
     * @param DbTable $table
     * @param DbSelect|array $dbSelectOrRecords
     * @param bool $isFromDb - true: records are from db. works only if $dbSelectOrRecords is array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function __construct(DbTable $table, $dbSelectOrRecords, $isFromDb = false) {
        $this->table = $table;
        if ($dbSelectOrRecords instanceof DbSelect) {
            $this->select = $dbSelectOrRecords;
            $this->isFromDb = true;
        } else if (is_array($dbSelectOrRecords)) {
            foreach ($dbSelectOrRecords as $record) {
                if (!is_array($record)) {
                    throw new \InvalidArgumentException('$dbSelectOrRecords must contain only arrays');
                }
            }
            $this->records = array_values($dbSelectOrRecords);
            $this->isFromDb = $isFromDb;
            $this->dbRecordForIteration = $this->table->newRecord();
        } else {
            throw new \InvalidArgumentException(
                '$dbSelectOrRecords argument bust be an array or instance of DbSelect class'
            );
        }
    }

    /**
     * @param bool $enable
     */
    public function setSingleDbRecordIterationMode($enable = true) {
        $this->singleDbRecordIteration = (bool)$enable;
    }

    /**
     * @return bool
     */
    public function isSingleRecordIterationModeEnabled() {
        return $this->singleDbRecordIteration;
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
        if (!$this->records && $this->select) {
            $this->records = $this->select->fetchMany();
        }
        return $this->records;
    }

    /**
     * @return DbRecord[]
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function toObjects() {
        $count = count($this->records);
        if ($count !== count($this->dbRecords)) {
            for ($i = 0; $i < $count; $i++) {
                $this->offsetGet($i);
            }
        }
        return $this->records;
    }

    /**
     * Return the current element
     * @return DbRecord
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function current() {
        if (!$this->offsetExists($this->position)) {
            return null;
        }
        if ($this->isSingleRecordIterationModeEnabled()) {
            if ($this->position !== $this->currentDbRecordIndex) {
                $data = $this->getRecordDataByIndex($this->position);
                $this->dbRecordForIteration->fromData($data, $this->isRecordsFromDb());
            }
            return $this->dbRecordForIteration;
        } else {
            return $this->offsetGet($this->position);
        }
    }

    /**
     * Move forward to next element
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
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
        return array_key_exists($index, $this->toArrays());
    }

    /**
     * @param int $index - The offset to retrieve.
     * @return DbRecord
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function offsetGet($index) {
        if (empty($this->dbRecords[$index])) {
            $data = $this->getRecordDataByIndex($index);
            $this->dbRecords[$index] = $this->table->newRecord()
                ->fromData($data, $this->isRecordsFromDb());
        }
        return $this->dbRecords[$index];
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
}