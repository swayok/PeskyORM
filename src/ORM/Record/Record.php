<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\RecordNotFoundException;
use PeskyORM\ORM\RecordsCollection\KeyValuePair;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnClosuresInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Utils\StringUtils;

abstract class Record implements RecordInterface, \Iterator, \Serializable
{
    /**
     * @var TableColumnInterface[]
     */
    private static array $columns = [];
    
    /**
     * @var RecordValue[]
     */
    protected array $values = [];
    
    /**
     * @var Record[]|RecordInterface[]|RecordsSet[]
     */
    protected array $relatedRecords = [];
    
    private ?bool $existsInDb = null;
    
    private ?bool $existsInDbReally = null;
    
    protected bool $isCollectingUpdates = false;
    
    /**
     * Collected when value is updated during $this->isCollectingUpdates === true
     * @var RecordValue[]
     */
    protected array $valuesBackup = [];
    
    protected int $iteratorIdx = 0;
    
    protected bool $trustDbDataMode = false;
    
    protected bool $isReadOnly = false;
    
    protected bool $forbidSaving = false;
    
    protected array $readOnlyData = [];
    
    /**
     * Create new record with values from $data array
     */
    public static function fromArray(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        return static::newEmptyRecord()
            ->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
    }
    
    /**
     * Create new record and load values from DB using $pkValue
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected (see \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::valueIsHeavy()). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     */
    public static function read(mixed $pkValue, array $columns = [], array $readRelatedRecords = []): static
    {
        return static::newEmptyRecord()
            ->fetchByPrimaryKey($pkValue, $columns, $readRelatedRecords);
    }
    
    /**
     * Create new record and find values in DB using $conditionsAndOptions
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected (see \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::valueIsHeavy()). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     */
    public static function find(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []): static
    {
        return static::newEmptyRecord()
            ->fetch($conditionsAndOptions, $columns, $readRelatedRecords);
    }
    
    public static function newEmptyRecord(): static
    {
        return new static();
    }
    
    /**
     * Create new empty record with enabled TrustModeForDbData
     */
    public static function newEmptyRecordForTrustedDbData(): static
    {
        $record = static::newEmptyRecord();
        $record->enableTrustModeForDbData();
        return $record;
    }
    
    /**
     * Create new empty record (shortcut)
     */
    public static function _(): static
    {
        return static::newEmptyRecord();
    }
    
    /**
     * Create new empty record (shortcut)
     */
    public static function new1(): static
    {
        return static::newEmptyRecord();
    }
    
    public function __construct()
    {
    }
    
    public static function getTableStructure(): TableStructureInterface
    {
        return static::getTable()->getStructure();
    }
    
    /**
     * Resets cached columns instances (used for testing only, that's why it is private)
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function resetColumnsCache(): void
    {
        self::$columns = [];
    }
    
    /**
     * @param bool $includeFormats - include columns formats ({column}_as_array, etc.)
     * @return TableColumnInterface[] - key = column name
     */
    public static function getColumns(bool $includeFormats = false): array
    {
        return self::getCachedColumnsOrRelations($includeFormats ? 'columns_and_formats' : 'columns');
    }
    
    /**
     * @return TableColumnInterface[] - key = column name
     */
    public static function getNotPrivateColumns(): array
    {
        return self::getCachedColumnsOrRelations('not_private_columns');
    }
    
    /**
     * @return TableColumnInterface[] - key = column name
     */
    public static function getColumnsThatExistInDb(): array
    {
        return self::getCachedColumnsOrRelations('db_columns');
    }
    
    /**
     * @return TableColumnInterface[] - key = column name
     */
    public static function getColumnsThatDoNotExistInDb(): array
    {
        return self::getCachedColumnsOrRelations('not_db_columns');
    }
    
    private static function getCachedColumnsOrRelations(string $key = 'columns'): array|TableColumnInterface
    {
        // significantly decreases execution time on heavy ORM usage (proved by profilig with xdebug)
        if (!isset(self::$columns[static::class])) {
            $tableStructure = static::getTableStructure();
            $columns = $tableStructure::getColumns();
            self::$columns[static::class] = [
                'columns' => $columns,
                'columns_and_formats' => [],
                'not_private_columns' => array_filter($columns, static function (TableColumnInterface $column) {
                    return !$column->isPrivateValues();
                }),
                'db_columns' => $tableStructure::getRealColumns(),
                'not_db_columns' => $tableStructure::getVirtualColumns(),
                'pk_column' => $tableStructure::getPkColumn(),
                'relations' => $tableStructure::getRelations(),
            ];
            foreach ($columns as $columnName => $column) {
                self::$columns[static::class]['columns_and_formats'][$columnName] = [
                    'format' => null,
                    'column' => $column,
                ];
                /** @var ColumnClosuresInterface $closuresClass */
                foreach ($column->getValueFormattersNames() as $format) {
                    self::$columns[static::class]['columns_and_formats'][$columnName . '_as_' . $format] = [
                        'format' => $format,
                        'column' => $column,
                    ];
                }
            }
        }
        return self::$columns[static::class][$key];
    }
    
    /**
     * @{inheritDoc}
     * @throws \InvalidArgumentException
     */
    public static function getColumn(string $name, string &$format = null): TableColumnInterface
    {
        $columns = static::getColumns(true);
        if (!isset($columns[$name])) {
            throw new \InvalidArgumentException(
                "There is no column '$name' in " . get_class(static::getTableStructure())
            );
        }
        $format = $columns[$name]['format'];
        return $columns[$name]['column'];
    }
    
    public static function hasColumn(string $name): bool
    {
        return static::_hasColumn($name, true);
    }
    
