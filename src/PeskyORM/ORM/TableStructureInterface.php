<?php

namespace PeskyORM\ORM;

interface TableStructureInterface {

    /**
     * @return $this
     */
    static public function getInstance();

    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    static public function getConnectionName($writable);

    /**
     * @return string
     */
    static public function getSchema();

    /**
     * @return string
     */
    static public function getTableName();

    /**
     * @param string $colName
     * @return bool
     */
    static public function hasColumn($colName);

    /**
     * @param string $colName
     * @return Column
     */
    static public function getColumn($colName);

    /**
     * @return Column[]
     */
    static public function getColumns();

    /**
     * @return string|null
     */
    static public function getPkColumnName();

    /**
     * @return Column
     */
    static public function getPkColumn();

    /**
     * @return bool
     */
    static public function hasPkColumn();

    /**
     * @return bool
     */
    static public function hasFileColumns();

    /**
     * @param string $colName
     * @return bool
     */
    static public function hasFileColumn($colName);

    /**
     * @return Column[] = array('column_name' => Column)
     */
    static public function getFileColumns();

    /**
     * @param $relationName
     * @return bool
     */
    static public function hasRelation($relationName);

    /**
     * @param string $relationName
     * @return Relation
     */
    static public function getRelation($relationName);

    /**
     * @return Relation[]
     */
    static public function getRelations();


}