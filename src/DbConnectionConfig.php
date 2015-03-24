<?php

namespace PeskyORM;

use ORM\Exception\DbConnectionConfigException;

class DbConnectionConfig {

    const POSTGRESQL = 'pgsql';
//    const MYSQL = 'mysql';
//    const SQLITE = 'sqlite';

    static private $drivers = array(
        self::POSTGRESQL,
//        self::MYSQL,
//        self::SQLITE,
    );

    /** @var string */
    private $host = 'localhost';
    /** @var string */
    private $driver;
    /** @var string */
    private $dbName;
    /** @var string */
    private $userName;
    /** @var string */
    private $password;

    /**
     * @param string $dbName
     * @param string $userName
     * @param string $password
     * @param string string $driver
     * @param string string $host
     * @throws DbConnectionConfigException
     */
    public function __construct($dbName, $userName, $password, $driver = self::POSTGRESQL, $host = 'localhost') {
        if (empty($dbName)) {
            throw new DbConnectionConfigException($this, "DB Name cannot be empty");
        }
        if (empty($userName)) {
            throw new DbConnectionConfigException($this, "User Name for DB connection cannot be empty");
        }
        if (empty($password)) {
            throw new DbConnectionConfigException($this, "User Password for DB connection cannot be empty");
        }
        if (empty($driver)) {
            throw new DbConnectionConfigException($this, "DB Driver cannot be empty");
        }
        if (!in_array($driver, self::$drivers)) {
            throw new DbConnectionConfigException($this, "DB Driver [{$driver}] is not supported");
        }

        $this->dbName = $dbName;
        $this->userName = $userName;
        $this->password = $password;
        $this->driver = $driver;
        $this->host = empty($host) ? 'localhost' : $host;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getDriver() {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getDbName() {
        return $this->dbName;
    }

    /**
     * @return string
     */
    public function getUserName() {
        return $this->userName;
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

}