    protected static function _hasColumn(string $name, bool $includeFormatters): bool
    {
        return isset(static::getColumns($includeFormatters)[$name]);
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public static function getPrimaryKeyColumn(): TableColumnInterface
    {
        $column = static::getCachedColumnsOrRelations('pk_column');
        if (!$column) {
            throw new \BadMethodCallException('There is no primary key column in ' . get_class(static::getTableStructure()));
        }
        return $column;
    }
    
    public static function hasPrimaryKeyColumn(): bool
    {
        return (bool)static::getCachedColumnsOrRelations('pk_column');
    }
    
    public static function getPrimaryKeyColumnName(): string
    {
        return static::getPrimaryKeyColumn()
            ->getName();
    }
    
    /**
     * @return RelationInterface[]
     */
    public static function getRelations(): array
    {
        return static::getCachedColumnsOrRelations('relations');
    }
    
    /**
     * @param string $name
     * @return RelationInterface
     * @throws \InvalidArgumentException
     */
    public static function getRelation(string $name): RelationInterface
    {
        $relations = static::getRelations();
        if (!isset($relations[$name])) {
            throw new \InvalidArgumentException(
                "There is no relation '$name' in " . get_class(static::getTableStructure())
            );
        }
        return $relations[$name];
    }
    
    public static function hasRelation(string $name): bool
    {
        return isset(static::getRelations()[$name]);
    }
    
    public function enableTrustModeForDbData(): static
    {
        $this->trustDbDataMode = true;
        return $this;
    }
    
    public function disableTrustModeForDbData(): static
    {
        $this->trustDbDataMode = false;
        return $this;
    }
    
    public function isTrustDbDataMode(): bool
    {
        return $this->trustDbDataMode;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function reset(): static
    {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first'
            );
        }
        $this->values = [];
        $this->valuesBackup = [];
        $this->readOnlyData = [];
        $this->relatedRecords = [];
        $this->iteratorIdx = 0;
        $this->existsInDb = null;
        $this->existsInDbReally = null;
        $this->cleanUpdates();
        return $this;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function resetToDefaults(): static
    {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first'
            );
        }
        foreach (static::getColumns() as $column) {
            if (!$column->isPrimaryKey()) {
                $this->resetValueToDefault($column);
            }
        }
        return $this;
    }
    
    protected function createValueObject(TableColumnInterface $column): RecordValue
    {
        return new RecordValue($column, $this);
    }
    
    protected function resetValue(TableColumnInterface|string $column): static
    {
        unset($this->values[is_string($column) ? $column : $column->getName()]);
        return $this;
    }
    
    /**
     * Clean properties related to updated columns
     */
    protected function cleanUpdates(): void
    {
        $this->valuesBackup = [];
        $this->isCollectingUpdates = false;
    }
    
    /**
     * Warning: do not use it to get/set/check value!
     */
    protected function getValueContainer(TableColumnInterface|string $colNameOrConfig): RecordValue
    {
        return is_string($colNameOrConfig)
            ? $this->getValueContainerByColumnName($colNameOrConfig)
            : $this->getValueContainerByColumnConfig($colNameOrConfig);
    }
    
    /**
     * Warning: do not use it to get/set/check value!
     * @throws \BadMethodCallException
     */
    protected function getValueContainerByColumnName(string $columnName): RecordValue
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode.');
        }
        if (!isset($this->values[$columnName])) {
            $this->values[$columnName] = $this->createValueObject(static::getColumn($columnName));
        }
        return $this->values[$columnName];
    }
    
    /**
     * Warning: do not use it to get/set/check value!
     * @throws \BadMethodCallException
     */
    protected function getValueContainerByColumnConfig(TableColumnInterface $column): RecordValue
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode.');
        }
        $colName = $column->getName();
        if (!isset($this->values[$colName])) {
            $this->values[$colName] = $this->createValueObject($column);
        }
        return $this->values[$colName];
    }
    
    public function getValue(string|TableColumnInterface $column, ?string $format = null): mixed
    {
        if (is_string($column)) {
            $column = static::getColumn($column, $maybeFormat);
            if ($maybeFormat && !$format) {
                $format = $maybeFormat;
            }
        }
        return $this->_getValue($column, $format);
    }
    
    protected function _getValue(TableColumnInterface $column, ?string $format): mixed
    {
        if ($this->isReadOnly()) {
            // todo: add tests for read only mode
            $value = $this->readOnlyData[$column->getName()] ?? null;
            if (empty($format) && $column->isReal()) {
                return $value;
            }

            $valueContainer = $this->createValueObject($column);
            $valueContainer->setValue($value, $value, true);
            return call_user_func(
                $column->getValueGetter(),
                $valueContainer,
                $format
            );
        }

        return call_user_func(
            $column->getValueGetter(),
            $this->getValueContainerByColumnConfig($column),
            $format
        );
    }
    
    public function getValueIfExistsInDb(string $columnName, mixed $default = null): mixed
    {
        return ($this->existsInDb() && isset($this->$columnName)) ? $this->$columnName : $default;
    }
    
    public function hasValue(string|TableColumnInterface $column, bool $trueIfThereIsDefaultValue = false): bool
    {
        return $this->_hasValue(is_string($column) ? static::getColumn($column) : $column, $trueIfThereIsDefaultValue);
    }
    
    /**
     * @param TableColumnInterface $column
     * @param bool $trueIfThereIsDefaultValue - true: returns true if there is no value set but column has default value
     * @return bool
     */
    protected function _hasValue(TableColumnInterface $column, bool $trueIfThereIsDefaultValue): bool
    {
        if ($this->isReadOnly()) {
            return array_key_exists($column->getName(), $this->readOnlyData);
        }

        return call_user_func(
            $column->getValueExistenceChecker(),
            $this->getValueContainerByColumnConfig($column),
            $trueIfThereIsDefaultValue
        );
    }
    
    public function isValueFromDb(TableColumnInterface|string $column): bool
    {
        return $this->getValueContainer($column)
            ->isItFromDb();
    }
    
    /**
     * @{@inheritDoc}
     * @throws \BadMethodCallException
     */
    public function updateValue(string|TableColumnInterface $column, mixed $value, bool $isFromDb): static
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }
        if (is_string($column)) {
            $column = static::getColumn($column);
        }
        return $this->_updateValue($column, $value, $isFromDb);
    }
    
    /**
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    public function _updateValue(TableColumnInterface $column, mixed $value, bool $isFromDb): static
    {
        $valueContainer = $this->getValueContainerByColumnConfig($column);
        if (!$isFromDb && $column->isReadonly()) {
            throw new \BadMethodCallException(
                "It is forbidden to modify or set value of a '{$valueContainer->getColumn()->getName()}' column"
            );
        }
        if ($this->isCollectingUpdates && $isFromDb) {
            throw new \BadMethodCallException('It is forbidden to set value with $isFromDb === true after begin()');
        }
        
        if ($column->isPrimaryKey()) {
            if ($value === null) {
                return $this->unsetPrimaryKeyValue();
            }

            if (!$isFromDb) {
                if ($valueContainer->hasValue() && (string)$value === (string)$valueContainer->getValue()) {
                    // no changes required in this situation
                    return $this;
                }

                throw new \InvalidArgumentException('It is forbidden to change primary key value when $isFromDb === false');
            }
            $this->existsInDb = true;
            $this->existsInDbReally = null;
        } elseif ($isFromDb && !$this->existsInDb()) {
            throw new \InvalidArgumentException(
                "Attempt to set a value for column [{$column->getName()}] with flag \$isFromDb === true while record does not exist in DB"
            );
        }
        $colName = $column->getName();
        $prevPkValue = null;
        // backup existing pk value
        if ($column->isPrimaryKey() && $valueContainer->hasValue() /*&& $valueContainer->isItFromDb()*/) {
            $prevPkValue = $valueContainer->getValue();
        }
        if ($this->isCollectingUpdates && !isset($this->valuesBackup[$colName])) {
            $this->valuesBackup[$colName] = clone $valueContainer;
        }
        call_user_func($column->getValueSetter(), $value, $isFromDb, $valueContainer, $this->isTrustDbDataMode());
        if ($isFromDb) {
            $this->unsetNotRelatedRecordsForColumnAfterValueUpdate($column);
        }
        if (
            $prevPkValue !== null
            && (
                !$valueContainer->hasValue()
                || $prevPkValue !== $valueContainer->getValue()
            )
        ) {
            // this will trigger only when previus pk value (that was from db) was changed or removed
            $this->onPrimaryKeyChangeForRecordReceivedFromDb($prevPkValue);
        }
        return $this;
    }
    
    protected function unsetNotRelatedRecordsForColumnAfterValueUpdate(TableColumnInterface $column): void
    {
        // check if all loaded relations still properly linked and unset relations if not
        $relations = $column->getRelations();
        if (count($relations) > 0) {
            $finalValue = $this->getValue($column);
            // unset relation if relation's linking column value differs from current one
            foreach ($column->getRelations() as $relation) {
                if ($this->isRelatedRecordAttached($relation->getName())) {
                    $relatedRecord = $this->getRelatedRecord($relation->getName(), false);
                    $relatedColumnName = $relation->getForeignColumnName();
                    switch ($relation->getType()) {
                        case RelationInterface::HAS_ONE:
                        case RelationInterface::BELONGS_TO:
                            if (
                                isset($relatedRecord[$relatedColumnName])
                                && $relatedRecord[$relatedColumnName] !== $finalValue
                            ) {
                                $this->unsetRelatedRecord($relation->getName());
                            }
                            break;
                        case RelationInterface::HAS_MANY:
                            /** @var RecordsSet $relatedRecord */
                            if (!$relatedRecord->areRecordsFetchedFromDb()) {
                                // not fetched yet - remove without counting and other testing to prevent
                                // unnecessary DB queries
                                $this->unsetRelatedRecord($relation->getName());
                            } elseif ($relatedRecord->count() > 0) {
                                $firstRelatedRecord = $relatedRecord->first();
                                if (
                                    isset($firstRelatedRecord[$relatedColumnName])
                                    && $firstRelatedRecord[$relatedColumnName] !== $finalValue
                                ) {
                                    $this->unsetRelatedRecord($relation->getName());
                                }
                            }
                            break;
                    }
                }
            }
        }
    }
    
    public function unsetValue(TableColumnInterface|string $column): static
    {
        $oldValueObject = $this->getValueContainer($column);
        if ($oldValueObject->hasValue()) {
            $column = $oldValueObject->getColumn();
            $this->values[$column->getName()] = $this->createValueObject($column);
            if ($column->isPrimaryKey()) {
                $this->onPrimaryKeyChangeForRecordReceivedFromDb(
                    $oldValueObject->getValue()
                );
            }
        }
        return $this;
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function resetValueToDefault(TableColumnInterface|string $column): static
    {
        if (is_string($column)) {
            $column = static::getColumn($column);
        }
        if ($column->isPrimaryKey()) {
            throw new \BadMethodCallException('Record->resetValueToDefault() cannot be applied to primary key column');
        }
        $valueContainer = $this->getValueContainer($column);
        if (!$column->isReadonly() && !$column->isAutoUpdatingValues() && $column->isReal()) {
            $this->updateValue($column, $valueContainer->getDefaultValueOrNull(), false);
        }
        return $this;
    }
    
    /**
     * Unset primary key value
     */
    public function unsetPrimaryKeyValue(): static
    {
        $this->existsInDb = false;
        $this->existsInDbReally = false;
        return $this->unsetValue(static::getPrimaryKeyColumn());
    }
    
    /**
     * Erase related records when primary key received from db was changed or removed + mark all values as
     * received not from db
     * @noinspection PhpUnusedParameterInspection
     */
    protected function onPrimaryKeyChangeForRecordReceivedFromDb(float|int|string|null $prevPkValue): void
    {
        $this->relatedRecords = [];
        $this->existsInDb = null;
        $this->existsInDbReally = null;
        $pkColName = static::getPrimaryKeyColumnName();
        foreach ($this->values as $colName => $valueContainer) {
            if ($colName !== $pkColName && $valueContainer->hasValue()) {
                $valueContainer->setIsFromDb(false);
            }
        }
    }
    
    public function getPrimaryKeyValue(): int|float|string|null
    {
        return $this->_getValue(static::getPrimaryKeyColumn(), null);
    }
    
    public function hasPrimaryKeyValue(): bool
    {
        return $this->_hasValue(static::getPrimaryKeyColumn(), false);
    }
    
    public function existsInDb(bool $useDbQuery = false): bool
    {
        if ($useDbQuery) {
            if ($this->existsInDbReally === null) {
                $this->existsInDb = $this->existsInDbReally = (
                    $this->_hasValue(static::getPrimaryKeyColumn(), false)
                    && $this->_existsInDbViaQuery()
                );
            }
            return (bool)$this->existsInDbReally;
        }

        if ($this->existsInDb === null) {
            $this->existsInDb = (
                $this->_hasValue(static::getPrimaryKeyColumn(), false)
                //            && $this->getValueContainerByColumnConfig($pkColumn)->isItFromDb() //< pk cannot be not from db
                && (!$useDbQuery || $this->_existsInDbViaQuery())
            );
        }
        return (bool)$this->existsInDb;
    }
    
    /**
     * Check if current Record exists in DB using DB query
     */
    protected function _existsInDbViaQuery(): bool
    {
        return static::getTable()
            ->hasMatchingRecord([
                static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue(),
            ]);
    }
    
    /**
     * @{inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function updateRelatedRecord(
        string|RelationInterface $relationName,
        array|RecordInterface|RecordsArray|RecordsSet $relatedRecord,
        ?bool $isFromDb = null,
        bool $haltOnUnknownColumnNames = true
    ): static {
        $relation = is_string($relationName) ? static::getRelation($relationName) : $relationName;
        $relationTable = $relation->getForeignTable();
        if ($relation->getType() === RelationInterface::HAS_MANY) {
            if (is_array($relatedRecord)) {
                $relatedRecord = RecordsSet::createFromArray($relationTable, $relatedRecord, $isFromDb, $this->isTrustDbDataMode());
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
            } elseif (!($relatedRecord instanceof RecordsArray)) {
                throw new \InvalidArgumentException(
                    '$relatedRecord argument for HAS MANY relation must be array or instance of ' . RecordsArray::class
                );
            }
        } elseif (is_array($relatedRecord)) {
            if ($isFromDb === null) {
                $pkName = $relationTable::getPkColumnName();
                $isFromDb = array_key_exists($pkName, $relatedRecord) && $relatedRecord[$pkName] !== null;
            }
            $data = $relatedRecord;
            $relatedRecord = $relationTable->newRecord();
            if ($this->isTrustDbDataMode()) {
                $relatedRecord->enableTrustModeForDbData();
            }
            if ($this->isReadOnly()) {
                $relatedRecord->enableReadOnlyMode();
            }
            if (!empty($data)) {
                $relatedRecord->fromData($data, $isFromDb, $haltOnUnknownColumnNames);
            }
        } elseif ($relatedRecord instanceof self) {
            if ($relatedRecord::getTable()
                    ->getName() !== $relationTable::getName()) {
                throw new \InvalidArgumentException(
                    "\$relatedRecord argument must be an instance of Record class for the '{$relationTable::getName()}' DB table"
                );
            }
        } else {
            throw new \InvalidArgumentException(
                "\$relatedRecord argument must be an array or instance of Record class for the '{$relationTable::getName()}' DB table"
            );
        }
        $this->relatedRecords[$relation->getName()] = $relatedRecord;
        return $this;
    }
    
    public function unsetRelatedRecord(string $relationName): static
    {
        unset($this->relatedRecords[$relationName]);
        return $this;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     * @noinspection NotOptimalIfConditionsInspection
     */
    public function getRelatedRecord(string $relationName, bool $loadIfNotSet = false): RecordsSet|RecordsArray|RecordInterface
    {
        if (!$this->isRelatedRecordAttached($relationName)) {
            if ($loadIfNotSet) {
                $this->readRelatedRecord($relationName);
            } else {
                throw new \BadMethodCallException(
                    "Related record with name '$relationName' is not set and autoloading is disabled"
                );
            }
        } elseif ($this->isReadOnly() && !isset($this->relatedRecords[$relationName])) {
            $this->updateRelatedRecord($relationName, $this->readOnlyData[$relationName], true, true);
        }
        return $this->relatedRecords[$relationName];
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function readRelatedRecord(string $relationName): static
    {
        $relation = static::getRelation($relationName);
        if (!$this->isRelatedRecordCanBeRead($relation)) {
            throw new \BadMethodCallException(
                'Record ' . get_class($this) . " has not enough data to read related record '{$relationName}'. "
                . "You need to provide a value for '{$relation->getLocalColumnName()}' column."
            );
        }
        $fkValue = $this->getValue($relation->getLocalColumnName());
        $relatedTable = $relation->getForeignTable();
        if ($fkValue === null) {
            $relatedRecord = $relatedTable->newRecord();
            if ($this->isReadOnly()) {
                $relatedRecord->enableReadOnlyMode();
            }
        } else {
            $conditions = array_merge(
                [$relation->getForeignColumnName() => $this->getValue($relation->getLocalColumnName())],
                $relation->getAdditionalJoinConditions(true, static::getTable()::getAlias(), $this)
            );
            if ($relation->getType() === RelationInterface::HAS_MANY) {
                $relatedRecord = $relatedTable::select(
                    '*',
                    $conditions,
                    static function (OrmSelect $select) use ($relationName, $relatedTable) {
                        $select
                            ->orderBy($relatedTable::getPkColumnName(), true)
                            ->setTableAlias($relationName);
                    }
                );
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
            } else {
                $relatedRecord = $relatedTable->newRecord();
                $data = $relatedTable::selectOne(
                    '*',
                    $conditions,
                    static function (OrmSelect $select) use ($relationName) {
                        $select->setTableAlias($relationName);
                    }
                );
                if ($this->isReadOnly()) {
                    $relatedRecord->enableReadOnlyMode();
                }
                if (!empty($data)) {
                    $relatedRecord->fromData($data, true, true);
                }
            }
        }
        $this->relatedRecords[$relationName] = $relatedRecord;
        return $this;
    }
    
    /**
     * Testif there are enough data to load related record
     */
    protected function isRelatedRecordCanBeRead(RelationInterface|string $relation): bool
    {
        $relation = $relation instanceof RelationInterface
            ? $relation
            : static::getRelation($relation);
        return $this->hasValue($relation->getLocalColumnName());
    }
    
    public function isRelatedRecordAttached(string $relationName): bool
    {
        static::getRelation($relationName);
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($this->isReadOnly() && isset($this->readOnlyData[$relationName])) {
            return true;
        }
        return isset($this->relatedRecords[$relationName]);
    }
    
    public function fromData(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        $this->reset();
        $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
        return $this;
    }
    
    public function fromDbData(array $data): static
    {
        return $this->fromData($data, true, true);
    }
    
    /**
     * @deprecated
     */
    public function fromPrimaryKey($pkValue, array $columns = [], array $readRelatedRecords = []): static
    {
        return $this->fetchByPrimaryKey($pkValue, $columns, $readRelatedRecords);
    }
    
    /**
     * Fill record values with data fetched from DB by primary key value ($pkValue)
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected (see \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::valueIsHeavy()). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     */
    public function fetchByPrimaryKey(int|float|string $pkValue, array $columns = [], array $readRelatedRecords = []): static
    {
        return $this->fetch([static::getPrimaryKeyColumnName() => $pkValue], $columns, $readRelatedRecords);
    }
    
    /**
     * @deprecated
     */
    public function fromDb(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []): static
    {
        return $this->fetch($conditionsAndOptions, $columns, $readRelatedRecords);
    }
    
    /**
     * Fill record values with data fetched from DB by $conditionsAndOptions
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected (see \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::valueIsHeavy()). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * Note: relations can be loaded via 'CONTAIN' key in $conditionsAndOptions
     * @throws \InvalidArgumentException
     */
    public function fetch(array $conditionsAndOptions, array $columns = [], array $readRelatedRecords = []): static
    {
        if (empty($columns)) {
            $columns = array_keys(static::getColumnsThatExistInDb());
        } else {
            $columns[] = static::getPrimaryKeyColumnName();
        }
        $columnsFromRelations = [];
        $hasManyRelations = [];
        /** @var RelationInterface[] $relations */
        $relations = [];
        foreach ($readRelatedRecords as $relationName => $realtionColumns) {
            if (is_int($relationName)) {
                $relationName = $realtionColumns;
                $realtionColumns = ['*'];
            }
            $relations[$relationName] = static::getRelation($relationName);
            if (static::getRelation($relationName)->getType() === RelationInterface::HAS_MANY) {
                $hasManyRelations[] = $relationName;
            } else {
                $columnsFromRelations[$relationName] = (array)$realtionColumns;
            }
        }
        try {
            $columnsToSelectFromMainTable = array_unique($columns);
        } catch (\Throwable $exception) {
            if (stripos($exception->getMessage(), 'Array to string conversion') !== false) {
                throw new \InvalidArgumentException(
                    '$columns argument contains invalid list of columns. Each value can only be a string or DbExpr object. $columns = '
                    . json_encode($columns, JSON_UNESCAPED_UNICODE)
                );
            }

            throw $exception;
        }
        $record = static::getTable()
            ->selectOne(array_merge($columnsToSelectFromMainTable, $columnsFromRelations), $conditionsAndOptions);
        if (empty($record)) {
            $this->reset();
        } else {
            // clear not existing relations
            foreach ($columnsFromRelations as $relationName => $unused) {
                $fkColName = $relations[$relationName]->getForeignColumnName();
                if (
                    !array_key_exists($relationName, $record)
                    || !array_key_exists($fkColName, $record[$relationName])
                    || $record[$relationName][$fkColName] === null
                ) {
                    $record[$relationName] = [];
                }
            }
            $this->fromDbData($record);
            foreach ($hasManyRelations as $relationName) {
                $this->readRelatedRecord($relationName);
            }
        }
        return $this;
    }
    
    /**
     * Reload data for current record.
     * Note: record must exist in DB
     * Warning: if $columns argument value is empty - even heavy valued columns
     * will be selected (see \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::valueIsHeavy()). To select all columns
     * excluding heavy ones use ['*'] as value for $columns argument
     * @throws RecordNotFoundException
     */
    public function reload(array $columns = [], array $readRelatedRecords = []): static
    {
        if (!$this->existsInDb()) {
            throw new RecordNotFoundException('Record must exist in DB');
        }
        return $this->fetchByPrimaryKey($this->getPrimaryKeyValue(), $columns, $readRelatedRecords);
    }
    
    public function readColumns(array $columns = []): static
    {
        if (!$this->existsInDb()) {
            throw new RecordNotFoundException('Record must exist in DB');
        }
        $data = static::getTable()
            ->selectOne(
                empty($columns) ? '*' : $columns,
                [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]
            );
        if (empty($data)) {
            throw new RecordNotFoundException(
                "Record with primary key '{$this->getPrimaryKeyValue()}' was not found in DB"
            );
        }
        $this->updateValues($data, true);
        return $this;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function updateValues(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        if ($this->isReadOnly()) {
            if (!$isFromDb) {
                throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
            }

            $this->readOnlyData = static::normalizeReadOnlyData($data);
            return $this;
        }
        
        $pkColumn = static::getPrimaryKeyColumn();
        if ($isFromDb && !$this->existsInDb()) {
            // first set pk column value
            if (array_key_exists($pkColumn->getName(), $data)) {
                $this->_updateValue($pkColumn, $data[$pkColumn->getName()], true);
                unset($data[$pkColumn->getName()]);
            } else {
                $recordClass = static::class;
                throw new \InvalidArgumentException(
                    "Values update failed: record {$recordClass} does not exist in DB while \$isFromDb === true."
                    . ' Possibly you\'ve missed a primary key value in $data argument.'
                );
            }
        }
        $columns = static::getColumns();
        $relations = static::getRelations();
        foreach ($data as $columnNameOrRelationName => $value) {
            if (isset($columns[$columnNameOrRelationName])) {
                $this->_updateValue($columns[$columnNameOrRelationName], $value, $isFromDb);
            } elseif (isset($relations[$columnNameOrRelationName])) {
                $this->updateRelatedRecord(
                    $relations[$columnNameOrRelationName],
                    $value,
                    null,
                    $haltOnUnknownColumnNames
                );
            } elseif ($haltOnUnknownColumnNames) {
                $recordClass = static::class;
                $tableStructureClass = get_class(static::getTableStructure());
                throw new \InvalidArgumentException(
                    "\$data argument contains unknown column name or relation name {$recordClass}->{$columnNameOrRelationName}"
                    . ' ($isFromDb: ' . ($isFromDb ? 'true' : 'false') . ').'
                    . ($isFromDb ? " Possibly column '{$columnNameOrRelationName}' exists in DB but not defined in {$tableStructureClass}" : '')
                );
            }
        }
        return $this;
    }
    
    /**
     * Update several values
     * Note: it does not save this values to DB, only stores them locally
     * @param array $data
     * @param bool $isFromDb - true: marks values as loaded from DB
     * @param bool $haltOnUnknownColumnNames - exception will be thrown is there is unknown column names in $data
     * @return static
     */
    public function merge(array $data, bool $isFromDb = false, bool $haltOnUnknownColumnNames = true): static
    {
        return $this->updateValues($data, $isFromDb, $haltOnUnknownColumnNames);
    }
    
    public function isCollectingUpdates(): bool
    {
        return $this->isCollectingUpdates;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function begin(): static
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }

        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException('Attempt to begin collecting changes when already collecting changes');
        }

        if (!$this->existsInDb()) {
            throw new \BadMethodCallException('Trying to begin collecting changes on not existing record');
        }

        $this->isCollectingUpdates = true;
        $this->valuesBackup = [];
        return $this;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function rollback(): static
    {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to rollback changed values: changes collecting was not started'
            );
        }
        if (!empty($this->valuesBackup)) {
            $this->values = array_replace($this->values, $this->valuesBackup);
        }
        $this->cleanUpdates();
        return $this;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function commit(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static
    {
        if (!$this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'It is impossible to commit changed values: changes collecting was not started'
            );
        }
        $columnsToSave = array_keys($this->valuesBackup);
        $this->cleanUpdates();
        $this->saveToDb(array_intersect($columnsToSave, $this->getColumnsNamesWithUpdatableValues()));
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave, $deleteNotListedRelatedRecords);
        }
        return $this;
    }
    
    /**
     * Get names of all columns that can be saved to db
     */
    protected function getColumnsNamesWithUpdatableValues(): array
    {
        $columnsNames = [];
        foreach (static::getColumns() as $columnName => $column) {
            if ($column->isReal() && !$column->isReadonly()) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }
    
    /**
     * Get names of all columns that should automatically update values on each save
     * Note: throws exception if used without begin()
     */
    protected function getAllColumnsWithAutoUpdatingValues(): array
    {
        $columnsNames = [];
        foreach (static::getColumns() as $columnName => $column) {
            if ($column->isAutoUpdatingValues() && $column->isReal()) {
                $columnsNames[] = $columnName;
            }
        }
        return $columnsNames;
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function save(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): static
    {
        if ($this->isCollectingUpdates) {
            throw new \BadMethodCallException(
                'Attempt to save data after begin(). You must call commit() or rollback()'
            );
        }
        $this->saveToDb($this->getColumnsNamesWithUpdatableValues());
        if (!empty($relationsToSave)) {
            $this->saveRelations($relationsToSave, $deleteNotListedRelatedRecords);
        }
        return $this;
    }
    
    /**
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws InvalidDataException
     */
    protected function saveToDb(array $columnsToSave = []): void
    {
        if ($this->isSavingAllowed()) {
            throw new \BadMethodCallException('Record saving was forbidden.');
        }

        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }

        if ($this->isTrustDbDataMode()) {
            throw new \BadMethodCallException('Saving is not alowed when trusted mode for DB data is enabled');
        }

        $isUpdate = $this->existsInDb();
        if (empty($columnsToSave)) {
            // nothing to save
            $this->runColumnSavingExtenders($columnsToSave, [], [], $isUpdate);
            return;
        }
        try {
            $diff = array_diff($columnsToSave, array_keys(static::getColumns()));
        } catch (\Throwable $exception) {
            if (stripos($exception->getMessage(), 'Array to string conversion') !== false) {
                throw new \InvalidArgumentException(
                    '$columnsToSave argument contains invalid list of columns. Each value can only be a string. $columns = '
                    . json_encode($columnsToSave, JSON_UNESCAPED_UNICODE)
                );
            }

            throw $exception;
        }
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains unknown columns: ' . implode(', ', $diff)
            );
        }
        $diff = array_diff($columnsToSave, $this->getColumnsNamesWithUpdatableValues());
        if (count($diff)) {
            throw new \InvalidArgumentException(
                '$columnsToSave argument contains columns that cannot be saved to DB: ' . implode(', ', $diff)
            );
        }
        $data = $this->collectValuesForSave($columnsToSave, $isUpdate);
        $updatedData = [];
        if (!empty($data)) {
            $errors = $this->beforeSave($columnsToSave, $data, $isUpdate);
            if (!empty($errors)) {
                throw new InvalidDataException($errors, $this);
            }
            
            if (!$this->performDataSave($isUpdate, $data)) {
                return;
            }
        }
        // run column saving extenders
        try {
            $this->runColumnSavingExtenders($columnsToSave, $data, $updatedData, $isUpdate);
            $this->cleanCacheAfterSave(!$isUpdate);
            $this->afterSave(!$isUpdate, $columnsToSave);
        } catch (\Throwable $exc) {
            static::getTable()::rollBackTransaction(true);
            throw $exc;
        }
    }
    
    /**
     * @throws \UnexpectedValueException
     */
    protected function performDataSave(bool $isUpdate, array $data): bool
    {
        $table = static::getTable();
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        $success = true;
        try {
            if ($isUpdate) {
                unset($data[static::getPrimaryKeyColumnName()]);
                $updatedData = (array)$table::update(
                    $data,
                    [static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()],
                    true
                );
                if (count($updatedData)) {
                    $updatedData = $updatedData[0];
                    $unknownColumns = array_diff_key($updatedData, static::getColumns());
                    if (count($unknownColumns) > 0) {
                        throw new \UnexpectedValueException(
                            'Database table "' . static::getTableStructure()
                                ->getTableName()
                            . '" contains columns that are not described in ' . get_class(static::getTableStructure())
                            . '. Unknown columns: "' . implode('", "', array_keys($unknownColumns)) . '"'
                        );
                    }
                    $this->updateValues($updatedData, true);
                } else {
                    // this means that record does not exist anymore
                    $this->reset();
                    $success = false;
                    // DO NOT RETURN FROM HERE! Transaction will hang
                }
            } else {
                $this->updateValues($table::insert($data, true, true), true);
            }
        } catch (\Throwable $exc) {
            $table::rollBackTransaction(true);
            throw $exc;
        }
        if (!$alreadyInTransaction) {
            $table::commitTransaction();
        }
        return $success;
    }
    
    /**
     * $columnsToSave passed by reference to be able to add some columns for saving in child classes
     */
    protected function collectValuesForSave(array &$columnsToSave, bool $isUpdate): array
    {
        $data = [];
        // collect values that are not from DB
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            $valueContainer = $this->getValueContainerByColumnConfig($column);
            if (
                $column->isReal()
                && !$column->isPrimaryKey()
                && $this->_hasValue($column, true)
                && !$valueContainer->isItFromDb()
            ) {
                $data[$columnName] = $this->_getValue($column, null);
            }
        }
        if (count($data) === 0) {
            return [];
        }
        // collect auto updates
        $autoUpdatingColumns = $this->getAllColumnsWithAutoUpdatingValues();
        foreach ($autoUpdatingColumns as $columnName) {
            if (!isset($data[$columnName])) {
                $data[$columnName] = static::getColumn($columnName)
                    ->getAutoUpdateForAValue($this);
            }
        }
        // set pk value
        $data[static::getPrimaryKeyColumnName()] = $isUpdate
            ? $this->getPrimaryKeyValue()
            : static::getTable()->getExpressionToSetDefaultValueForAColumn();
        return $data;
    }

    public function getValuesForInsertQuery(array $columnsToSave): array
    {
        $ret = [];
        $existsInDb = $this->existsInDb();
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if (!$column->isReal()) {
                continue;
            }
            $recordValue = $this->getValueContainer($column);
            if (
                $column->isAutoUpdatingValues()
                && (!$existsInDb || !$recordValue->hasValue())
            ) {
                $value = $column->getAutoUpdateForAValue($this);
            } elseif ($recordValue->hasValue()) {
                $value = $recordValue->getValue();
            } elseif ($recordValue->hasDefaultValue()) {
                $value = $recordValue->getDefaultValue();
            } else {
                $value = static::getTable()->getExpressionToSetDefaultValueForAColumn();
            }
            $ret[$columnName] = $value;
        }
        return $ret;
    }

    protected function runColumnSavingExtenders(
        array $columnsToSave,
        array $dataSavedToDb,
        array $updatesReceivedFromDb,
        bool $isUpdate
    ): void {
        $updatedColumns = array_merge(
            array_keys($dataSavedToDb),
            static::getColumnsThatDoNotExistInDb()
        );
        $this->begin();
        foreach ($updatedColumns as $column) {
            if (is_string($column)) {
                $column = static::getColumn($column);
            }
            $valueObject = $this->getValueContainerByColumnConfig($column);
            if ($column->isReal() || $valueObject->hasValue()) {
                call_user_func(
                    $column->getValueSavingExtender(),
                    $valueObject,
                    $isUpdate,
                    $updatesReceivedFromDb
                );
            }
            // to be sure no data remains after extender is used
            $valueObject->pullPayload(
                RecordValueContainerInterface::PAYLOAD_KEY_FOR_VALUE_SAVING_EXTENDER
            );
        }
        if (empty($this->valuesBackup)) {
            // needed to prevent infinite recursion caused by calling this method from saveToDb() method
            $this->cleanUpdates();
        } else {
            $this->commit();
        }
    }
    
    /**
     * Called after all data collected and validated
     * Warning: $data is not modifiable here! Use $this->collectValuesForSave() if you need to modify it.
     * Returns array with errors or empty array when there are no errors
     * @noinspection PhpUnusedParameterInspection
     */
    protected function beforeSave(array $columnsToSave, array $data, bool $isUpdate): array
    {
        return [];
    }
    
    /**
     * Called after successful save() and commit() even if nothing was really saved to database
     * @param bool $isCreated - true: new record was created; false: old record was updated
     * @param array $updatedColumns - list of updated columns
     */
    protected function afterSave(bool $isCreated, array $updatedColumns = []): void
    {
    }
    
    /**
     * Clean cache related to this record after saving its data to DB.
     * Called before afterSave()
     */
    protected function cleanCacheAfterSave(bool $isCreated): void
    {
    }
    
    /**
     * Validate a value.
     * Returns array with errors or empty array when there are no errors.
     */
    public static function validateValue(TableColumnInterface|string $column, $value, bool $isFromDb = false): array
    {
        if (is_string($column)) {
            $column = static::getColumn($column);
        }
        return $column->validateValue($value, $isFromDb, false);
    }
    
    /**
     * @{inheritDoc}
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function saveRelations(array $relationsToSave = [], bool $deleteNotListedRelatedRecords = false): void
    {
        if (!$this->existsInDb()) {
            throw new \BadMethodCallException(
                'It is impossible to save related objects of a record that does not exist in DB'
            );
        }
        $relations = static::getRelations();
        if (count($relationsToSave) === 1 && $relationsToSave[0] === '*') {
            $relationsToSave = array_keys($relations);
        } else {
            try {
                $diff = array_diff($relationsToSave, array_keys($relations));
            } catch (\Throwable $exception) {
                if (stripos($exception->getMessage(), 'Array to string conversion') !== false) {
                    throw new \InvalidArgumentException(
                        '$relationsToSave argument contains invalid list of columns. Each value can only be a string. $relationsToSave = '
                        . json_encode($relationsToSave, JSON_UNESCAPED_UNICODE)
                    );
                }

                throw $exception;
            }
            if (count($diff)) {
                throw new \InvalidArgumentException(
                    '$relationsToSave argument contains unknown relations: ' . implode(', ', $diff)
                );
            }
        }
        foreach ($relationsToSave as $relationName) {
            if ($this->isRelatedRecordAttached($relationName)) {
                $relatedRecord = $this->getRelatedRecord($relationName);
                if ($relations[$relationName]->getType() === $relations[$relationName]::HAS_ONE) {
                    $relatedRecord->updateValue(
                        $relations[$relationName]->getForeignColumnName(),
                        $this->getValue($relations[$relationName]->getLocalColumnName()),
                        false
                    );
                    $relatedRecord->save();
                } elseif ($relations[$relationName]->getType() === $relations[$relationName]::BELONGS_TO) {
                    $relatedRecord->save();
                    $this->updateValue(
                        $relations[$relationName]->getLocalColumnName(),
                        $relatedRecord->getValue($relations[$relationName]->getForeignColumnName()),
                        false
                    );
                    $this->saveToDb([$relations[$relationName]->getLocalColumnName()]);
                } else {
                    $fkColName = $relations[$relationName]->getForeignColumnName();
                    $fkValue = $this->getValue($relations[$relationName]->getLocalColumnName());
                    if ($deleteNotListedRelatedRecords) {
                        $pkValues = [];
                        foreach ($relatedRecord as $recordObj) {
                            if ($recordObj->hasPrimaryKeyValue()) {
                                $pkValues[] = $recordObj->getPrimaryKeyValue();
                            }
                        }
                        // delete related records that are not listed in current records list but exist in DB
                        $conditions = [
                            $fkColName => $fkValue,
                        ];
                        if (!empty($pkValues)) {
                            $conditions[$relations[$relationName]->getForeignTable()
                                ->getPkColumnName() . ' !='] = $pkValues;
                        }
                        $relations[$relationName]->getForeignTable()
                            ->delete($conditions);
                    }
                    foreach ($relatedRecord as $recordObj) {
                        // placed here to avoid uniqueness fails connected to deleted records
                        $recordObj
                            ->updateValue($fkColName, $fkValue, false)
                            ->save();
                    }
                }
            }
        }
    }
    
    /**
     * @{inheritDoc}
     * @throws \BadMethodCallException
     */
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true): static
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }

        if (!$this->hasPrimaryKeyValue()) {
            throw new \BadMethodCallException('It is impossible to delete record has no primary key value');
        }

        $this->beforeDelete();
        $table = static::getTable();
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            $table::delete([static::getPrimaryKeyColumnName() => $this->getPrimaryKeyValue()]);
        } catch (\PDOException $exc) {
            $table::rollBackTransaction(true);
            throw $exc;
        }
        $this->afterDelete(); //< transaction may be closed there
        $this->cleanCacheAfterDelete();
        if (!$alreadyInTransaction && $table::inTransaction()) {
            $table::commitTransaction();
        }
        foreach (static::getColumns() as $column) {
            call_user_func(
                $column->getValueDeleteExtender(),
                $this->getValueContainerByColumnConfig($column),
                $deleteFiles
            );
        }
        // note: related objects delete must be managed only by database relations (foreign keys), not here
        if ($resetAllValuesAfterDelete) {
            $this->reset();
        } else {
            $this->resetValue(static::getPrimaryKeyColumn());
        }
        return $this;
    }
    
    /**
     * To terminate delete - throw exception
     */
    protected function beforeDelete(): void
    {
    }
    
    /**
     * Called after successful delete but before columns values resetted
     * (for child classes)
     */
    protected function afterDelete(): void
    {
    }
    
    /**
     * To clean cached data related to record
     */
    protected function cleanCacheAfterDelete(): void
    {
    }
    
    /**
     * Get required values as array
     * @param array $columnsNames
     *  - empty array: return known values for all columns (unknown = not set or not fetched from DB)
     *  - array: contains index-string, key-string, key-\Closure, key-array pairs:
     *      - '*' as the value for index 0: all known values for record (unknown = not set or not fetched from DB)
     *          Note: private columns / see TableColumn::isValuePrivate() will have null value
     *          Note: heavy column / see TableColumn::isValueHeavy() will have value only if it was fetched from DB
     *      - '*' as key: same as ['*' the value for index 0] variant but will exclude columns listed in value.
     *      - index-string: value is column name or relation name (returns all data from related record)
     *          or 'column_name_as_format' or 'RelationName.relation_column'.
     *      - key-string (renaming): key is a column name and value is a column alias (alters key in resulting array).
     *          Key can be 'column_name_as_format' or 'RelationName.relation_column'.
     *      - key-\Closure (value altering): key is a column name and value is a \Closure that alters column's value.
     *          Key can be 'column_name_as_format' or 'RelationName.relation_column'.
     *          \Closure receives 2 arguments: $value (formatted column's value to alter) and Record $record ($this):
     *              function ($value, Record $record) { return $value; }
     *          \Closure may return \PeskyORM\ORM\RecordsCollection\KeyValuePair object to alter key in resulting array:
     *              function ($value, Record $record) { return KeyValuePair::create('some_other_key', $value); }
     *      - key-\Closure (value adding): key is not a column name and value is a \Closure that generates value for this key.
     *          \Closure receives 1 argument: Record $record ($this):
     *              function (Record $record) { return $record->column_name; }
     *          \Closure may return \PeskyORM\ORM\RecordsCollection\KeyValuePair object to alter key in resulting array (not recommended!):
     *              function (Record $record) { return KeyValuePair::create('some_other_key', $record->column_name); }
     *      - key-array (relation data): key is a relation name and value is an array containing column names
     *          of the related record using same rules as here.
     * @param array $relatedRecordsNames
     *  - empty: do not add any relations
     *  - array: contains index-string, key-string, key-array pairs or single value = '*':
     *      - '*' as the value for index === 0: add all related records
     *          (if $loadRelatedRecordsIfNotSet === false - only already loaded records will be added)
     *      - index-string: value is relation name (returns all data from related record)
     *      - key-array: key is relation name and value is array containing column names of
     *          the related record to return using same rules as for $columnsNames.
     * @param bool $loadRelatedRecordsIfNotSet - true: read all missing related objects from DB
     * @param bool $withFilesInfo - true: add info about files attached to a record (url, path, file_name, full_file_name, ext)
     * @throws \InvalidArgumentException
     */
    public function toArray(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false,
        bool $withFilesInfo = true
    ): array {
        // normalize column names
        if (empty($columnsNames) || (count($columnsNames) === 1 && isset($columnsNames[0]) && $columnsNames[0] === '*')) {
            $columnsNames = array_keys(static::getNotPrivateColumns());
        } elseif (in_array('*', $columnsNames, true)) {
            $excludeDuplicatesFromWildcard = [];
            foreach ($columnsNames as $index => $columnName) {
                if (is_string($index)) {
                    $excludeDuplicatesFromWildcard[] = $index;
                } elseif (is_string($columnName)) {
                    $excludeDuplicatesFromWildcard[] = $columnName;
                    if ($columnName === '*') {
                        unset($columnsNames[$index]);
                    }
                }
            }
            $wildcardColumns = array_diff(array_keys(static::getNotPrivateColumns()), $excludeDuplicatesFromWildcard);
            $columnsNames = array_merge($wildcardColumns, $columnsNames);
        } elseif (isset($columnsNames['*'])) {
            // exclude some columns from wildcard
            $columnsNames = array_merge(
                array_diff(array_keys(static::getNotPrivateColumns()), (array)$columnsNames['*']),
                $columnsNames
            );
            unset($columnsNames['*']);
        }
        // normalize relation names
        if (
            array_key_exists(0, $relatedRecordsNames)
            && count($relatedRecordsNames) === 1
            && $relatedRecordsNames[0] === '*'
        ) {
            $relatedRecordsNames = array_keys(
                static::getTableStructure()
                    ->getRelations()
            );
            if (!$loadRelatedRecordsIfNotSet) {
                if ($this->isReadOnly()) {
                    $relatedRecordsNames = array_intersect(
                        $relatedRecordsNames,
                        array_merge(array_keys($this->relatedRecords), array_keys($this->readOnlyData))
                    );
                } else {
                    $relatedRecordsNames = array_keys($this->relatedRecords);
                }
            }
        }
        // collect data for columns
        $data = [];
        foreach ($columnsNames as $index => $columnName) {
            $columnAlias = null; //< to be safe
            $isset = null; //< to be safe
            if (
                (!is_int($index) && (is_array($columnName) || $columnName === '*' || static::hasRelation($index)))
                || (is_string($columnName) && static::hasRelation($columnName))
            ) {
                // it is actually relation
                if (is_int($index)) {
                    // get all data from related record
                    $relatedRecordsNames[] = $columnName;
                } else {
                    // get certain data form related record
                    $relatedRecordsNames[$index] = (array)$columnName;
                }
            } else {
                if ($columnName instanceof \Closure) {
                    $valueModifier = $columnName;
                    $columnName = $columnAlias = $index;
                } else {
                    $columnAlias = $columnName;
                    if (!is_int($index)) {
                        $columnName = $index;
                    }
                    $valueModifier = null;
                }
                if (!static::hasColumn($columnName) && count($parts = explode('.', $columnName)) > 1) {
                    // $columnName = 'Relaion.column' or 'Relation.Subrelation.column'
                    $value = $this->getNestedValueForToArray(
                        $parts,
                        $columnAlias,
                        $valueModifier,
                        $loadRelatedRecordsIfNotSet,
                        !$withFilesInfo,
                        $isset,
                        true
                    );
                } else {
                    $value = $this->getColumnValueForToArray(
                        $columnName,
                        $columnAlias,
                        $valueModifier,
                        !$withFilesInfo,
                        $isset,
                        true
                    );
                }
                // $columnAlias may be modified in $this->getColumnValueForToArray()
                $data[$columnAlias] = $value;
                if ($isset === false) {
                    unset($data[$columnAlias]);
                }
            }
        }
        // collect data for relations
        foreach ($relatedRecordsNames as $relatedRecordName => $relatedRecordColumns) {
            if (is_int($relatedRecordName)) {
                $relatedRecordName = $relatedRecordColumns;
                $relatedRecordColumns = [];
            }
            if (!is_array($relatedRecordColumns)) {
                throw new \InvalidArgumentException(
                    "Columns list for relation '{$relatedRecordName}' must be an array. "
                    . gettype($relatedRecordName) . ' given.'
                );
            }
            $relatedRecord = $this->getRelatedRecord($relatedRecordName, $loadRelatedRecordsIfNotSet);
            if ($relatedRecord instanceof self) {
                // ignore related records without non-default data
                if ($relatedRecord->existsInDb()) {
                    $data[$relatedRecordName] = $withFilesInfo
                        ? $relatedRecord->toArray($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet)
                        : $relatedRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                } elseif ($relatedRecord->hasAnyNonDefaultValues()) {
                    // return related record only if there are any non-default value (column that do not exist in db are ignored)
                    $data[$relatedRecordName] = $relatedRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                }
            } else {
                /** @var RecordsSet $relatedRecord */
                $relatedRecord->enableDbRecordInstanceReuseDuringIteration();
                if ($this->isTrustDbDataMode()) {
                    $relatedRecord->disableDbRecordDataValidation();
                }
                $data[$relatedRecordName] = [];
                foreach ($relatedRecord as $relRecord) {
                    $data[$relatedRecordName][] = $withFilesInfo
                        ? $relRecord->toArray($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet)
                        : $relRecord->toArrayWithoutFiles($relatedRecordColumns, [], $loadRelatedRecordsIfNotSet);
                }
                $relatedRecord->disableDbRecordInstanceReuseDuringIteration();
            }
        }
        return $data;
    }
    
    protected function hasAnyNonDefaultValues(): bool
    {
        $columnsNames = static::getColumns();
        foreach ($columnsNames as $column) {
            if ($column->isReal() && $this->hasValue($column, false)) {
                return true;
            }
        }
        return false;
    }
    
    public function getAllNonDefaultValues(): array
    {
        $columnsNames = static::getColumns();
        $ret = [];
        foreach ($columnsNames as $columnName => $column) {
            if ($column->isReal() && $this->hasValue($column, false)) {
                $ret[$columnName] = $this->_getValue($column, null);
            }
        }
        return $ret;
    }
    
    /**
     * Get column value if it is set or null in any other cases
     * @param string $columnName
     * @param string|null $columnAlias - it is a reference because it can be altered by KeyValuePair returend from $valueModifier \Closure
     * @param null|\Closure $valueModifier - \Closure to modify value = function ($value, Record $record) { return $value; }
     * @param bool $returnNullForFiles - false: return file information for file column | true: return null for file column
     * @param bool $isset - true: value is set | false: value is not set
     * @param bool $skipPrivateValueCheck - true: return real value even if column is private (TableColumn::isValuePrivate())
     * @return mixed
     */
    protected function getColumnValueForToArray(
        string $columnName,
        ?string &$columnAlias = null,
        ?\Closure $valueModifier = null,
        bool $returnNullForFiles = false,
        ?bool &$isset = null,
        bool $skipPrivateValueCheck = false
    ): mixed {
        $isset = false;
        if ($valueModifier && !static::hasColumn($columnName)) {
            return $this->modifyValueForToArray(null, $columnAlias, null, $valueModifier, $isset);
        }
        $column = static::getColumn($columnName, $format);
        if (!$skipPrivateValueCheck && $column->isPrivateValues()) {
            return null;
        }
        if ($this->isReadOnly()) {
            if (array_key_exists($columnName, $this->readOnlyData)) {
                $isset = true;
                return $this->modifyValueForToArray(
                    $columnName,
                    $columnAlias,
                    $this->readOnlyData[$columnName],
                    $valueModifier
                );
            }

            if ($format) {
                $valueContainer = $this->createValueObject($column);
                $isset = true;
                if (array_key_exists($column->getName(), $this->readOnlyData)) {
                    $value = $this->readOnlyData[$column->getName()];
                    $valueContainer->setValue($value, $value, true);
                    return $this->modifyValueForToArray(
                        $columnName,
                        $columnAlias,
                        call_user_func($column->getValueFormatter(), $valueContainer, $format),
                        $valueModifier
                    );
                }

                return $this->modifyValueForToArray($columnName, $columnAlias, null, $valueModifier);
            }
        }
        if ($column->isFile()) {
            if (!$returnNullForFiles && $this->_hasValue($column, false)) {
                $isset = true;
                return $this->modifyValueForToArray(
                    $columnName,
                    $columnAlias,
                    $this->_getValue($column, $format ?: 'array'),
                    $valueModifier
                );
            }
        } elseif ($this->existsInDb()) {
            if ($this->_hasValue($column, false)) {
                $isset = true;
                $val = $this->_getValue($column, $format);
                return $this->modifyValueForToArray(
                    $columnName,
                    $columnAlias,
                    ($val instanceof DbExpr) ? null : $val,
                    $valueModifier
                );
            }
        } else {
            $isset = true; //< there is always a value when record does not exist in DB
            // if default value not provided directly it is considered to be null when record does not exist is DB
            if ($this->_hasValue($column, true)) {
                $val = $this->_getValue($column, $format);
                return $this->modifyValueForToArray(
                    $columnName,
                    $columnAlias,
                    ($val instanceof DbExpr) ? null : $val,
                    $valueModifier
                );
            }
        }
        return $this->modifyValueForToArray($columnName, $columnAlias, null, $valueModifier, $isset);
    }
    
    /**
     * @param string|null $columnName - when null: calls $valueModifier with only $this argument
     * @param string|null $columnAlias - may be modified by KeyValuePair returned from $valueModifier
     * @param mixed $value
     * @param \Closure|null $valueModifier - \Closure that modifies the value. 2 variants:
     *      - if $columnName is set: function ($value, Record $record) { return $value };
     *      - if $columnName is empty: function (Record $record) { return $record->column };
     *      Both versions may return KeyValuePair object (not recommended if $columnName is empty)
     * @param bool|null $hasValue
     * @return mixed
     */
    protected function modifyValueForToArray(
        ?string $columnName,
        ?string &$columnAlias,
        mixed $value,
        ?\Closure $valueModifier,
        ?bool &$hasValue = null
    ): mixed {
        if (!$valueModifier) {
            return $value;
        }
        $hasValue = true;
        if (empty($columnName)) {
            $value = $valueModifier($this);
        } else {
            $value = $valueModifier($value, $this);
        }
        if ($value instanceof KeyValuePair) {
            $columnAlias = (string)$value->getKey();
            $value = $value->getValue();
        }
        return $value;
    }
    
    /**
     * Get nested value if it is set or null in any other cases
     * @param array $parts - parts of nested path ('Relation.Subrelation.column' => ['Relation', 'Subrelation', 'column']
     * @param string|null $columnAlias - it is a reference because it can be altered by KeyValuePair returend from $valueModifier \Closure
     * @param null|\Closure $valueModifier - \Closure to modify value = function ($value, Record $record) { return $value; }
     * @param bool $loadRelatedRecordsIfNotSet - true: read required missing related objects from DB
     * @param bool $returnNullForFiles - false: return file information for file column | true: return null for file column
     * @param bool|null $isset - true: value is set | false: value is not set
     * @param bool $skipPrivateValueCheck - true: return real value even if column is private (TableColumn::isValuePrivate())
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getNestedValueForToArray(
        array $parts,
        ?string &$columnAlias = null,
        ?\Closure $valueModifier = null,
        bool $loadRelatedRecordsIfNotSet = false,
        bool $returnNullForFiles = false,
        ?bool &$isset = null,
        bool $skipPrivateValueCheck = false
    ): mixed {
        $relationName = array_shift($parts);
        $relatedRecord = $this->getRelatedRecord($relationName, $loadRelatedRecordsIfNotSet);
        if ($relatedRecord instanceof self) {
            // ignore related records without non-default data
            if ($relatedRecord->existsInDb() || $relatedRecord->hasAnyNonDefaultValues()) {
                if (count($parts) === 1) {
                    return $relatedRecord->getColumnValueForToArray(
                        $parts[0],
                        $columnAlias,
                        $valueModifier,
                        $returnNullForFiles,
                        $isset,
                        $skipPrivateValueCheck
                    );
                }

                return $relatedRecord->getNestedValueForToArray(
                    $parts,
                    $columnAlias,
                    $valueModifier,
                    $loadRelatedRecordsIfNotSet,
                    $returnNullForFiles,
                    $isset,
                    $skipPrivateValueCheck
                );
            }
            $isset = false;
            return null;
        }

        // record set - not supported
        throw new \InvalidArgumentException(
            'Has many relations are not supported. Trying to resolve: ' . $relationName . '.' . implode('.', $parts)
        );
    }
    
    /**
     * Get required values as array but exclude file columns
     */
    public function toArrayWithoutFiles(
        array $columnsNames = [],
        array $relatedRecordsNames = [],
        bool $loadRelatedRecordsIfNotSet = false
    ): array {
        return $this->toArray($columnsNames, $relatedRecordsNames, $loadRelatedRecordsIfNotSet, false);
    }
    
    /**
     * Collect default values for the columns
     * Note: if there is no default value for a column - null will be returned
     * Note: this method is not used by ORM
     * @param array $columns - empty: return values for all columns
     * @param bool $ignoreColumnsThatCannotBeSetManually - true: value will not be returned for columns that
     *      - autoupdatable ($column->isAutoUpdatingValue())
     *      - does not exist in DB (!$column->isItExistsInDb())
     *      - value cannot be set or changed (!$column->isValueCanBeSetOrChanged())
     * @param bool $nullifyDbExprValues - true: if default value is DbExpr - replace it by null
     * @return array
     */
    public function getDefaults(
        array $columns = [],
        bool $ignoreColumnsThatCannotBeSetManually = true,
        bool $nullifyDbExprValues = true
    ): array {
        if (count($columns) === 0) {
            $columns = array_keys(static::getColumns());
        }
        $values = [];
        foreach ($columns as $columnName) {
            $column = static::getColumn($columnName);
            if (
                $ignoreColumnsThatCannotBeSetManually
                && (
                    !$column->isReal()
                    || $column->isAutoUpdatingValues()
                    || $column->isReadonly()
                )
            ) {
                continue;
            }

            $values[$columnName] = $this->getValueContainerByColumnConfig($column)
                ->getDefaultValueOrNull();
            if ($nullifyDbExprValues && $values[$columnName] instanceof DbExpr) {
                $values[$columnName] = null;
            }
        }
        return $values;
    }
    
    /**
     * Return the current element
     */
    public function current(): mixed
    {
        $key = $this->key();
        return $key !== null ? $this->getColumnValueForToArray($key) : null;
    }
    
    /**
     * Move forward to next element
     */
    public function next(): void
    {
        $this->iteratorIdx++;
    }
    
    /**
     * Return the key of the current element
     * Returns null on failure.
     */
    public function key(): string|null
    {
        if ($this->valid()) {
            return array_keys(static::getNotPrivateColumns())[$this->iteratorIdx];
        }

        return null;
    }
    
    /**
     * Checks if current position is valid
     */
    public function valid(): bool
    {
        return array_key_exists($this->iteratorIdx, array_keys(static::getNotPrivateColumns()));
    }
    
    /**
     * Rewind the Iterator to the first element
     */
    public function rewind(): void
    {
        $this->iteratorIdx = 0;
    }
    
    /**
     * Proxy to _hasValue() or isRelatedRecordCanBeRead();
     * NOTE: same as isset() when calling isset($record[$columnName]) and also used by empty($record[$columnName])
     * @param string $key - column name or relation name
     * @return boolean - true on success or false on failure.
     * @throws \InvalidArgumentException
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetExists(mixed $key): bool
    {
        if (static::hasColumn($key)) {
            // also handles 'column_as_format'
            $column = static::getColumn($key, $format);
            if (!$this->_hasValue($column, false)) {
                return false;
            }

            return $this->_getValue($column, $format) !== null;
        }

        if (static::hasRelation($key)) {
            if (!$this->isRelatedRecordCanBeRead($key)) {
                return false;
            }
            $record = $this->getRelatedRecord($key, true);
            return $record instanceof RecordInterface ? $record->existsInDb() : ($record->count() > 0);
        }

        $this->throwInvalidColumnOrRelationException($key);
    }
    
    /**
     * @param string $key - column name or column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     * @throws \InvalidArgumentException
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetGet(mixed $key): mixed
    {
        if (static::hasColumn($key)) {
            $column = static::getColumn($key, $format);
            return $this->_getValue($column, $format);
        }

        if (static::hasRelation($key)) {
            return $this->getRelatedRecord($key, true);
        }

        $this->throwInvalidColumnOrRelationException($key);
    }
    
    protected function throwInvalidColumnOrRelationException(string $name): void
    {
        throw new \InvalidArgumentException(
            'There is no column or relation with name [' . $name . '] in ' . static::class
        );
    }
    
    /**
     * @param string $key - column name or relation name
     * @param mixed $value
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }

        if (static::_hasColumn($key, false)) {
            $this->_updateValue(static::getColumn($key), $value, $key === static::getPrimaryKeyColumnName());
        } elseif (static::hasRelation($key)) {
            $this->updateRelatedRecord($key, $value, null);
        } else {
            $this->throwInvalidColumnOrRelationException($key);
        }
    }
    
    /**
     * @param string $key
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function offsetUnset(mixed $key): void
    {
        if ($this->isReadOnly()) {
            throw new \BadMethodCallException('Record is in read only mode. Updates not allowed.');
        }

        if (static::_hasColumn($key, false)) {
            $this->unsetValue($key);
        } elseif (static::hasRelation($key)) {
            $this->unsetRelatedRecord($key);
        } else {
            $this->throwInvalidColumnOrRelationException($key);
        }
    }
    
    /**
     * @param string $name - column name or column name with format (ex: created_at_as_date) or relation name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }
    
    /**
     * @param string $name - 'setColumnName' or 'setRelationName'
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }
    
    /**
     * @param string $name - column name or relation name, can contain formatter name (column_as_formatter)
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }
    
    /**
     * @param string $name - column name or relation name
     */
    public function __unset(string $name): void
    {
        $this->offsetUnset($name);
    }
    
    /**
     * Supports only methods starting with 'set' and ending with column name or relation name
     * @param string $name - something like 'setColumnName' or 'setRelationName'
     * @param array $arguments - 1 required, 2 accepted. 1st - value, 2nd - $isFromDb
     * @return static
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __call(string $name, array $arguments): static
    {
        $isValidName = (bool)preg_match('%^set([A-Z][a-zA-Z0-9]*)$%', $name, $nameParts);
        if (!$isValidName) {
            throw new \BadMethodCallException(
                "Magic method '{$name}(\$value, \$isFromDb = false)' is forbidden. You can magically call only methods starting with 'set', for example: setId(1)"
            );
        }

        if (count($arguments) > 2) {
            throw new \InvalidArgumentException(
                "Magic method '{$name}(\$value, \$isFromDb = false)' accepts only 2 arguments, but " . count($arguments) . ' arguments passed'
            );
        }

        if (array_key_exists(1, $arguments) && !is_bool($arguments[1])) {
            throw new \InvalidArgumentException(
                "2nd argument for magic method '{$name}(\$value, \$isFromDb = false)' must be a boolean and reflects if value received from DB"
            );
        }

        $value = $arguments[0];
        if (static::hasRelation($nameParts[1])) {
            if (
                (
                    !is_array($value)
                    && !is_object($value)
                )
                || (
                    is_object($value)
                    && !($value instanceof self)
                    && !($value instanceof RecordsSet)
                )
            ) {
                throw new \InvalidArgumentException(
                    "1st argument for magic method '{$name}(\$value, \$isFromDb = false)' must be an array or instance of Record class or RecordsSet class"
                );
            }
            $isFromDb = $arguments[1] ?? null;
            $this->updateRelatedRecord($nameParts[1], $value, $isFromDb);
        } else {
            $columnName = StringUtils::toSnakeCase($nameParts[1]);
            if (!static::_hasColumn($columnName, false)) {
                throw new \BadMethodCallException(
                    "Magic method '{$name}(\$value, \$isFromDb = false)' is not linked with any column or relation"
                );
            }
            $column = static::getColumn($columnName);
            $isFromDb = array_key_exists(1, $arguments)
                ? $arguments[1]
                : $column->isPrimaryKey(); //< make pk key be "from DB" by default, or it will crash
            $this->_updateValue($column, $value, $isFromDb);
        }
        return $this;
    }
    
    /**
     * String representation of object
     * Note: it does not save relations to prevent infinite loops
     */
    public function serialize(): string
    {
        $data = [
            'props' => [
                'existsInDb' => $this->existsInDb,
            ],
            'values' => [],
        ];
        foreach ($this->values as $name => $value) {
            $data['values'][$name] = $value->toArray();
        }
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
    
    /**
     * @throws \InvalidArgumentException
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function unserialize(string $serialized): void
    {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        $this->reset();
        foreach ($data['props'] as $name => $value) {
            $this->$name = $value;
        }
        foreach ($data['values'] as $name => $value) {
            $this->getValueContainerByColumnName($name)
                ->fromArray($value);
        }
    }
    
    public function enableReadOnlyMode(): static
    {
        if (!$this->isReadOnly) {
            if ($this->existsInDb()) {
                $this->readOnlyData = $this->toArray([], ['*']);
            } else {
                $this->readOnlyData = $this->getAllNonDefaultValues();
            }
            $this->isReadOnly = true;
        }
        return $this;
    }
    
    public function disableReadOnlyMode(): static
    {
        if ($this->isReadOnly) {
            $this->isReadOnly = false;
            $this->reset();
            if (!empty($this->readOnlyData)) {
                $this->updateValues($this->readOnlyData, !empty($this->readOnlyData[static::getPrimaryKeyColumnName()]));
            }
            $this->readOnlyData = [];
        }
        return $this;
    }
    
    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }
    
    public function forbidSaving(): static
    {
        $this->forbidSaving = true;
        return $this;
    }
    
    public function allowSaving(): static
    {
        $this->forbidSaving = true;
        return $this;
    }
    
    public function isSavingAllowed(): bool
    {
        return !$this->forbidSaving();
    }
    
    /**
     * Normalizes readonly data so that numeric and bool values will not be strings
     */
    public static function normalizeReadOnlyData(array $data): array
    {
        $columns = static::getColumns();
        $relations = static::getRelations();
        foreach ($data as $key => $value) {
            if (isset($columns[$key])) {
                $data[$key] = ColumnValueProcessingHelpers::normalizeValueReceivedFromDb(
                    $value,
                    static::getColumn($key)->getType()
                );
            } elseif (isset($relations[$key])) {
                if (!is_array($value)) {
                    $data[$key] = $value;
                } else {
                    $data[$key] = $relations[$key]->getForeignTable()
                        ->newRecord()
                        ->normalizeReadOnlyData($value);
                }
            }
        }
        return $data;
    }
    
}