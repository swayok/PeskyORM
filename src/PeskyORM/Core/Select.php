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
     * @var string
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
    static public function from($tableName, DbAdapterInterface $connection) {
        return new static($tableName, $connection);
    }

    /**
     * @param string $tableName - table name or Table object
     * @param DbAdapterInterface $connection
     * @throws \InvalidArgumentException
     */
    public function __construct($tableName, DbAdapterInterface $connection) {
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
    public function setTableSchemaName($schema) {
        if (!is_string($schema) || empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string');
        }
        $this->dbSchema = $schema;
        return $this;
    }

    /**
     * @return string
     */
    public function getTableSchemaName() {
        return $this->dbSchema;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getTableAlias() {
        return $this->tableAlias;
    }

    /**
     * @return DbAdapterInterface
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * @param JoinInfo $joinConfig
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function join(JoinInfo $joinConfig, $append = true) {
        $this->_join($joinConfig, $append);
        return $this;
    }

}