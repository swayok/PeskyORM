<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;

class RecordValue
{
    
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
    /**
     * @var bool
     */
    protected $oldValueIsFromDb = false;
    
    protected $isFromDb = false;
    protected $hasValue = false;
    protected $hasOldValue = false;
    protected $isValidated = false;
    protected $validationErrors = [];
    protected $isDefaultValueCanBeSet = null;
    
    /**
     * @var array
     */
    protected $customInfo = [];
    /**
     * @var array
     */
    protected $dataForSavingExtender;
    /**
     * @var Column
     */
    protected $column;
    /**
     * @var Record
     */
    protected $record;
    
    /**
     * @param Column $dbTableColumn
     * @param Record $record
     * @return static
     */
    static public function create(Column $dbTableColumn, Record $record)
    {
        return new static($dbTableColumn, $record);
    }
    
    /**
     * @param Column $dbTableColumn
     * @param Record $record
     */
    public function __construct(Column $dbTableColumn, Record $record)
    {
        $this->column = $dbTableColumn;
        $this->record = $record;
    }
    
    public function __clone()
    {
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
    
    public function getColumn(): Column
    {
        return $this->column;
    }
    
    public function getRecord(): Record
    {
        return $this->record;
    }
    
    public function isItFromDb(): bool
    {
        return $this->isFromDb;
    }
    
    /**
     * @return $this
     */
    public function setIsFromDb(bool $isFromDb)
    {
        $this->isFromDb = $isFromDb;
        return $this;
    }
    
    public function hasValue(): bool
    {
        return $this->hasValue;
    }
    
    public function hasValueOrDefault(): bool
    {
        return $this->hasValue() || $this->isDefaultValueCanBeSet();
    }
    
    public function hasDefaultValue(): bool
    {
        return $this->getColumn()
            ->hasDefaultValue();
    }
    
    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->getColumn()
            ->getValidDefaultValue();
    }
    
    /**
     * Return null if there is no default value.
     * When there is no default value this method will avoid validation of a NULL value so that there will be no
     * exception 'default value is not valid' if column is not nullable
     * @return mixed
     */
    public function getDefaultValueOrNull()
    {
        return $this->hasDefaultValue() ? $this->getColumn()
            ->getValidDefaultValue() : null;
    }
    
    public function isDefaultValueCanBeSet(): bool
    {
        if ($this->isDefaultValueCanBeSet === null) {
            if (!$this->hasDefaultValue()) {
                return false;
            }
            if ($this->getColumn()
                ->isItPrimaryKey()) {
                return $this->hasValue ? false : ($this->getDefaultValue() instanceof DbExpr);
            } else {
                return !$this->getRecord()
                    ->existsInDb();
            }
        }
        return $this->isDefaultValueCanBeSet;
    }
    
    /**
     * @return mixed
     */
    public function getRawValue()
    {
        return $this->rawValue;
    }
    
