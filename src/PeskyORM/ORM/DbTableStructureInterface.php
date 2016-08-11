<?php

namespace PeskyORM\ORM;

interface DbTableStructureInterface {

    /**
     * @return $this
     */
    static public function getInstance();

    /**
     * @return string
     */
    static public function getConnectionName();

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
     * @return DbTableColumn
     */
    static public function getColumn($colName);

    /**
     * @return DbTableColumn[]
     */
    static public function getColumns();

    /**
     * @return string|null
     */
    static public function getPkColumnName();

    /**
     * @return DbTableColumn
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
     * @return DbTableColumn[] = array('column_name' => DbTableColumn)
     */
    static public function getFileColumns();

    /**
     * @param $relationName
     * @return bool
     */
    static public function hasRelation($relationName);

    /**
     * @param string $relationName
     * @return DbTableRelation
     */
    static public function getRelation($relationName);

    /**
     * @return DbTableRelation[]
     */
    static public function getRelations();


}