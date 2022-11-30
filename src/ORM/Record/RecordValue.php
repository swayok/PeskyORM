<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

class RecordValue implements RecordValueContainerInterface
{
    protected mixed $rawValue = null;
    /**
     * When true: $value will be used insterad of $rawValue.
     * This required to lower memory usage when raw value is
     * same as normalized value.
     */
    protected bool $ignoreRawValue = false;

    protected mixed $value = null;
    protected bool $hasValue = false;
    protected bool $isFromDb = false;

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

    public function setValue(
        mixed $rawValue,
        mixed $processedValue,
        bool $isFromDb
    ): void {
        if ($this->hasValue()) {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} aready set."
                . ' You need to create a new instance of ' . static::class
                . ' to store different value.'
            );
        }
        $this->value = $processedValue;
        $this->isFromDb = $isFromDb;
        $this->hasValue = true;
        if ($rawValue === $processedValue) {
            $this->ignoreRawValue = true;
        } else {
            $this->rawValue = $rawValue;
        }
    }

    public function getRawValue(): mixed
    {
        $this->assertValueIsSet();
        return $this->ignoreRawValue ? $this->value : $this->rawValue;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function getValue(): mixed
    {
        $this->assertValueIsSet();
        return $this->value;
    }

    protected function assertValueIsSet(): void
    {
        if (!$this->hasValue) {
            throw new \BadMethodCallException(
                "Value for {$this->getColumnInfoForException()} is not set."
            );
        }
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