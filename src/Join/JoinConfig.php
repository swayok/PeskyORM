<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\Utils\ArgumentValidators;

class JoinConfig extends NormalJoinConfigAbstract
{

    /**
     * @deprecated
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
    ): static {
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
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable(string $tableName, string $columnName, ?string $tableSchema = null): static
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
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable(
        string $foreignTableName,
        string $foreignColumnName,
        ?string $foreignTableSchema = null
    ): static {
        $this
            ->setForeignTableName($foreignTableName)
            ->setForeignColumnName($foreignColumnName);
        if ($foreignTableSchema) {
            $this->setForeignTableSchema($foreignTableSchema);
        }
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setForeignTableName(string $foreignTableName): static
    {
        if (empty($foreignTableName)) {
            throw new \InvalidArgumentException('$foreignTableName argument must be a not-empty string');
        }
        $this->foreignTableName = $foreignTableName;
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setForeignTableSchema(?string $schema): static
    {
        if ($schema !== null && empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->foreignTableSchema = $schema;
        return $this;
    }

    /**
     * Set source table name and schema
     * @throws \InvalidArgumentException
     */
    public function setTableName(string $tableName, ?string $tableSchema = null): static
    {
        ArgumentValidators::assertNullOrNotEmptyString('$tableName', $tableName);
        $this->tableName = $tableName;
        if ($tableSchema) {
            $this->setTableSchema($tableSchema);
        }
        return $this;
    }

    /**
     * Set source table schema
     */
    public function setTableSchema(?string $tableSchema): static
    {
        ArgumentValidators::assertNullOrNotEmptyString('$tableSchema', $tableSchema);
        $this->tableSchema = $tableSchema;
        return $this;
    }
}
