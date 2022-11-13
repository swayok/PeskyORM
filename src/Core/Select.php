<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\Core\Utils\ArgumentValidators;
use Swayok\Utils\StringUtils;

class Select extends SelectQueryBuilderAbstract
{
    /**
     * Main table name to select data from
     */
    protected string $tableName;
    protected ?string $dbSchema = null;
    protected string $tableAlias;
    protected DbAdapterInterface $connection;
    
    /**
     * @throws \InvalidArgumentException
     */
    public static function from(string $tableName, DbAdapterInterface $connection): static
    {
        return new static($tableName, $connection);
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $tableName, DbAdapterInterface $connection)
    {
        ArgumentValidators::assertNotEmpty('$tableName', $tableName);
        $this->tableName = $tableName;
        $this->connection = $connection;
        $this->setTableAlias(StringUtils::classify($tableName));
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setTableSchemaName(string $tableSchema): static
    {
        ArgumentValidators::assertNotEmpty('$tableSchema', $tableSchema);
        if (!$this->getConnection()->isValidDbEntityName($tableSchema)) {
            throw new \InvalidArgumentException(
                "\$tableSchema argument value is not a valid DB entity name: [$tableSchema]"
            );
        }
        $this->dbSchema = $tableSchema;
        return $this;
    }
    
    public function getTableSchemaName(): ?string
    {
        return $this->dbSchema;
    }
    
    public function getTableName(): string
    {
        return $this->tableName;
    }
    
    public function setTableAlias(string $tableAlias): static
    {
        $this->tableAlias = $tableAlias;
        return $this;
    }
    
    public function getTableAlias(): string
    {
        return $this->tableAlias;
    }
    
    public function getConnection(): DbAdapterInterface
    {
        return $this->connection;
    }
}
