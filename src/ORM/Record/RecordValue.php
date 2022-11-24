<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\Column;

class RecordValue
{
    
    protected Column $column;
    protected RecordInterface $record;
    
    protected mixed $value = null;
    protected mixed $rawValue = null;
    protected mixed $oldValue = null;
    
    protected bool $oldValueIsFromDb = false;
    protected bool $isFromDb = false;
    protected bool $hasValue = false;
    protected bool $hasOldValue = false;
    protected bool $isValidated = false;
    protected array $validationErrors = [];
    protected ?bool $isDefaultValueCanBeSet = null;
    protected array $customInfo = [];
    protected ?array $dataForSavingExtender = null;
    
    public static function create(Column $dbTableColumn, RecordInterface $record): static
    {
        return new static($dbTableColumn, $record);
    }
    
    public function __construct(Column $dbTableColumn, RecordInterface $record)
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
        foreach ($this->customInfo as &$value) {
            if (is_object($value)) {
                $value = clone $value;
            }
        }
    }
    
    public function getColumn(): Column
    {
        return $this->column;
    }
    
    public function getRecord(): RecordInterface
    {
        return $this->record;
    }
    
    public function isItFromDb(): bool
    {
        return $this->isFromDb;
    }
    
    public function setIsFromDb(bool $isFromDb): static
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
        return $this->getColumn()->hasDefaultValue();
    }
    
    public function getDefaultValue(): mixed
    {
        return $this->getColumn()->getValidDefaultValue();
    }
    
    /**
     * Return null if there is no default value.
     * When there is no default value this method will avoid validation of a NULL value so that there will be no
     * exception 'default value is not valid' if column is not nullable
     */
    public function getDefaultValueOrNull(): mixed
    {
        return $this->hasDefaultValue() ? $this->getColumn()->getValidDefaultValue() : null;
    }
    
    public function isDefaultValueCanBeSet(): bool
    {
        if ($this->isDefaultValueCanBeSet === null) {
            if (!$this->hasDefaultValue()) {
                return false;
            }
            if ($this->getColumn()->isItPrimaryKey()) {
                return !$this->hasValue && $this->getDefaultValue() instanceof DbExpr;
            }

            return !$this->getRecord()->existsInDb();
        }
        return $this->isDefaultValueCanBeSet;
    }
    
    public function getRawValue(): mixed
    {
        return $this->rawValue;
    }
    
    public function setRawValue(mixed $rawValue, mixed $preprocessedValue, bool $isFromDb): static
    {
        $this->setOldValue($this);
        $this->rawValue = $rawValue;
        $this->value = $preprocessedValue;
        $this->hasValue = true;
        $this->isDefaultValueCanBeSet = null;
        $this->isFromDb = $isFromDb;
        $this->customInfo = [];
        $this->validationErrors = [];
        $this->isValidated = false;
        return $this;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function getValue(): mixed
    {
        if (!$this->hasValue) {
            throw new \BadMethodCallException("Value for {$this->getColumnInfoForException()} is not set");
        }
        return $this->value;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function getValueOrDefault(): mixed
    {
        if ($this->hasValue()) {
            return $this->value;
        }

        if ($this->isDefaultValueCanBeSet()) {
            return $this->getDefaultValue();
        }

        if ($this->hasDefaultValue()) {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} is not set and default value cannot be set because"
                . ' record already exists in DB and there is danger of unintended value overwriting'
            );
        }

        throw new \BadMethodCallException(
            "Value for {$this->getColumnInfoForException()} is not set and default value is not provided"
        );
    }
    
    /**
     * $rawValue needed to verify that valid value once was same as raw value
     * @throws \InvalidArgumentException
     */
    public function setValidValue(mixed $value, mixed $rawValue): static
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
     * @throws \BadMethodCallException
     */
    public function getOldValue(): mixed
    {
        if (!$this->hasOldValue()) {
            throw new \BadMethodCallException("Old value is not set for {$this->getColumnInfoForException()}");
        }
        return $this->oldValue;
    }
    
    public function setOldValue(RecordValue $oldValueObject): static
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
    
    public function setValidationErrors(array $validationErrors): static
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
     * $storeDefaultValueIfUsed = true tells method to save $default value when there were no value for $key before.
     */
    public function getCustomInfo(?string $key = null, mixed $default = null, bool $storeDefaultValueIfUsed = false): mixed
    {
        if ($key === null) {
            return $this->customInfo;
        }

        if (array_key_exists($key, $this->customInfo)) {
            return $this->customInfo[$key];
        }

        if ($default instanceof \Closure) {
            $default = $default($this);
        }
        if ($storeDefaultValueIfUsed) {
            $this->customInfo[$key] = $default;
        }
        return $default;
    }
    
    public function setCustomInfo(array $data): static
    {
        $this->customInfo = $data;
        return $this;
    }
    
    public function addCustomInfo(string $key, mixed $value): static
    {
        $this->customInfo[$key] = $value;
        return $this;
    }
    
    public function removeCustomInfo(?string $key = null): static
    {
        if ($key === null) {
            $this->customInfo = [];
        } else {
            unset($this->customInfo[$key]);
        }
        return $this;
    }
    
    public function setDataForSavingExtender(?array $data): static
    {
        $this->dataForSavingExtender = $data;
        return $this;
    }
    
    public function pullDataForSavingExtender(): ?array
    {
        $data = $this->dataForSavingExtender;
        $this->dataForSavingExtender = null;
        return $data;
    }
    
    /**
     * Collects all properties. Used by Record::serialize()
     */
    public function serialize(): array
    {
        $data = get_object_vars($this);
        unset($data['column'], $data['record']);
        return $data;
    }
    
    /**
     * Sets all properties from $data. Used by Record::unserialize()
     */
    public function unserialize(array $data): void
    {
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
    }
    
    protected function getColumnInfoForException(): string
    {
        $recordClass = get_class($this->getRecord());
        $pk = 'undefined';
        if (!$this->getColumn()->isItPrimaryKey()) {
            try {
                $pk = $this->getRecord()->existsInDb()
                    ? $this->getRecord()->getPrimaryKeyValue()
                    : 'null';
            } catch (\Throwable) {
            }
        }
        return $recordClass . '(#' . $pk . ')->' . $this->getColumn()->getName();
    }
}