    /**
     * @param mixed $rawValue
     * @param mixed $preprocessedValue
     * @param boolean $isFromDb
     * @return $this
     */
    public function setRawValue($rawValue, $preprocessedValue, bool $isFromDb)
    {
        $this->setOldValue($this);
        $this->rawValue = $rawValue;
        $this->value = $preprocessedValue;
        $this->hasValue = true;
        $this->isDefaultValueCanBeSet = null;
        $this->isFromDb = (bool)$isFromDb;
        $this->customInfo = [];
        $this->validationErrors = [];
        $this->isValidated = false;
        return $this;
    }
    
    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getValue()
    {
        if (!$this->hasValue) {
            throw new \BadMethodCallException("Value for {$this->getColumnInfoForException()} is not set");
        }
        return $this->value;
    }
    
    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getValueOrDefault()
    {
        if ($this->hasValue()) {
            return $this->value;
        } elseif ($this->isDefaultValueCanBeSet()) {
            return $this->getDefaultValue();
        } elseif ($this->hasDefaultValue()) {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} is not set and default value cannot be set because"
                . ' record already exists in DB and there is danger of unintended value overwriting'
            );
        } else {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} is not set and default value is not provided"
            );
        }
    }
    
    /**
     * @param mixed $value
     * @param mixed $rawValue - needed to verify that valid value once was same as raw value
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setValidValue($value, $rawValue)
    {
        if ($rawValue !== $this->rawValue) {
            throw new \InvalidArgumentException(
                "\$rawValue argument for {$this->getColumnInfoForException()}"
                . ' must be same as current raw value: ' . var_export($this->rawValue, true)
            );
        }
        $this->value = $value;
        $this->setValidationErrors([]);
        return $this;
    }
    
    public function hasOldValue(): bool
    {
        return $this->hasOldValue;
    }
    
    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function getOldValue()
    {
        if (!$this->hasOldValue()) {
            throw new \BadMethodCallException("Old value is not set for {$this->getColumnInfoForException()}");
        }
        return $this->oldValue;
    }
    
    /**
     * @return $this
     */
    public function setOldValue(RecordValue $oldValueObject)
    {
        if ($oldValueObject->hasValue()) {
            $this->oldValue = $oldValueObject->getValue();
            $this->oldValueIsFromDb = $oldValueObject->isItFromDb();
            $this->hasOldValue = true;
        }
        return $this;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function isOldValueWasFromDb(): bool
    {
        if (!$this->hasOldValue()) {
            throw new \BadMethodCallException("Old value is not set for {$this->getColumnInfoForException()}");
        }
        return $this->oldValueIsFromDb;
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
    
    /**
     * @return $this
     */
    public function setValidationErrors(array $validationErrors)
    {
        $this->validationErrors = $validationErrors;
        $this->isValidated = true;
        return $this;
    }
    
    public function isValidated(): bool
    {
        return $this->isValidated;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function isValid(): bool
    {
        if (!$this->isValidated()) {
            throw new \BadMethodCallException("Value was not validated for {$this->getColumnInfoForException()}");
        }
        return empty($this->validationErrors);
    }
    
    /**
     * @param null|string|int|float $key
     * @param mixed|\Closure $default
     * @param bool $storeDefaultValueIfUsed - if default value is used - save it to custom info as new value
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getCustomInfo($key = null, $default = null, $storeDefaultValueIfUsed = false)
    {
        if ($key === null) {
            return $this->customInfo;
        } else {
            if (!is_string($key) && !is_numeric($key)) {
                throw new \InvalidArgumentException(
                    '$key argument for custom info must be a string or number but ' . gettype($key) . ' received'
                    . " (column: '{$this->getColumnInfoForException()}')"
                );
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
    public function setCustomInfo(array $data)
    {
        $this->customInfo = $data;
        return $this;
    }
    
    /**
     * @param string|int|float $key
     * @param mixed $value
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addCustomInfo($key, $value)
    {
        if (!is_string($key) && !is_numeric($key)) {
            throw new \InvalidArgumentException(
                '$key argument for custom info must be a string or number but ' . gettype($key) . ' received'
                . " (column: '{$this->getColumnInfoForException()}')"
            );
        }
        $this->customInfo[$key] = $value;
        return $this;
    }
    
    /**
     * @param null|string $key
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function removeCustomInfo(?string $key = null)
    {
        if ($key === null) {
            $this->customInfo = [];
        } else {
            if (!is_string($key) && !is_numeric($key)) {
                throw new \InvalidArgumentException(
                    '$key argument for custom info must be a string or number but ' . gettype($key) . ' received'
                    . " (column: '{$this->getColumnInfoForException()}')"
                );
            }
            unset($this->customInfo[$key]);
        }
        return $this;
    }
    
    /**
     * @param array|null $data
     * @return $this
     */
    public function setDataForSavingExtender(?array $data)
    {
        $this->dataForSavingExtender = $data;
        return $this;
    }
    
    /**
     * @return array
     */
    public function pullDataForSavingExtender(): ?array
    {
        $data = $this->dataForSavingExtender;
        $this->dataForSavingExtender = null;
        return $data;
    }
    
    /**
     * Collects all properties. Used by Record::serialize()
     * @return array
     */
    public function serialize(): array
    {
        $data = get_object_vars($this);
        unset($data['column'], $data['record']);
        return $data;
    }
    
    /**
     * Sets all properties from $data. Used by Record::unserialize()
     * @param array $data
     */
    public function unserialize(array $data)
    {
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
    }
    
    protected function getColumnInfoForException(): string
    {
        $recordClass = get_class($this->getRecord());
        $pk = 'undefined';
        if (!$this->getColumn()
            ->isItPrimaryKey()) {
            try {
                $pk = $this->getRecord()
                    ->existsInDb() ? $this->getRecord()
                    ->getPrimaryKeyValue() : 'null';
            } catch (\Throwable $ignore) {
            }
        }
        return $recordClass . '(#' . $pk . ')->' . $this->getColumn()
                ->getName();
    }
}