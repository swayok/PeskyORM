<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Swayok\Utils\Set;

class UtilsTest extends \PHPUnit_Framework_TestCase {

    /** @var PostgresConfig */
    static protected $dbConnectionConfig;

    static public function setUpBeforeClass() {
        $data = include __DIR__ . '/../configs/global.php';
        static::$dbConnectionConfig = PostgresConfig::fromArray($data['pgsql']);
        static::cleanTables();
    }

    static public function tearDownAfterClass() {
        static::cleanTables();
        static::$dbConnectionConfig = null;
    }

    protected function tearDown() {
        static::cleanTables();
    }

    static protected function cleanTables() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }

    static protected function fillTables() {
        $data = static::getTestDataForAdminsTableInsert();
        static::getValidAdapter()->insertMany('admins', array_keys($data[0]), $data);
        return ['admins' => $data];
    }

    static protected function getValidAdapter() {
        $adapter = new Postgres(static::$dbConnectionConfig);
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }

    static public function getTestDataForAdminsTableInsert() {
        return [
            [
                'id' => 1,
                'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
                'password' => password_hash('KIS37QEG4HT', PASSWORD_DEFAULT),
                'parent_id' => null,
                'created_at' => '2015-05-14 02:12:05+00',
                'updated_at' => '2015-06-10 19:30:24+00',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => 1,
                'name' => 'Lionel Freeman',
                'email' => 'diam.at.pretium@idmollisnec.co.uk',
                'timezone' => 'Europe/Moscow'
            ],
            [
                'id' => 2,
                'login' => 'ADCE237A-9E48-BECD-1F01-1CACA964CF0F',
                'password' => password_hash('NKJ63NMV6NY', PASSWORD_DEFAULT),
                'parent_id' => 1,
                'created_at' => '2015-05-14 06:54:01+00',
                'updated_at' => '2015-05-19 23:48:17+00',
                'remember_token' => '0A2E7DA9-6072-34E2-38E8-2675C73F3419',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => false,
                'name' => 'Jasper Waller',
                'email' => 'elit@eratvelpede.org',
                'timezone' => 'Europe/Moscow'
            ]
        ];
    }

    public function convertTestDataForAdminsTableAssert($data) {
        foreach ($data as &$item) {
            $item['id'] = "{$item['id']}";
            $item['is_superadmin'] = (bool)$item['is_superadmin'];
            $item['is_active'] = (bool)$item['is_active'];
        }
        return $data;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unknown processing type [???]
     */
    public function testInvalidGetDataFromStatement1() {
        Utils::getDataFromStatement(new PDOStatement(), '???');
    }

    public function testGetDataFromStatement() {
        $testData = $this->convertTestDataForAdminsTableAssert(static::fillTables()['admins']);
        $statement = static::getValidAdapter()->query(DbExpr::create('SELECT * FROM `admins`'));
        static::assertEquals(
            $testData,
            Utils::getDataFromStatement($statement, Utils::FETCH_ALL)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0],
            Utils::getDataFromStatement($statement, Utils::FETCH_FIRST)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            Set::extract('/id', $testData),
            Utils::getDataFromStatement($statement, Utils::FETCH_COLUMN)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0]['id'],
            Utils::getDataFromStatement($statement, Utils::FETCH_VALUE)
        );
        $statement = static::getValidAdapter()->query(DbExpr::create('SELECT * FROM `admins` WHERE `id` < ``0``'));
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_ALL));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_FIRST));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_COLUMN));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(null, Utils::getDataFromStatement($statement, Utils::FETCH_VALUE));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $glue argument must be "AND" or "OR"
     */
    public function testInvalidAssembleWhereConditionsFromArray1() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [], function () {}, 'wow');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $conditions argument may contain only objects of class DbExpr. Other objects are forbidden.
     */
    public function testInvalidAssembleWhereConditionsFromArray3() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [$this], function () {});
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty column name detected in $conditions argument
     */
    public function testInvalidAssembleWhereConditionsFromArray4() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['' => 'value'], function () {});
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty column name detected in $conditions argument
     */
    public function testInvalidAssembleWhereConditionsFromArray5() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [' =' => ['value']], function () {});
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty column name detected in $conditions argument
     */
    public function testInvalidAssembleWhereConditionsFromArray6() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['=' => ['value']], function () {});
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Condition operator [LIKE] does not support list of values
     */
    public function testInvalidAssembleWhereConditionsFromArray7() {
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['col1 LIKE' => ['value']], function () {});
    }

    public function testGluesInAssembleWhereConditionsFromArray() {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $col2 = $adapter->quoteDbEntityName('col2');
        $value1 = $adapter->quoteValue('value');
        $value2 = $adapter->quoteValue('value2');
        static::assertEquals(
            '',
            Utils::assembleWhereConditionsFromArray($adapter, [], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 OR $col2 = $value2",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter, 'OR')
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'AND' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2) AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2'], 'col2' => 'value2'],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR ($col1 = $value1 AND $col2 = $value2)) AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', ['col' => 'value', 'col2' => 'value2']], 'col2' => 'value2'],
                $columnQuoter
            )
        );
    }

    public function testOperatorsInAssembleWhereConditionsFromArray() {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $value1 = $adapter->quoteValue('value');
        $value2 = $adapter->quoteValue('value2');
        static::assertEquals(
            "$col1 = $value1 AND $col1 != $value1 AND $col1 = $value1 AND $col1 != $value1 AND $col1 != $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col =' => 'value',
                    'col !=' => 'value',
                    'col is' => 'value',
                    'col not' => 'value',
                    'col is not' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 < $value1 AND $col1 <= $value1 AND $col1 > $value1 AND $col1 >= $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col <' => 'value',
                    'col <=' => 'value',
                    'col >' => 'value',
                    'col >=' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "jsonb_exists($col1->$value2, $value1) AND jsonb_exists_any($col1, array[$value1, $value2]) AND jsonb_exists_all($col1, array[$value1, $value2])",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col->value2 ?' => 'value',
                    'col ?|' => ['value', 'value2'],
                    'col ?&' => ['value', 'value2'],
                ],
                $columnQuoter
            )
        );
        $jsonQuoted = $adapter->quoteValue(json_encode(['value' => 'value2']));
        static::assertEquals(
            "$col1 @> $jsonQuoted::jsonb AND $col1 <@ $jsonQuoted::jsonb",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col @>' => ['value' => 'value2'],
                    'col <@' => json_encode(['value' => 'value2']),
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 ~ $value1 AND $col1 !~ $value1 AND $col1 ~* $value1 AND $col1 !~* $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col ~' => 'value',
                    'col !~' => 'value',
                    'col ~*' => 'value',
                    'col !~*' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 LIKE $value1 AND $col1 NOT LIKE $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col LIKE' => 'value',
                    'col NOT LIKE ' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 IS NULL AND $col1 IS NOT NULL AND $col1 IS NULL AND $col1 IS NOT NULL AND $col1 IS NOT NULL",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col =' => null,
                    'col !=' => null,
                    'col IS' => null,
                    'col NOT' => null,
                    'col IS NOT' => null,
                ],
                $columnQuoter
            )
        );
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Value [aaa] for column name [test] is invalid
     */
    public function testValidationViaAssembleWhereConditionsFromArray() {
        Utils::assembleWhereConditionsFromArray(
            static::getValidAdapter(),
            ['test' => 'aaa'],
            null,
            'AND',
            function ($colName, $value, $connection) {
                throw new \UnexpectedValueException("Value [$value] for column name [$colName] is invalid");
            }
        );
    }

    public function testDbExprUsageInAssembleWhereConditionsFromArray() {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $value1 = $adapter->quoteValue('value');
        static::assertEquals(
            "($col1 = $value1)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [DbExpr::create('`col` = ``value``')],
                $columnQuoter,
                'AND',
                function ($colName, $value, \PeskyORM\Core\DbAdapterInterface $connection) {
                    return $connection->quoteDbExpr($value);
                }
            )
        );
        static::assertEquals(
            "$col1 = ($value1)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => DbExpr::create('``value``')],
                $columnQuoter
            )
        );
    }
}
