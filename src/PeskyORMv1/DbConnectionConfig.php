<?php

namespace PeskyORM;

use PeskyORM\Exception\DbConnectionConfigException;

class DbConnectionConfig {

    const POSTGRESQL = 'pgsql';
    const MYSQL = 'mysql';
//    const SQLITE = 'sqlite';

    static private $drivers = array(
        self::POSTGRESQL,
        self::MYSQL,
//        self::SQLITE,
    );

    /** @var string */
    private $host = 'localhost';
    /** @var string */
    private $driver = self::POSTGRESQL;
    /** @var string */
    private $dbName;
    /** @var string */
    private $userName;
    /** @var string */
    private $password;

    static public function create() {
        return new DbConnectionConfig();
    }

    public function __construct() {

    }

    /**
     * @param string $host
     * @return $this
     */
    public function setHost($host) {
        $this->host = empty($host) ? 'localhost' : $host;
        return $this;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @param string $driver
     * @return $this
     * @throws DbConnectionConfigException
     */
    public function setDriver($driver) {
        if (empty($driver)) {
            throw new DbConnectionConfigException($this, "DB Driver cannot be empty");
        }
        if (!in_array($driver, self::$drivers)) {
            throw new DbConnectionConfigException($this, "DB Driver [{$driver}] is not supported");
        }
        $this->driver = $driver;
        return $this;
    }

    /**
     * @return string
     * @throws DbConnectionConfigException
     */
    public function getDriver() {
        if (empty($this->driver)) {
            throw new DbConnectionConfigException($this, "DB Driver cannot be empty");
        }
        if (!in_array($this->driver, self::$drivers)) {
            throw new DbConnectionConfigException($this, "DB Driver [{$this->driver}] is not supported");
        }
        return $this->driver;
    }

    /**
     * @param string $dbName
     * @return $this
     * @throws DbConnectionConfigException
     */
    public function setDbName($dbName) {
        if (empty($dbName)) {
            throw new DbConnectionConfigException($this, "DB Name cannot be empty");
        }
        $this->dbName = $dbName;
        return $this;
    }

    /**
     * @return string
     * @throws DbConnectionConfigException
     */
    public function getDbName() {
        if (empty($this->dbName)) {
            throw new DbConnectionConfigException($this, "DB Name cannot be empty");
        }
        return $this->dbName;
    }

    /**
     * @param string $userName
     * @return $this
     * @throws DbConnectionConfigException
     */
    public function setUserName($userName) {
        if (empty($userName)) {
            throw new DbConnectionConfigException($this, "User Name for DB connection cannot be empty");
        }
        $this->userName = $userName;
        return $this;
    }

    /**
     * @return string
     * @throws DbConnectionConfigException
     */
    public function getUserName() {
        if (empty($this->userName)) {
            throw new DbConnectionConfigException($this, "User Name for DB connection cannot be empty");
        }
        return $this->userName;
    }

    /**
     * @param string $password
     * @return $this
     * @throws DbConnectionConfigException
     */
    public function setPassword($password) {
        if (empty($password)) {
            throw new DbConnectionConfigException($this, "User Password for DB connection cannot be empty");
        }
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     * @throws DbConnectionConfigException
     */
    public function getPassword() {
        if (empty($this->password)) {
            throw new DbConnectionConfigException($this, "User Password for DB connection cannot be empty");
        }
        return $this->password;
    }


}