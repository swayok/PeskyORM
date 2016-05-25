<?php

namespace PeskyORM\ORM;

class DbRecordValue {

    /**
     * @var mixed
     */
    protected $value = null;
    /**
     * @var mixed
     */
    protected $rawValue = null;
    /**
     * @var mixed
     */
    protected $oldValue = null;

    protected $isFromDb = false;
    protected $hasValue = false;
    protected $hasOldValue = false;
    protected $isValidated = false;
    protected $validationErrors = [];

    /**
     * @var mixed
     */
    protected $customInfo = null;
    /**
     * @var DbTableColumn
     */
    protected $column;
    /**
     * @var DbRecord
     */
    protected $record;

    /**
     * @param DbTableColumn $dbTableColumn
     * @param DbRecord $record
     * @return static
     */
    static public function create(DbTableColumn $dbTableColumn, DbRecord $record) {
        return new static($dbTableColumn, $record);
    }

    /**
     * @param DbTableColumn $dbTableColumn
     * @param DbRecord $record
     */
    public function __construct(DbTableColumn $dbTableColumn, DbRecord $record) {
        $this->column = $dbTableColumn;
        $this->record = $record;
    }

    /**
     * @return DbTableColumn
     */
    public function getColumn() {
        return $this->column;
    }

    /**
     * @return DbRecord
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * @return boolean
     */
    public function isItFromDb() {
        return $this->isFromDb;
    }

    /**
     * @param boolean $isFromDb
     * @return $this
     */
    public function setIsFromDb($isFromDb) {
        $this->isFromDb = $isFromDb;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasValue() {
        return $this->hasValue || $this->isDefaultValueCanBeSet();
    }

    /**
     * @return boolean
     */
    public function hasDefaultValue() {
        return $this->getColumn()->hasDefaultValue();
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getDefaultValue() {
        if (!$this->hasDefaultValue()) {
            throw new \BadMethodCallException("Column '{$this->getColumn()->getName()}' has no default value");
        }
        return $this->getColumn()->getDefaultValue();
    }

    /**
     * @return boolean
     */
    public function isDefaultValueCanBeSet() {
        return $this->hasDefaultValue()
            && !$this->getColumn()->isItPrimaryKey()
            && !$this->getRecord()->existsInDb();
    }

    /**
     * @return mixed
     */
    public function getRawValue() {
        return $this->rawValue;
    }

    /**
     * @param mixed $rawValue
     * @param mixed $preprocessedValue
     * @param boolean $isFromDb
     * @return $this
     */
    public function setRawValue($rawValue, $preprocessedValue, $isFromDb) {
        if ($this->hasValue) {
            $this->hasOldValue = true;
            $this->oldValue = $this->value;
        }
        $this->rawValue = $rawValue;
        $this->value = $preprocessedValue;
        $this->hasValue = true;
        $this->isFromDb = (bool)$isFromDb;
        $this->customInfo = null;
        $this->validationErrors = [];
        $this->isValidated = false;
        return $this;
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getValue() {
        if ($this->hasValue()) {
            return $this->hasValue ? $this->value : $this->getDefaultValue();
        } else {
            throw new \BadMethodCallException(
                "Value for column '{$this->getColumn()->getName()}' is not set and default value "
                . (!$this->hasDefaultValue() ? 'is not provided' : 'cannot be set because record exists in DB')
            );
        }
    }

    /**
     * @param mixed $value
     * @param mixed $rawValue - needed to verify that valid value once was same as raw value
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setValidValue($value, $rawValue) {
        if (!$this->hasValue || $rawValue !== $this->rawValue) {
            throw new \InvalidArgumentException(
                "$rawValue argument must be same as current raw value: '{$this->rawValue}'"
            );
        }
        $this->value = $value;
        $this->validationErrors = [];
        return $this;
    }

    /**
     * @return array
     */
    public function getValidationErrors() {
        return $this->validationErrors;
    }

    /**
     * @param array $validationErrors
     * @return $this
     */
    public function setValidationErrors(array $validationErrors) {
        $this->validationErrors = $validationErrors;
        $this->isValidated = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return empty($this->validationErrors);
    }

    /**
     * @return mixed
     */
    public function getCustomInfo() {
        return $this->customInfo;
    }

    /**
     * @param mixed $customInfo
     * @return $this
     */
    public function setCustomInfo($customInfo) {
        $this->customInfo = $customInfo;
        return $this;
    }

}