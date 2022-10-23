<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

class TableDescription implements \Serializable
{
    
    /**
     * Name of DB schema that contains this table (PostgreSQL)
     */
    protected ?string $dbSchema = null;
    protected string $tableName;
    /**
     * @var ColumnDescription[]
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
    
    /**
     * @param ColumnDescription $columnDescription
     * @throws \UnexpectedValueException
     */
    public function addColumn(ColumnDescription $columnDescription): void
    {
        if (array_key_exists($columnDescription->getName(), $this->columns)) {
            throw new \UnexpectedValueException("Table description already has description for column '{$columnDescription->getName()}'");
        }
        $this->columns[$columnDescription->getName()] = $columnDescription;
    }
    
    public function hasColumn(string $columnName): bool
    {
        return !empty($this->columns[$columnName]);
    }
    
    public function getDbSchema(): ?string
    {
        return $this->dbSchema;
    }
    
    public function getTableName(): string
    {
        return $this->tableName;
    }
    
    /**
     * @return ColumnDescription[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function getColumn($name): ColumnDescription
    {
        if (!array_key_exists($name, $this->columns)) {
            throw new \InvalidArgumentException("Column '{$name}' does not exist");
        }
        return $this->columns[$name];
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
    
    public function unserialize(string $serialized): void
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
        foreach ($data['columns'] as $columnName => $serializedColumnDescription) {
            $this->columns[$columnName] = unserialize($serializedColumnDescription, ['allowed_classes' => [ColumnDescription::class]]);
        }
    }
}