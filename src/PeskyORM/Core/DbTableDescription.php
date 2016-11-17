<?php

namespace PeskyORM\Core;

class DbTableDescription implements \Serializable {

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
     * @var DbTableColumnDescription[]
     */
    protected $columns = [];
    /**
     * @var DbJoinConfig[]
     */
    protected $foreignKeys = [];

    /**
     * @param string $tableName
     * @param string|null $dbSchema
     */
    public function __construct($tableName, $dbSchema = null) {
        $this->name = $tableName;
        $this->dbSchema = $dbSchema;
    }

    /**
     * @param DbTableColumnDescription $columnDescription
     * @throws \UnexpectedValueException
     */
    public function addColumn(DbTableColumnDescription $columnDescription) {
        if (array_key_exists($columnDescription->getName(), $this->columns)) {
            throw new \UnexpectedValueException("Table description already has description for column '{$columnDescription->getName()}'");
        }
        $this->columns[$columnDescription->getName()] = $columnDescription;
    }

    /**
     * @param $columnName
     * @param DbTableForeignKeyDescription $foreignKeyDescription
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function addForeignKeyForColumn($columnName, DbTableForeignKeyDescription $foreignKeyDescription) {
        if (!array_key_exists($columnName, $this->columns)) {
            throw new \InvalidArgumentException("Table has no column '{$columnName}'");
        }
        if (array_key_exists($columnName, $this->foreignKeys)) {
            throw new \UnexpectedValueException("Column {$columnName} already has a foreign key");
        }
        $this->foreignKeys[$columnName] = $foreignKeyDescription;
    }

    /**
     * @return string
     */
    public function getDbSchema() {
        return $this->dbSchema;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return DbTableColumnDescription[]
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * @return DbTableColumnDescription
     * @throws \InvalidArgumentException
     */
    public function getColumn($name) {
        if (!array_key_exists($name, $this->columns)) {
            throw new \InvalidArgumentException("Column '{$name}' does not exist");
        }
        return $this->columns[$name];
    }

    /**
     * @return DbJoinConfig[]
     */
    public function getForeignKeys() {
        return $this->foreignKeys;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize() {
        $data = [
            'name' => $this->getName(),
            'dbSchema' => $this->getDbSchema(),
            'columns' => [],
            'relations' => []
        ];
        foreach ($this->getColumns() as $columnName => $columnDescription) {
            $data['columns'][$columnName] = serialize($columnDescription);
        }
        foreach ($this->getForeignKeys() as $relationName => $relationDescription) {
            $data['relations'][$relationName] = serialize($relationDescription);
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
    public function unserialize($serialized) {
        $data = json_decode($serialized, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('$serialized argument must be a json-encoded array');
        }
        foreach ($data as $propertyName => $value) {
            $this->$propertyName = $value;
        }
        foreach ($this->columns as $columnName => $serializedForeignKey) {
            $this->columns[$columnName] = unserialize($serializedForeignKey);
        }
        foreach ($this->foreignKeys as $columnName => $serializedForeignKey) {
            $this->foreignKeys[$columnName] = unserialize($serializedForeignKey);
        }
    }
}