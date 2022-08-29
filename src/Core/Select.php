<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;

class Select extends AbstractSelect
{
    
    /**
     * Main table name to select data from
     */
    protected string $tableName;
    protected ?string $dbSchema = null;
    protected string $tableAlias;
    protected DbAdapterInterface $connection;
    
    /**
     * @param string $tableName
     * @param DbAdapterInterface $connection
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function from(string $tableName, DbAdapterInterface $connection)
    {
        return new static($tableName, $connection);
    }
    
    /**
     * @param string $tableName - table name or Table object
     * @param DbAdapterInterface $connection
     * @throws \InvalidArgumentException
     */
    public function __construct(string $tableName, DbAdapterInterface $connection)
    {
        if (empty($tableName)) {
            throw new \InvalidArgumentException('$tableName argument must be a not-empty string');
        }
        $this->tableName = $tableName;
        $this->connection = $connection;
        $this->setTableAlias(StringUtils::classify($tableName));
    }
    
    /**
     * @param string $schema
     * @return static
     * @throws \InvalidArgumentException
     */
    public function setTableSchemaName(string $schema)
    {
        if (empty($schema)) {
            throw new \InvalidArgumentException('$schema argument value cannot be empty');
        } elseif (!$this->getConnection()->isValidDbEntityName($schema)) {
            throw new \InvalidArgumentException("\$schema argument value is invalid: [$schema]");
        }
        $this->dbSchema = $schema;
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
    
    /**
     * @return static
     */
    public function setTableAlias(string $tableAlias)
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
    
    /**
     * @param JoinInfo $joinInfo
     * @param bool $append
     * @return static
     * @throws \InvalidArgumentException
     */
    public function join(JoinInfo $joinInfo, bool $append = true)
    {
        $this->_join($joinInfo, $append);
        return $this;
    }
    
}
