<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

class TempRecord implements RecordInterface
{
    
    protected array $data = [];
    protected bool $existsInDb = false;
    protected ?string $tableName = null;
    
    public static function newEmptyRecord(): static
    {
        return new static();
    }
    
    public static function newTempRecord(array $data, bool $existsInDb = false, ?string $tableName = null): static
    {
        return static::newEmptyRecord()
            ->fromData($data, $existsInDb)
            ->setTableName($tableName);
    }
    
    public static function getTable(): TableInterface
    {
        throw new \BadMethodCallException('Temp Record has not Table');
    }
    
    /**
     * @param string $name
     * @return bool
     */
    public static function hasColumn(string $name): bool
    {
        return false;
    }
    
    public static function getColumn(string $name, string &$format = null): Column
    {
        throw new \BadMethodCallException('TempRecord has no Columns');
    }
    
    public function setTableName(string $name): static
    {
        $this->tableName = $name;
        return $this;
    }
    
    public function getTableName(): ?string
    {
        return $this->tableName;
    }
    
    public function reset(): static
    {
        $this->data = [];
        return $this;
    }
    
    public function getValue(string|Column $column, ?string $format = null): mixed
    {
        return $this->data[$column] ?? null;
    }
    
    public function hasValue(string|Column $column, bool $trueIfThereIsDefaultValue = false): bool
    {
        return array_key_exists($column, $this->data);
    }
    
    public function updateValue(string|Column $column, mixed $value, bool $isFromDb): static
    {
        $this->data[$column] = $value;
        return $this;
    }
    
    public function getPrimaryKeyValue(): int|float|string|null
    {
        return null;
    }
    
    public function hasPrimaryKeyValue(): bool
    {
        return false;
    }
    
    public function existsInDb(bool $useDbQuery = false): bool
    {
        return $this->existsInDb;
    }
    
    public function setExistsInDb(bool $exists): static
    {
        $this->existsInDb = $exists;
        return $this;
    }
    
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false): RecordsSet|RecordsArray|RecordInterface
    {
        throw new \BadMethodCallException('TempRecord has no Relations');
    }
    
    public function readRelatedRecord(string $relationName): static
    {
        return $this;
    }
    
    public function isRelatedRecordAttached(string $relationName): bool
    {
        return false;
    }
    
    public function updateRelatedRecord(
        string|Relation $relationName,
        array|RecordInterface|RecordsArray|RecordsSet $relatedRecord,
        ?bool $isFromDb = null,
        bool $haltOnUnknownColumnNames = true
    ): static {
        return $this;
    }
    
    public function unsetRelatedRecord(string $relationName): static
    {
        return $this;
    }
    
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        $this->data = $data;
        $this->setExistsInDb($isFromDb);
        return $this;
    }
    
    public function fromDbData(array $data): static
    {
        return $this->fromData($data, true);
    }
    
    public function fetchByPrimaryKey(int|float|string $pkValue, array $columns = [], array $readRelatedRecords = []): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function reload(array $columns = [], array $readRelatedRecords = []): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function readColumns(array $columns = []): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    public function begin(): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function rollback(): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): void
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true): static
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . get_class($this) . ')');
    }
    
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false,
        bool $withFilesInfo = true
    ): array {
        if (
            empty($columnsNames)
            || (count($columnsNames) === 1 && $columnsNames[0] === '*')
            || in_array('*', $columnsNames, true)
        ) {
            return $this->data;
        } else {
            $ret = [];
            foreach ($columnsNames as $key) {
                $ret[$key] = $this->getValue($key);
            }
            return $ret;
        }
    }
    
    public function toArrayWithoutFiles(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false
    ): array {
        return $this->toArray($columnsNames, $relatedRecordsNames, $loadRelatedRecordsIfNotSet, false);
    }
    
    public function getDefaults(array $columns = [], bool $ignoreColumnsThatCannotBeSetManually = true, bool $nullifyDbExprValues = true): array
    {
        return [];
    }
    
    public function enableReadOnlyMode(): static
    {
        return $this;
    }
    
    public function disableReadOnlyMode(): static
    {
        return $this;
    }
    
    public function isReadOnly(): bool
    {
        return true;
    }
    
    public function enableTrustModeForDbData(): static
    {
        return $this;
    }
    
    public function disableTrustModeForDbData(): static
    {
        return $this;
    }
    
    public function isTrustDbDataMode(): bool
    {
        return true;
    }
    
    public static function getPrimaryKeyColumnName(): string
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . static::class . ')');
    }
    
    public static function hasPrimaryKeyColumn(): bool
    {
        return false;
    }
    
    public static function getPrimaryKeyColumn(): Column
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . static::class . ')');
    }
    
    public static function getRelations(): array
    {
        return [];
    }
    
    public static function hasRelation(string $name): bool
    {
        return false;
    }
    
    public static function getRelation(string $name): Relation
    {
        throw new \BadMethodCallException('Method cannot be used for this class (' . static::class . ')');
    }
    
    public function isCollectingUpdates(): bool
    {
        return false;
    }
}
