<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;

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
     * @var array
     */
    protected $customInfo = [];
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

    public function __clone() {
        if (is_object($this->value)) {
            $this->value = clone $this->value;
        }
        if (is_object($this->rawValue)) {
            $this->rawValue = clone $this->rawValue;
        }
        if (is_object($this->oldValue)) {
            $this->oldValue = clone $this->oldValue;
        }
        foreach ($this->customInfo as $key => &$value) {
            if (is_object($value)) {
                $value = clone $value;
            }
        }
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
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
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
     * @throws \UnexpectedValueException
     */
    public function getDefaultValue() {
        $defaultValue = $this->getColumn()->getDefaultValue(function (DbRecord $record) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return $record::getTable()->getConnection()->getExpressionToSetDefaultValueForAColumn();
        });
        if ($defaultValue instanceof \Closure) {
            $defaultValue = $defaultValue($this->getRecord());
        }
        if (!($defaultValue instanceof DbExpr) && count($this->getColumn()->validateValue($defaultValue, false)) > 0) {
            throw new \UnexpectedValueException('Default column value is not valid');
        }
        return $defaultValue;
    }

    /**
     * @return boolean
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function isDefaultValueCanBeSet() {
        if (!$this->hasDefaultValue()) {
            return false;
        }
        if ($this->getColumn()->isItPrimaryKey()) {
            return $this->getDefaultValue() instanceof DbExpr;
        } else {
            return !$this->getRecord()->existsInDb();
        }
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
        $this->customInfo = [];
        $this->validationErrors = [];
        $this->isValidated = false;
        return $this;
    }

    /**
     * @return mixed
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     */
    public function getValue() {
        if ($this->hasValue()) {
            return $this->hasValue ? $this->value : $this->getDefaultValue();
        } else {
            throw new \BadMethodCallException(
                "Value for column '{$this->getColumn()->getName()}' is not set and default value cannot be set because"
                    . ' record already exists in DB and there is danger of unintended value overwriting'
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
     * @return bool
     */
    public function hasOldValue() {
        return $this->hasOldValue;
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getOldValue() {
        if (!$this->hasOldValue()) {
            throw new \BadMethodCallException('Old value is not set');
        }
        return $this->oldValue;
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
     * @param null|string $key
     * @param mixed|\Closure $default
     * @param bool $storeDefaultValueIfUsed - if default value is used - save it to custom info as new value
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getCustomInfo($key = null, $default = null, $storeDefaultValueIfUsed = false) {
        if ($key === null) {
            return $this->customInfo;
        } else {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('$key argument must be a string');
            }
            if (array_key_exists($key, $this->customInfo)) {
                return $this->customInfo[$key];
            } else {
                if ($default instanceof \Closure) {
                    $default = $default($this);
                }
                if ($storeDefaultValueIfUsed) {
                    $this->customInfo[$key] = $default;
                }
                return $default;
            }
        }
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setCustomInfo(array $data) {
        $this->customInfo = $data;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addCustomInfo($key, $value) {
        $this->customInfo[$key] = $value;
        return $this;
    }

    /**
     * @param null|string $key
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function removeCustomInfo($key = null) {
        if ($key === null) {
            $this->customInfo = [];
        } else {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('$key argument must be a string');
            }
            unset($this->customInfo[$key]);
        }
        return $this;
    }

}