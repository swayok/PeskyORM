<?php

namespace PeskyORM\ORM;

class DbRecordsSet implements \ArrayAccess, \Iterator {

    /**
     * @var DbTable
     */
    protected $table;

    /**
     * @var OrmSelect
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
     * @param boolean $isFromDb
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function createFromArray(DbTableInterface $table, array $records, $isFromDb = false) {
        return new static($table, $records, $isFromDb);
    }

    /**
     * @param OrmSelect $dbSelect
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function createFromOrmSelect(OrmSelect $dbSelect) {
        return new static($dbSelect->getTable(), $dbSelect);
    }

    /**
     * @param DbTableInterface $table
     * @param OrmSelect|array $dbSelectOrRecords
     * @param bool $isFromDb - true: records are from db. works only if $dbSelectOrRecords is array
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function __construct(DbTableInterface $table, $dbSelectOrRecords, $isFromDb = false) {
        $this->table = $table;
        if ($dbSelectOrRecords instanceof OrmSelect) {
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
                '$dbSelectOrRecords argument bust be an array or instance of OrmSelect class'
            );
        }
    }

    /**
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function nextPage() {
        if (!$this->select) {
            throw new \BadMethodCallException('Pagination is impossible without OrmSelect instance');
        }
        $this->rewind();
        $this->records = $this->select->fetchNextPage();
        return $this;
    }

    /**
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function prevPage() {
        if (!$this->select) {
            throw new \BadMethodCallException('Pagination is impossible without OrmSelect instance');
        }
        $this->rewind();
        $this->records = $this->select->fetchPrevPage();
        return $this;
    }

    // todo: add possibility to fetch records via OrmSelect until there is no more records in db (using nextPage or prevPage)
    // this should be enabled/disabled via a method like [enable/disable]IterationOverAllMatchingRecords()

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
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function toArrays() {
        if (!$this->records && $this->select) {
            $this->records = $this->select->fetchMany();
        }
        return $this->records;
    }

    /**
     * @return DbRecord[]
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
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
     * @throws \PDOException
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
        if ($this->isDbRecordInstanceReuseDuringIterationEnabled()) {
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
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function offsetExists($index) {
        return array_key_exists($index, $this->toArrays());
    }

    /**
     * @param int $index - The offset to retrieve.
     * @return DbRecord
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
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
     * @throws \UnexpectedValueException
     * @throws \PDOException
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