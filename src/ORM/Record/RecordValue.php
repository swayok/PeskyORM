<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

class RecordValue implements RecordValueContainerInterface
{
    protected mixed $value = null;
    protected mixed $rawValue = null;

    protected bool $hasValue = false;
    protected bool $isFromDb = false;
    protected ?bool $isDefaultValueCanBeSet = null;
    protected array $payload = [];

    public function __construct(
        protected TableColumnInterface $column,
        protected RecordInterface $record
    ) {
    }

    public function __clone()
    {
        if (is_object($this->value)) {
            $this->value = clone $this->value;
        }
        if (is_object($this->rawValue)) {
            $this->rawValue = clone $this->rawValue;
        }
        foreach ($this->payload as &$value) {
            if (is_object($value)) {
                $value = clone $value;
            }
        }
    }

    public function getColumn(): TableColumnInterface
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

    public function setIsFromDb(bool $isFromDb): void
    {
        $this->isFromDb = $isFromDb;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    public function hasValueOrDefault(): bool
    {
        // todo: move this to TableColumnAbstract - it should not be here
        return $this->hasValue() || $this->isDefaultValueCanBeSet();
    }

    public function hasDefaultValue(): bool
    {
        // todo: move this to TableColumnAbstract - it should not be here
        return $this->getColumn()->hasDefaultValue();
    }

    public function getDefaultValue(): mixed
    {
        // todo: move this to TableColumnAbstract - it should not be here
        return $this->getColumn()->getValidDefaultValue();
    }

    /**
     * Return null if there is no default value.
     * When there is no default value this method will avoid validation of a NULL value so that there will be no
     * exception 'default value is not valid' if column is not nullable
     */
    public function getDefaultValueOrNull(): mixed
    {
        // todo: move this to TableColumnAbstract - it should not be here
        return $this->hasDefaultValue()
            ? $this->getColumn()->getValidDefaultValue()
            : null;
    }

    public function getRawValue(): mixed
    {
        return $this->rawValue;
    }

    public function setValue(
        mixed $rawValue,
        mixed $processedValue,
        bool $isFromDb
    ): void {
        $this->rawValue = $rawValue;
        $this->value = $processedValue;
        $this->hasValue = true;
        $this->isDefaultValueCanBeSet = null;
        $this->isFromDb = $isFromDb;
        $this->payload = [];
    }

    /**
     * @throws \BadMethodCallException
     */
    public function getValue(): mixed
    {
        if (!$this->hasValue) {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} is not set"
            );
        }
        return $this->value;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function getValueOrDefault(): mixed
    {
        // todo: move implementation to TableColumnAbstract, use delegated call here
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

    protected function isDefaultValueCanBeSet(): bool
    {
        // todo: move to ColumnInterface?
        if ($this->isDefaultValueCanBeSet === null) {
            if (!$this->hasDefaultValue()) {
                return false;
            }
            if ($this->getColumn()->isPrimaryKey()) {
                return (
                    !$this->hasValue
                    && $this->getDefaultValue() instanceof DbExpr
                );
            }

            return !$this->getRecord()->existsInDb();
        }
        return $this->isDefaultValueCanBeSet;
    }

    public function getPayload(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->payload;
        }
        if ($this->hasPayload($key)) {
            return $this->payload[$key];
        }
        return $default;
    }

    public function pullPayload(string $key, mixed $default = null): mixed
    {
        $ret = $this->getPayload($key, $default);
        $this->removePayload($key);
        return $ret;
    }

    public function rememberPayload(
        string $key,
        \Closure $default = null
    ): mixed {
        if ($this->hasPayload($key)) {
            return $this->payload[$key];
        }
        $this->addPayload($key, $default($this));
        return $this->payload[$key];
    }

    public function hasPayload(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }

    public function addPayload(string $key, mixed $value): void
    {
        if (!$this->hasValue() && $this->getColumn()->isReal()) {
            throw new \BadMethodCallException(
                'Adding payload to RecordValue before value is provided is not allowed.'
                . ' Detected in: ' . $this->getColumnInfoForException() . '.'
            );
        }
        $this->payload[$key] = $value;
    }

    public function removePayload(?string $key = null): void
    {
        if ($key === null) {
            $this->payload = [];
        } else {
            unset($this->payload[$key]);
        }
    }

    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['column'], $data['record']);
        return $data;
    }

    public function fromArray(array $data): void
    {
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
    }

    protected function getColumnInfoForException(): string
    {
        $recordClass = get_class($this->getRecord());
        $pk = 'undefined';
        if (!$this->getColumn()->isPrimaryKey()) {
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