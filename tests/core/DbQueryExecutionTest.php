<?php

use PeskyORM\Config\Connection\PostgresConfig;

class PostgresAdapterFunctionalityTest extends PHPUnit_Extensions_Database_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;
    /** @var PDO */
    static protected $pdo;
    /** @var PHPUnit_Extensions_Database_DB_IDatabaseConnection */
    static protected $connection;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
        self::$pdo = new PDO(
            self::$dbConnectionConfig->getPdoConnectionString(),
            self::$dbConnectionConfig->getUserName(),
            self::$dbConnectionConfig->getUserPassword(),
            self::$dbConnectionConfig->getOptions()
        );
    }

    public static function tearDownAfterClass() {
        self::$dbConnectionConfig = null;
        self::$connection = null;
        self::$pdo = null;
    }

    /**
     * Returns the test database connection.
     *
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    protected function getConnection() {
        if (self::$connection === null) {
            self::$connection = $this->createDefaultDBConnection(self::$pdo, 'public');
        }
        return self::$connection;
    }

    /**
     * Returns the test dataset.
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet() {
        return new PHPUnit_Extensions_Database_DataSet_ArrayDataSet([]);
    }

    public function testNothing() {

    }
}
