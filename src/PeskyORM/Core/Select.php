<?php

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;

class Select extends AbstractSelect {

    /**
     * Main table name to select data from
     * @var string
     */
    protected $tableName;
    /**
     * @var string|null
     */
    protected $dbSchema;
    /**
     * @var string
     */
    protected $tableAlias;
    /**
     * @var DbAdapterInterface
     */
    protected $connection;

    /**
     * @param string $tableName
     * @param DbAdapterInterface $connection
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function from(string $tableName, DbAdapterInterface $connection) {
        return new static($tableName, $connection);
    }

    /**
     * @param string $tableName - table name or Table object
     * @param DbAdapterInterface $connection
     * @throws \InvalidArgumentException
     */
    public function __construct(string $tableName, DbAdapterInterface $connection) {
        if (!is_string($tableName) || empty($tableName)) {
            throw new \InvalidArgumentException('$tableName argument must be a not-empty string');
        }
        $this->tableName = $tableName;
        $this->tableAlias = StringUtils::classify($tableName);
        $this->connection = $connection;
    }

    /**
     * @param string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTableSchemaName(string $schema) {
        if (!is_string($schema) || empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string');
        }
        $this->dbSchema = $schema;
        return $this;
    }

    public function getTableSchemaName(): ?string {
        return $this->dbSchema;
    }

    public function getTableName(): string {
        return $this->tableName;
    }

    public function getTableAlias(): string {
        return $this->tableAlias;
    }

    public function getConnection(): DbAdapterInterface {
        return $this->connection;
    }

    /**
     * @param JoinInfo $joinInfo
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function join(JoinInfo $joinInfo, bool $append = true) {
        $this->_join($joinInfo, $append);
        return $this;
    }

}
