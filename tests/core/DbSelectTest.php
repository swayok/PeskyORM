<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\DbJoinConfig;
use PeskyORM\Core\DbSelect;
use Swayok\Utils\Set;

class DbSelectTest extends \PHPUnit_Framework_TestCase {

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

    static protected function cleanTables() {
        $adapter = static::getValidAdapter();
        $adapter->exec('TRUNCATE TABLE settings');
        $adapter->exec('TRUNCATE TABLE admins');
    }

    static protected function fillTables() {
        static::cleanTables();
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

    static protected function getNewSelect() {
        return DbSelect::from('admins', static::getValidAdapter());
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
     * @param DbSelect $object
     * @param string $propertyName
     * @return mixed
     */
    public function getObjectPropertyValue($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @param DbSelect $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    public function callObjectMethod($object, $methodName, array $args = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $tableName argument must be a not-empty string
     */
    public function testInvalidTableNameInConstructor1() {
        DbSelect::from('', static::getValidAdapter());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $tableName argument must be a not-empty string
     */
    public function testInvalidTableNameInConstructor2() {
        DbSelect::from(null, static::getValidAdapter());
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage It is impossible to use pagination when there is no limit
     */
    public function testInvalidFetchNextPage() {
        static::getNewSelect()->fetchNextPage();
    }

    public function testConstructorAndBasicFetching() {
        $adapter = static::getValidAdapter();
        // via new
        $dbSelect = new DbSelect('admins', $adapter);
        static::assertInstanceOf(DbSelect::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertEquals(
            [
                [
                    'name' => '*',
                    'alias' => null,
                    'join_alias' => null,
                    'type_cast' => null,
                ]
            ],
            $this->getObjectPropertyValue($dbSelect, 'columns')
        );
        static::assertEquals('SELECT "Admins".* FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getCountQuery()));

        $insertedData = static::fillTables();
        $testData = static::convertTestDataForAdminsTableAssert($insertedData['admins']);
        $count = $dbSelect->fetchCount();
        static::assertEquals(2, $count);
        $data = $dbSelect->fetchMany();
        static::assertEquals($testData, $data);
        $data = $dbSelect->fetchOne();
        static::assertEquals($testData[0], $data);
        $data = $dbSelect->fetchColumn();
        static::assertEquals(Set::extract('/id', $testData), $data);
        $data = $dbSelect->fetchAssoc('id', 'login');
        static::assertEquals(Set::combine($testData, '/id', '/login'), $data);
        $sum = $dbSelect->fetchValue(\PeskyORM\Core\DbExpr::create('SUM(`id`)'));
        static::assertEquals(array_sum(Set::extract('/id', $testData)), $sum);

        // via static
        $dbSelect = DbSelect::from('admins', $adapter);
        static::assertInstanceOf(DbSelect::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertEquals('admins', $dbSelect->getTableName());
        $data = $dbSelect->limit(1)->fetchNextPage();
        static::assertEquals([$testData[1]], $data);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns1() {
        static::getNewSelect()->columns(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns3() {
        static::getNewSelect()->columns(1);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column name for a key
     */
    public function testInvalidColumns4() {
        static::getNewSelect()->columns(['']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column name for a key 'qq'
     */
    public function testInvalidColumns5() {
        static::getNewSelect()->columns(['qq' => '']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column alias
     */
    public function testInvalidColumns6() {
        static::getNewSelect()->columns(['' => 'qq']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns7() {
        static::getNewSelect()->columns([$this]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns8() {
        static::getNewSelect()->columns([[]]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument must be a string or instance of DbExpr class
     */
    public function testInvalidAnalyzeColumnName11() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', [null]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument is not allowed to be an empty string
     */
    public function testInvalidAnalyzeColumnName12() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument must be a string or instance of DbExpr class
     */
    public function testInvalidAnalyzeColumnName13() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', [[]]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument must be a string or instance of DbExpr class
     */
    public function testInvalidAnalyzeColumnName14() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', [false]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid column name or json selector: [test test]
     */
    public function testInvalidAnalyzeColumnName15() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test test']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid column name or json selector: [0test]
     */
    public function testInvalidAnalyzeColumnName16() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['0test']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid column name or json selector: [test%test]
     */
    public function testInvalidAnalyzeColumnName17() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test%test']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $alias argument must be a string or null
     */
    public function testInvalidAnalyzeColumnName21() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', []]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $alias argument must be a string or null
     */
    public function testInvalidAnalyzeColumnName22() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', false]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $alias argument is not allowed to be an empty string
     */
    public function testInvalidAnalyzeColumnName23() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', '']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $joinName argument must be a string or null
     */
    public function testInvalidAnalyzeColumnName31() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', null, false]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $joinName argument must be a string or null
     */
    public function testInvalidAnalyzeColumnName32() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', null, []]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $joinName argument is not allowed to be an empty string
     */
    public function testInvalidAnalyzeColumnName33() {
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['test', null, '']);
    }

    public function testAnalyzeColumnName() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            [
                'name' => '*',
                'alias' => null,
                'join_alias' => null,
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['*', 'test'])
        );
        static::assertEquals(
            [
                'name' => '*',
                'alias' => null,
                'join_alias' => 'JoinAlias',
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['JoinAlias.*', 'test'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => 'not_id',
                'join_alias' => null,
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['id as not_id'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_alias' => null,
                'type_cast' => 'int',
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['Admins.id::int'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_alias' => 'Other',
                'type_cast' => 'int',
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['Other.id::int'])
        );
        $dbExpr = DbExpr::create('Other.id::int');
        $columnInfo = $this->callObjectMethod($dbSelect, 'analyzeColumnName', [$dbExpr]);
        static::assertArraySubset(
            [
                'alias' => null,
                'join_alias' => null,
                'type_cast' => null,
            ],
            $columnInfo
        );
        static::assertInstanceOf(DbExpr::class, $columnInfo['name']);
        static::assertEquals($dbExpr->get(), $columnInfo['name']->get());
        $columnInfo = $this->callObjectMethod($dbSelect, 'analyzeColumnName', [$dbExpr, 'dbexpr']);
        static::assertArraySubset(
            [
                'alias' => 'dbexpr',
                'join_alias' => null,
                'type_cast' => null,
            ],
            $columnInfo
        );
        static::assertInstanceOf(DbExpr::class, $columnInfo['name']);
        static::assertEquals($dbExpr->get(), $columnInfo['name']->get());
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage There are no joins defined for next aliases: OtherTable
     */
    public function testInvalidJoinsSet() {
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "public"."admins" AS "Admins"',
            rtrim(static::getNewSelect()->columns(['OtherTable.id'])->getQuery())
        );
    }

    public function testColumns() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns([])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['*'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['id'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['Admins.id'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['id', 'login'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id"::int AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns('id::int', 'login')->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", (SUM("id")) FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['id', DbExpr::create('SUM(`id`)')])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__not_id", (SUM("id")) AS "_Admins__sum" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['not_id' => 'id', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins".*, (SUM("id")) AS "_Admins__sum" FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->columns(['*', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery())
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument cannot be empty
     */
    public function testInvalidOrderBy1() {
        static::getNewSelect()->orderBy('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument cannot be empty
     */
    public function testInvalidOrderBy2() {
        static::getNewSelect()->orderBy(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument cannot be empty
     */
    public function testInvalidOrderBy3() {
        static::getNewSelect()->orderBy(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument must be a string or instance of DbExpr class
     */
    public function testInvalidOrderBy4() {
        static::getNewSelect()->orderBy(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument cannot be empty
     */
    public function testInvalidOrderBy5() {
        static::getNewSelect()->orderBy([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnName argument must be a string or instance of DbExpr class
     */
    public function testInvalidOrderBy6() {
        static::getNewSelect()->orderBy($this);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage There are no joins defined for next aliases: OtherTable
     */
    public function testInvalidOrderBy7() {
        static::getNewSelect()->orderBy('OtherTable.id')->getQuery();
    }

    public function testOrderBy() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" ORDER BY "Admins"."id" ASC',
            $dbSelect->orderBy('id')->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" ORDER BY "Admins"."id" DESC',
            $dbSelect->orderBy('Admins.id', false)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" ORDER BY "Admins"."id" DESC, "Admins"."email" DESC',
            $dbSelect->orderBy('email', false)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" ORDER BY (RANDOM())',
            $dbSelect->orderBy(DbExpr::create('RANDOM()'), null, false)->getQuery()
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains invalid value at index '0'
     */
    public function testInvalidGroupBy1() {
        static::getNewSelect()->groupBy([null]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains invalid value at index '0'
     */
    public function testInvalidGroupBy2() {
        static::getNewSelect()->groupBy([true]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains invalid value at index '0'
     */
    public function testInvalidGroupBy3() {
        static::getNewSelect()->groupBy([false]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains invalid value at index '0'
     */
    public function testInvalidGroupBy4() {
        static::getNewSelect()->groupBy([$this]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains invalid value at index '0'
     */
    public function testInvalidGroupBy5() {
        static::getNewSelect()->groupBy([[]]);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage There are no joins defined for next aliases: OtherTable
     */
    public function testInvalidGroupBy6() {
        static::getNewSelect()->groupBy(['OtherTable.id'])->getQuery();
    }

    public function testGroupBy() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['Admins.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" GROUP BY "Admins"."id", "Admins"."email"',
            $dbSelect->groupBy(['email'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" GROUP BY (RANDOM())',
            $dbSelect->groupBy([DbExpr::create('RANDOM()')], false)->getQuery()
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit1() {
        static::getNewSelect()->limit(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit2() {
        static::getNewSelect()->limit(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit3() {
        static::getNewSelect()->limit(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit4() {
        static::getNewSelect()->limit([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit5() {
        static::getNewSelect()->limit($this);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer value >= 0
     */
    public function testInvalidLimit6() {
        static::getNewSelect()->limit(-1);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset1() {
        static::getNewSelect()->offset(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset2() {
        static::getNewSelect()->offset(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset3() {
        static::getNewSelect()->offset(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset4() {
        static::getNewSelect()->offset([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset5() {
        static::getNewSelect()->offset($this);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer value >= 0
     */
    public function testInvalidOffset6() {
        static::getNewSelect()->offset(-1);
    }

    public function testLimitAndOffset() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            $dbSelect->limit(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            $dbSelect->noLimit()->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" LIMIT 1 OFFSET 2',
            $dbSelect->limit(1)->offset(2)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" OFFSET 1',
            $dbSelect->limit(0)->offset(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" LIMIT 10 OFFSET 9',
            $dbSelect->page(10, 9)->getQuery()
        );
    }

    public function testWhereAndHaving() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            $dbSelect->where([])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' HAVING "Admins"."login"::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])->having(['login::varchar' => '2'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' AND "Admins"."login" = \'3\' HAVING "Admins"."login"::varchar = \'2\' AND "Admins"."email" = \'3\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])->having(['login::varchar' => '2', 'email' => '3'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])->having([DbExpr::create('SUM(`id`) > ``2``')])->getQuery()
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join config is not valid
     */
    public function testInvalidJoin1() {
        $joinConfig = DbJoinConfig::create('Test');
        static::getNewSelect()->join($joinConfig);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join with name 'Test' already defined
     */
    public function testInvalidJoin2() {
        $joinConfig = DbJoinConfig::create('Test')
            ->setConfigForLocalTable('admins', 'id')
            ->setConfigForForeignTable('settings', 'id')
            ->setJoinType(DbJoinConfig::JOIN_INNER);
        static::getNewSelect()->join($joinConfig)->join($joinConfig);
    }

    public function testJoins() {
        $dbSelect = static::getNewSelect();
        $joinConfig = DbJoinConfig::create('Test')
            ->setConfigForLocalTable('admins', 'id')
            ->setConfigForForeignTable('settings', 'id')
            ->setJoinType(DbJoinConfig::JOIN_INNER)
            ->setForeignColumnsToSelect('key', 'value');
        static::assertEquals(
            'SELECT "Admins".*, "Test"."key" AS "_Test__key", "Test"."value" AS "_Test__value" FROM "public"."admins" AS "Admins" INNER JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(DbJoinConfig::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'key' => 'name'
            ]);
        static::assertEquals(
            'SELECT "Admins".*, "Test".* FROM "public"."admins" AS "Admins" LEFT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(DbJoinConfig::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['value']);
        static::assertEquals(
            'SELECT "Admins".*, "Test"."value" AS "_Test__value" FROM "public"."admins" AS "Admins" RIGHT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(DbJoinConfig::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" RIGHT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
    }

    public function testMakeColumnAlias() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '_Admins__colname',
            $this->callObjectMethod($dbSelect, 'makeColumnAlias', ['colname'])
        );
        static::assertEquals(
            '_JoinAlias__colname',
            $this->callObjectMethod($dbSelect, 'makeColumnAlias', ['colname', 'JoinAlias'])
        );
    }

    public function testMakeColumnNameWithAliasForQuery() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"Admins"."colname" AS "_Admins__colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [[
                'name' => 'colname',
                'alias' => null,
                'join_alias' => null,
                'type_cast' => null
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int AS "_JoinAlias__colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [[
                'name' => 'colname',
                'alias' => null,
                'join_alias' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int AS "_JoinAlias__colalias"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_alias' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
    }

    public function testMakeTableNameWithAliasForQuery() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"public"."admins" AS "Admins"',
            $this->callObjectMethod($dbSelect, 'makeTableNameWithAliasForQuery', ['admins', 'Admins'])
        );
        $expr = $this->callObjectMethod(
            $dbSelect,
            'makeTableNameWithAliasForQuery',
            ['admins', 'SomeTooLongTableAliasToMakeSystemShortenIt', 'other']
        );
        static::assertRegExp('%"other"\."admins" AS "[a-z][a-z0-9]+"%', $expr);
        static::assertNotEquals('"other"."admins" AS "SomeTooLongTableAliasToMakeSystemShortenIt"', $expr);
    }

    public function testMakeColumnNameForCondition() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"Admins"."colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => null,
                'join_alias' => null,
                'type_cast' => null
            ]])
        );
        static::assertEquals(
            '"Admins"."colname"::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_alias' => null,
                'type_cast' => 'int'
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_alias' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
    }

    public function testGetShortAlias() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'Admins',
            $this->callObjectMethod($dbSelect, 'getShortAlias', ['Admins'])
        );
        for ($i = 0; $i < 30; $i++) {
            // it uses rand so it will be better to test it many times
            $alias = $this->callObjectMethod($dbSelect, 'getShortAlias', ['SomeTooLongTableAliasToMakeSystemShortenIt']);
            static::assertNotEquals('Admins', $alias);
            static::assertRegExp('%^[a-z][a-z0-9]+$%', $alias);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName1() {
        static::getNewSelect()->setTableSchemaName(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName2() {
        static::getNewSelect()->setTableSchemaName('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName3() {
        static::getNewSelect()->setTableSchemaName(true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName4() {
        static::getNewSelect()->setTableSchemaName(false);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName5() {
        static::getNewSelect()->setTableSchemaName(['arr']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $schema argument must be a not-empty string
     */
    public function testInvalidSetDbSchemaName6() {
        static::getNewSelect()->setTableSchemaName($this);
    }

    public function testSetDbSchemaName() {
        $dbSelect = static::getNewSelect();
        static::assertEquals('test_schema', $dbSelect->setTableSchemaName('test_schema')->getTableSchemaName());
        static::assertEquals('SELECT "Admins".* FROM "test_schema"."admins" AS "Admins"', $dbSelect->getQuery());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage COLUMNS key in $conditionsAndOptions argument must be an array or '*'
     */
    public function testInvalidFromConfigsArrayColumns1() {
        static::getNewSelect()->fromConfigsArray([
            'COLUMNS' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage COLUMNS key in $conditionsAndOptions argument must be an array or '*'
     */
    public function testInvalidFromConfigsArrayColumns2() {
        static::getNewSelect()->fromConfigsArray([
            'COLUMNS' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayOrder1() {
        static::getNewSelect()->fromConfigsArray([
            'ORDER' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayOrder2() {
        static::getNewSelect()->fromConfigsArray([
            'ORDER' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayOrder3() {
        static::getNewSelect()->fromConfigsArray([
            'ORDER' => 'colname ASC'
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER key contains invalid direction 'NONE' for a column 'colname'
     */
    public function testInvalidFromConfigsArrayOrder4() {
        static::getNewSelect()->fromConfigsArray([
            'ORDER' => ['colname' => 'NONE']
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage GROUP key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayGroup1() {
        static::getNewSelect()->fromConfigsArray([
            'GROUP' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage GROUP key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayGroup2() {
        static::getNewSelect()->fromConfigsArray([
            'GROUP' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage HAVING key in $conditionsAndOptions argument must be an must be an array like conditions
     */
    public function testInvalidFromConfigsArrayHaving1() {
        static::getNewSelect()->fromConfigsArray([
            'HAVING' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage HAVING key in $conditionsAndOptions argument must be an must be an array like conditions
     */
    public function testInvalidFromConfigsArrayHaving2() {
        static::getNewSelect()->fromConfigsArray([
            'HAVING' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayJoins1() {
        static::getNewSelect()->fromConfigsArray([
            'JOINS' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must be an array
     */
    public function testInvalidFromConfigsArrayJoins2() {
        static::getNewSelect()->fromConfigsArray([
            'JOINS' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must contain only instances of DbJoinConfig class
     */
    public function testInvalidFromConfigsArrayJoins3() {
        static::getNewSelect()->fromConfigsArray([
            'JOINS' => ['string']
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must contain only instances of DbJoinConfig class
     */
    public function testInvalidFromConfigsArrayJoins4() {
        static::getNewSelect()->fromConfigsArray([
            'JOINS' => [$this]
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage LIMIT key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayLimit1() {
        static::getNewSelect()->fromConfigsArray([
            'LIMIT' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage LIMIT key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayLimit2() {
        static::getNewSelect()->fromConfigsArray([
            'LIMIT' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage LIMIT key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayLimit3() {
        static::getNewSelect()->fromConfigsArray([
            'LIMIT' => -1
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage OFFSET key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayOffset1() {
        static::getNewSelect()->fromConfigsArray([
            'OFFSET' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage OFFSET key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayOffset2() {
        static::getNewSelect()->fromConfigsArray([
            'OFFSET' => true
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage OFFSET key in $conditionsAndOptions argument must be an integer >= 0
     */
    public function testInvalidFromConfigsArrayOffset3() {
        static::getNewSelect()->fromConfigsArray([
            'OFFSET' => -1
        ]);
    }

    public function testFromConfigsArray() {
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins"',
            static::getNewSelect()->fromConfigsArray([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" WHERE "Admins"."colname" = \'value\'',
            static::getNewSelect()->fromConfigsArray(['colname' => 'value'])->getQuery()
        );
        $configs = [
            'colname' => 'value',
            'OR' => [
                'colname2' => 'value2',
                'colname3' => 'value3',
            ],
            'COLUMNS' => ['colname', 'colname2', 'colname3', 'setting_value' => 'Test.value', '*'],
            'ORDER' => ['colname', 'Test.admin_id' => 'desc'],
            'LIMIT' => 10,
            'OFFSET' => 20,
            'GROUP' => ['colname', 'Test.admin_id'],
            'HAVING' => [
                'colname3' => 'value',
                'Test.admin_id >' => '1',
            ],
            'JOIN' => [
                DbJoinConfig::create('Test')
                    ->setConfigForLocalTable('admins', 'id')
                    ->setJoinType(DbJoinConfig::JOIN_LEFT)
                    ->setConfigForForeignTable('settings', 'admin_id')
                    ->setForeignColumnsToSelect('admin_id')
            ]
        ];
        static::assertEquals(
            'SELECT "Admins"."colname" AS "_Admins__colname", "Admins"."colname2" AS "_Admins__colname2", "Admins"."colname3" AS "_Admins__colname3", "Test"."value" AS "_Test__setting_value", "Admins".*, "Test"."admin_id" AS "_Test__admin_id" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."admin_id") WHERE "Admins"."colname" = \'value\' AND ("Admins"."colname2" = \'value2\' OR "Admins"."colname3" = \'value3\') GROUP BY "Admins"."colname", "Test"."admin_id" HAVING "Admins"."colname3" = \'value\' AND "Test"."admin_id" > \'1\' ORDER BY "Admins"."colname" ASC, "Test"."admin_id" DESC LIMIT 10 OFFSET 20',
            static::getNewSelect()->fromConfigsArray($configs)->getQuery()
        );
    }
}
