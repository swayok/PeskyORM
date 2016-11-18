<?php

namespace PeskyORM\Core;

interface DbConnectionConfigInterface {

    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     * @return string
     */
    public function getPdoConnectionString();

    /**
     * @return string
     */
    public function getDbName();

    /**
     * @return string
     */
    public function getDbHost();

    /**
     * @return int|null|string
     */
    public function getDbPort();

    /**
     * @return string
     */
    public function getUserName();

    /**
     * @return string
     */
    public function getUserPassword();

    /**
     * Set options for PDO connection (key-value)
     * @param array $options
     */
    public function setOptions(array $options);

    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions();

    /**
     * @return null|string
     */
    public function getCharset();

}