<?php

namespace PeskyORM\Core;

class DbTableForeignKeyDescription implements \Serializable {

    /**
     * @var string|null
     */
    protected $foreignTableSchema;
    /**
     * @var string
     */
    protected $foreignTableName;
    /**
     * @var string
     */
    protected $foreignTableColumnName;

    /**
     * @param string $foreignTableName
     * @param string $foreignTableColumnName
     * @param string $foreignTableSchema
     */
    public function __construct($foreignTableName, $foreignTableColumnName, $foreignTableSchema = null) {
        $this->foreignTableSchema = $foreignTableSchema;
        $this->foreignTableName = $foreignTableName;
        $this->foreignTableColumnName = $foreignTableColumnName;
    }

    /**
     * @return null|string
     */
    public function getForeignTableSchema() {
        return $this->foreignTableSchema;
    }

    /**
     * @param null|string $foreignTableSchema
     * @return $this
     */
    public function setForeignTableSchema($foreignTableSchema) {
        $this->foreignTableSchema = $foreignTableSchema;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTableName() {
        return $this->foreignTableName;
    }

    /**
     * @param string $foreignTableName
     * @return $this
     */
    public function setForeignTableName($foreignTableName) {
        $this->foreignTableName = $foreignTableName;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTableColumnName() {
        return $this->foreignTableColumnName;
    }

    /**
     * @param string $foreignTableColumnName
     * @return $this
     */
    public function setForeignTableColumnName($foreignTableColumnName) {
        $this->foreignTableColumnName = $foreignTableColumnName;
        return $this;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize() {
        return json_encode(get_object_vars($this));
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
    }
}