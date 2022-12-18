<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;

abstract class VirtualTableColumnAbstract extends TableColumnAbstract
{
    final public function getDataType(): string
    {
        return TableColumnDataType::VIRTUAL;
    }

    final public function isReal(): bool
    {
        return false;
    }

    /** @noinspection SenselessMethodDuplicationInspection */
    final public function isPrimaryKey(): bool
    {
        return false;
    }

    public function isNullableValues(): bool
    {
        return true;
    }

    public function isReadonly(): bool
    {
        // not final because it is possible to use virtual column
        // to manage data of other column.
        return true;
    }

    final public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    public function getAutoUpdateForAValue(RecordInterface|array $record): mixed
    {
        throw $this->getIsVirtualException();
    }

    final public function getRelations(): array
    {
        return [];
    }

    final public function hasRelation(string $relationName): bool
    {
        return false;
    }

    final public function getRelation(string $relationName): RelationInterface
    {
        throw $this->getIsVirtualException();
    }

    public function addRelation(RelationInterface $relation): static
    {
        throw $this->getIsVirtualException();
    }

    protected function getIsVirtualException(): TableColumnConfigException
    {
        return new TableColumnConfigException(
            "Column {$this->getNameForException()} is virtual.",
            $this
        );
    }

    final public function isForeignKey(): bool
    {
        return false;
    }

    final public function getForeignKeyRelation(): ?RelationInterface
    {
        return null;
    }

    final public function getDefaultValue(): mixed
    {
        // virtual column cannot have a default value
        return null;
    }

    final public function getValidDefaultValue(): mixed
    {
        // virtual column cannot have a default value
        return null;
    }

    final public function hasDefaultValue(): bool
    {
        return false;
    }

    public function validateValue(
        mixed $value,
        bool $isFromDb = false,
        bool $isForCondition = false
    ): array {
        throw $this->getIsVirtualException();
    }

    public function setValue(
        RecordValueContainerInterface $currentValueContainer,
        mixed $newValue,
        bool $isFromDb,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        throw $this->getIsVirtualException();
    }

    public function normalizeValidatedValue(
        mixed $validatedValue,
        bool $isFromDb
    ): mixed {
        throw $this->getIsVirtualException();
    }
}