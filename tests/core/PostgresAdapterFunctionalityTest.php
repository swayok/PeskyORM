<?php

use PeskyORM\Config\Connection\PostgresConfig;

class PostgresAdapterFunctionalityTest extends PHPUnit_Extensions_Database_TestCase {

    /** @var PostgresConfig */
    static $dbConnectionConfig;

    public static function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        self::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
    }

    public static function tearDownAfterClass() {
        self::$dbConnectionConfig = null;
    }

    /**
     * Returns the test database connection.
     *
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    protected function getConnection() {
        $pdo = new PDO(
            self::$dbConnectionConfig->getPdoConnectionString(),
            self::$dbConnectionConfig->getUserName(),
            self::$dbConnectionConfig->getUserPassword(),
            self::$dbConnectionConfig->getOptions()
        );
        return $this->createDefaultDBConnection($pdo, 'public');
    }

    /**
     * Returns the test dataset.
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet() {

    }
}
