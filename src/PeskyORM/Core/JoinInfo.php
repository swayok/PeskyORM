<?php

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;

class JoinInfo extends AbstractJoinInfo {

    /**
     * @param string $joinName
     * @param string $tableName
     * @param string $column
     * @param string $joinType
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return JoinInfo
     * @throws \InvalidArgumentException
     */
    static public function construct($joinName, $tableName, $column, $joinType, $foreignTableName, $foreignColumn) {
        return self::create($joinName)
            ->setConfigForLocalTable($tableName, $column)
            ->setJoinType($joinType)
            ->setConfigForForeignTable($foreignTableName, $foreignColumn);
    }

    /**
     * @param string $tableName
     * @param string $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForLocalTable($tableName, $column) {
        return $this->setTableName($tableName)->setColumnName($column);
    }

    /**
     * @param string $foreignTableName
     * @param string $foreignColumn
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setConfigForForeignTable($foreignTableName, $foreignColumn) {
        return $this->setForeignTableName($foreignTableName)->setForeignColumnName($foreignColumn);
    }

    /**
     * @param string $foreignTableName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableName($foreignTableName) {
        if (empty($foreignTableName) || !is_string($foreignTableName)) {
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
    public function setForeignTableSchema($schema) {
        if ($schema !== null && (!is_string($schema) || empty($schema)) ) {
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
    public function setTableName($tableName) {
        if (empty($tableName) || !is_string($tableName)) {
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
    public function setTableSchema($schema) {
        if ($schema !== null && (!is_string($schema) || empty($schema)) ) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string or null');
        }
        $this->tableSchema = $schema;
        return $this;
    }

}