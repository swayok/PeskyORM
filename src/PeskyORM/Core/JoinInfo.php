<?php

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;

class JoinInfo extends AbstractJoinInfo
{
    
    /**
     * @param string $joinName
     * @param string $localTableName
     * @param string $localColumnName
     * @param string $joinType
     * @param string $foreignTableName
     * @param string $foreignColumnName
     * @param string|null $localTableSchema
     * @param string|null $foreignTableSchema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public static function create(
        string $joinName,
        string $localTableName,
        string $localColumnName,
        string $joinType,
        string $foreignTableName,
        string $foreignColumnName,
        ?string $localTableSchema = null,
        ?string $foreignTableSchema = null
    ) {
        return new static(
            $joinName,
            $localTableName,
            $localColumnName,
            $joinType,
            $foreignTableName,
            $foreignColumnName,
            $localTableSchema,
            $foreignTableSchema
        );
    }
    
    /**
     * @param string $joinName
     * @param string $localTableName
     * @param string $localColumnName
     * @param string $joinType
     * @param string $foreignTableName
     * @param string $foreignColumnName
     * @param string|null $localTableSchema
     * @param string|null $foreignTableSchema
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $joinName,
        string $localTableName,
        string $localColumnName,
        string $joinType,
        string $foreignTableName,
        string $foreignColumnName,
        ?string $localTableSchema = null,
        ?string $foreignTableSchema = null
    ) {
        parent::__construct($joinName);
        $this
            ->setConfigForLocalTable($localTableName, $localColumnName, $localTableSchema)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignTableName, $foreignColumnName, $foreignTableSchema);
    }
    
    /**
     * @param string $tableName
     * @param string $columnName
     * @param string|null $tableSchema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable(string $tableName, string $columnName, ?string $tableSchema = null)
    {
        $this
            ->setTableName($tableName)
            ->setColumnName($columnName);
        if ($tableSchema) {
            $this->setTableSchema($tableSchema);
        }
        return $this;
    }
    
    /**
     * @param string $foreignTableName
     * @param string $foreignColumnName
     * @param string|null $foreignTableSchema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable(string $foreignTableName, string $foreignColumnName, ?string $foreignTableSchema = null)
    {
        $this
            ->setForeignTableName($foreignTableName)
            ->setForeignColumnName($foreignColumnName);
        if ($foreignTableSchema) {
            $this->setForeignTableSchema($foreignTableSchema);
        }
        return $this;
    }
    
    /**
     * @param string $foreignTableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableName(string $foreignTableName)
    {
        if (empty($foreignTableName)) {
            throw new \InvalidArgumentException('$foreignTableName argument must be a not-empty string');
        }
        $this->foreignTableName = $foreignTableName;
        return $this;
    }
    
    /**
     * @param null|string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableSchema(?string $schema)
    {
        if ($schema !== null && empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->foreignTableSchema = $schema;
        return $this;
    }
    
    /**
     * @param string $tableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableName(string $tableName)
    {
        if (empty($tableName)) {
            throw new \InvalidArgumentException('$tableName argument must be a not-empty string');
        }
        $this->tableName = $tableName;
        if ($this->tableAlias === null) {
            $this->setTableAlias(StringUtils::classify($this->tableName));
        }
        return $this;
    }
    
    /**
     * @param null|string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableSchema(?string $schema)
    {
        if ($schema !== null && empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->tableSchema = $schema;
        return $this;
    }
    
}
