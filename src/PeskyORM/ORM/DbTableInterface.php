<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;

interface DbTableInterface {

    /**
     * Table Name
     * @return string
     */
    static public function getTableName();
    
    /**
     * Table alias.
     * For example: if table name is 'user_actions' the alias might be 'UserActions'
     * @return string
     */
    static public function getTableAlias();

    /**
     * @return DbAdapterInterface
     */
    static public function getConnection();

    /**
     * @return DbTableInterface
     */
    static public function getInstance();

    /**
     * Table schema description
     * @return DbTableStructure
     */
    static public function getStructure();

    /**
     * @return bool
     */
    static public function hasPkColumn();

    /**
     * @return DbTableColumn
     */
    static public function getPkColumn();

    /**
     * @return mixed
     */
    static public function getPkColumnName();

//    static public function setTempAlias();

//    static public function getTempAlias();
}