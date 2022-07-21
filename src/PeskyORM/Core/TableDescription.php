<?php

namespace PeskyORM\Core;

class TableDescription implements \Serializable
{
    
    /**
     * Name of DB schema that contains this table (PostgreSQL)
     * @var string
     */
    protected $dbSchema;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var ColumnDescription[]
     */
    protected $columns = [];
    
    /**
     * @param string $tableName
     * @param string|null $dbSchema
     */
    public function __construct($tableName, $dbSchema = null)
    {
        $this->name = $tableName;
        $this->dbSchema = $dbSchema;
    }
    
    /**
     * @param ColumnDescription $columnDescription
     * @throws \UnexpectedValueException
     */
    public function addColumn(ColumnDescription $columnDescription)
    {
        if (array_key_exists($columnDescription->getName(), $this->columns)) {
            throw new \UnexpectedValueException("Table description already has description for column '{$columnDescription->getName()}'");
        }
        $this->columns[$columnDescription->getName()] = $columnDescription;
    }
    
    /**
     * @param $columnName
     * @return bool
     */
    public function hasColumn($columnName)
    {
        return !empty($this->columns[$columnName]);
    }
    
    /**
     * @return string
     */
    public function getDbSchema()
    {
        return $this->dbSchema;
    }
    
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @return ColumnDescription[]
     */
    public function getColumns()
    {
        return $this->columns;
    }
    
    /**
     * @return ColumnDescription
     * @throws \InvalidArgumentException
     */
    public function getColumn($name)
    {
        if (!array_key_exists($name, $this->columns)) {
            throw new \InvalidArgumentException("Column '{$name}' does not exist");
        }
        return $this->columns[$name];
    }
    
    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        $data = [
            'name' => $this->getName(),
            'dbSchema' => $this->getDbSchema(),
            'columns' => [],
        ];
        foreach ($this->getColumns() as $columnName => $columnDescription) {
            $data['columns'][$columnName] = serialize($columnDescription);
        }
        return json_encode($data);
    }
    
    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @throws \InvalidArgumentException
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
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