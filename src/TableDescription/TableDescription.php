<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

class TableDescription implements TableDescriptionInterface
{
    /**
     * Name of DB schema that contains this table (PostgreSQL)
     */
    protected ?string $dbSchema = null;
    protected string $tableName;
    /**
     * @var ColumnDescriptionInterface[]
     * ['column_name' => ColumnDescriptionInterface, ...]
     */
    protected array $columns = [];
    
    /**
     * @param string $tableName
     * @param string|null $dbSchema
     */
    public function __construct(string $tableName, ?string $dbSchema = null)
    {
        $this->tableName = $tableName;
        $this->dbSchema = $dbSchema;
    }
    
    public function getDbSchema(): ?string
    {
        return $this->dbSchema;
    }
    
    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function hasColumn(string $columnName): bool
    {
        return !empty($this->columns[$columnName]);
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function getColumn($name): ColumnDescriptionInterface
    {
        if (!array_key_exists($name, $this->columns)) {
            throw new \InvalidArgumentException("Column '{$name}' does not exist");
        }
        return $this->columns[$name];
    }

    /**
     * @param ColumnDescriptionInterface $columnDescription
     * @throws \UnexpectedValueException
     */
    public function addColumn(ColumnDescriptionInterface $columnDescription): void
    {
        if (array_key_exists($columnDescription->getName(), $this->columns)) {
            throw new \UnexpectedValueException("Table description already has description for column '{$columnDescription->getName()}'");
        }
        $this->columns[$columnDescription->getName()] = $columnDescription;
    }
    
    public function serialize(): string
    {
        $data = [
            'name' => $this->getTableName(),
            'dbSchema' => $this->getDbSchema(),
            'columns' => [],
        ];
        foreach ($this->getColumns() as $columnName => $columnDescription) {
            $data['columns'][$columnName] = serialize($columnDescription);
        }
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
    
    public function unserialize(string $data): void
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $unserialized = json_decode($data, true);
        if (!is_array($unserialized)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        foreach ($unserialized as $propertyName => $value) {
            $this->$propertyName = $value;
        }
        foreach ($unserialized['columns'] as $columnName => $serializedColumnDescription) {
            $this->columns[$columnName] = unserialize($serializedColumnDescription, [
                'allowed_classes' => [ColumnDescriptionInterface::class]
            ]);
        }
    }
}