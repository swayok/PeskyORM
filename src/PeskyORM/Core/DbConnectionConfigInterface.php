<?php

namespace PeskyORM\Core;

interface DbConnectionConfigInterface {

    /**
     * @param array $config
     * @param null $name
     * @return $this
     */
    static public function fromArray(array $config, $name = null);

    /**
     * Get PDO connection string (ex: pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass)
     * @return string
     */
    public function getPdoConnectionString();

    /**
     * Connection name (Default: DB name)
     * @return string
     */
    public function getName();

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
     * @return $this
     */
    public function setOptions(array $options);

    /**
     * GET options for PDO connection
     * @return array
     */
    public function getOptions();

    /**
     * @param $charset
     * @return $this
     */
    public function setCharset($charset);

    /**
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone($timezone);

    /**
     * Do some action on connect (set charset, default db schema, etc)
     * @param \PDO $connection
     * @return $this
     */
    public function onConnect(\PDO $connection);

}