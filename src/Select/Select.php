<?php

declare(strict_types=1);

namespace PeskyORM\Select;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Utils\ArgumentValidators;

class Select extends SelectQueryBuilderAbstract
{
    /**
     * Main table name to select data from
     */
    protected string $tableName;
    protected ?string $dbSchema = null;
    protected DbAdapterInterface $connection;
    protected ?DbExpr $appendedDbExpr = null;

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
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function setTableSchemaName(string $tableSchema): static
    {
        ArgumentValidators::assertNotEmpty('$tableSchema', $tableSchema);
        if (!$this->getConnection()->isValidDbEntityName($tableSchema)) {
            throw new \InvalidArgumentException(
                "\$tableSchema argument value '$tableSchema' is not a valid DB entity name"
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
    
    public function getConnection(): DbAdapterInterface
    {
        return $this->connection;
    }

    public function appendDbExpr(?DbExpr $dbExpr): static
    {
        $this->appendedDbExpr = $dbExpr?->setWrapInBrackets(false);
        return $this;
    }

    protected function buildQueryPartsAfterSelectColumns(
        bool $ignoreLeftJoins,
        bool $withOrderBy,
        bool $withLimitAndOffset
    ): string {
        $queryParts = parent::buildQueryPartsAfterSelectColumns($ignoreLeftJoins, $withOrderBy, $withLimitAndOffset);
        if ($this->appendedDbExpr) {
            $queryParts.= ' ' . $this->quoteDbExpr($this->appendedDbExpr);
        }
        return $queryParts;
    }

}
