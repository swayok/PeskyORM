<?php

namespace PeskyORM\ORM;

interface DbClassesManagerInterface {

    /**
     * @param string $tableName
     * @return DbTable
     */
    static public function getTableInstance($tableName);

    /**
     * @param string $tableAlias
     * @return DbTable
     */
    static public function getTableInstanceByAlias($tableAlias);

    /**
     * @param string $tableName
     * @return DbTableStructure
     */
    static public function getTableStructure($tableName);

    /**
     * @param string $tableName
     * @return DbRecord
     */
    static public function newRecord($tableName);

}