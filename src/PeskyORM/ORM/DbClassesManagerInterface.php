<?php

namespace PeskyORM\ORM;

interface DbClassesManagerInterface {

    /**
     * @param string $tableName
     * @return DbTable
     */
    public function getTableInstance($tableName);

    /**
     * @param string $tableAlias
     * @return DbTable
     */
    public function getTableInstanceByAlias($tableAlias);

    /**
     * @param string $tableName
     * @return DbTableStructure
     */
    public function getTableStructure($tableName);

    /**
     * @param string $tableName
     * @return DbRecord
     */
    public function newRecord($tableName);

}