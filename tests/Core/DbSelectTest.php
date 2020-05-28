<?php

namespace Tests\Core;

use InvalidArgumentException;
use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\JoinInfo;
use PeskyORM\Core\Select;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Swayok\Utils\Set;
use Tests\PeskyORMTest\TestingApp;
use UnexpectedValueException;

class DbSelectTest extends TestCase {

    static public function setUpBeforeClass(): void {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static public function tearDownAfterClass(): void {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static protected function fillAdminsTable() {
        TestingApp::clearTables(static::getValidAdapter());
        $data = static::getTestDataForAdminsTableInsert();
        static::getValidAdapter()->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }

    static protected function getValidAdapter() {
        $adapter = TestingApp::getPgsqlConnection();
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
        return Select::from('admins', static::getValidAdapter());
    }

    public function convertTestDataForAdminsTableAssert($data) {
        foreach ($data as &$item) {
            $item['id'] = "{$item['id']}";
            $item['is_superadmin'] = (bool)$item['is_superadmin'];
            $item['is_active'] = (bool)$item['is_active'];
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
    }

    /**
     * @param Select $object
     * @param string $propertyName
     * @return mixed
     */
    private function getObjectPropertyValue($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @param Select $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    private function callObjectMethod($object, $methodName, array $args = []) {
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
        Select::from('', static::getValidAdapter());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $tableName argument must be a not-empty string
     */
    public function testInvalidTableNameInConstructor2() {
        Select::from(null, static::getValidAdapter());
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
        $dbSelect = new Select('admins', $adapter);
        static::assertInstanceOf(Select::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertEquals([], $this->getObjectPropertyValue($dbSelect, 'columns')); //< not initialized before query builder launched
        static::assertEquals('SELECT "Admins".* FROM "admins" AS "Admins"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "admins" AS "Admins"', rtrim($dbSelect->getCountQuery()));
        static::assertEquals('SELECT 1 FROM "admins" AS "Admins" LIMIT 1', rtrim($dbSelect->getExistenceQuery()));

        $insertedData = static::fillAdminsTable();
        $testData = $this->convertTestDataForAdminsTableAssert($insertedData);
        static::assertEquals(2, $dbSelect->fetchCount());
        static::assertTrue($dbSelect->fetchExistence());
        $data = $dbSelect->fetchMany();
        static::assertEquals($testData, $data);
        $data = $dbSelect->fetchOne();
        static::assertEquals($testData[0], $data);
        $data = $dbSelect->fetchColumn();
        static::assertEquals(Set::extract('/id', $testData), $data);
        $data = $dbSelect->fetchAssoc('id', 'login');
        static::assertEquals(Set::combine($testData, '/id', '/login'), $data);
        $sum = $dbSelect->fetchValue(DbExpr::create('SUM(`id`)'));
        static::assertEquals(array_sum(Set::extract('/id', $testData)), $sum);

        // via static
        $dbSelect = Select::from('admins', $adapter);
        static::assertInstanceOf(Select::class, $dbSelect);
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
        static::getNewSelect()->columns(null)->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns3() {
        static::getNewSelect()->columns(1)->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column name for a key
     */
    public function testInvalidColumns4() {
        static::getNewSelect()->columns([''])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column name for a key 'qq'
     */
    public function testInvalidColumns5() {
        static::getNewSelect()->columns(['qq' => ''])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains an empty column alias
     */
    public function testInvalidColumns6() {
        static::getNewSelect()->columns(['' => 'qq'])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns7() {
        static::getNewSelect()->columns([$this])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain only strings and instances of DbExpr class
     */
    public function testInvalidColumns8() {
        static::getNewSelect()->columns([[]])->getQuery();
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
                'join_name' => null,
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['*', 'test'])
        );
        static::assertEquals(
            [
                'name' => '*',
                'alias' => null,
                'join_name' => 'JoinAlias',
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['JoinAlias.*', 'test'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => 'not_id',
                'join_name' => null,
                'type_cast' => null,
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['id as not_id'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => null,
                'type_cast' => 'int',
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['Admins.id::int'])
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => 'Other',
                'type_cast' => 'int',
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', ['Other.id::int'])
        );
        $dbExpr = DbExpr::create('Other.id::int');
        $columnInfo = $this->callObjectMethod($dbSelect, 'analyzeColumnName', [$dbExpr]);
        static::assertArraySubset(
            [
                'alias' => null,
                'join_name' => null,
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
                'join_name' => null,
                'type_cast' => null,
            ],
            $columnInfo
        );
        static::assertInstanceOf(DbExpr::class, $columnInfo['name']);
        static::assertEquals($dbExpr->get(), $columnInfo['name']->get());
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage You must use JoinInfo->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'
     */
    public function testInvalidColumnsWithJoinName() {
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(static::getNewSelect()->columns(['OtherTable.id'])->getQuery())
        );
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage You must use JoinInfo->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'
     */
    public function testInvalidColumnsWithJoinName2() {
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(static::getNewSelect()->columns(['OtherTable' => '*'])->getQuery())
        );
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage You must use JoinInfo->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'
     */
    public function testInvalidColumnsWithJoinName3() {
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(static::getNewSelect()->columns(['OtherTable' => ['col1']])->getQuery())
        );
    }

    public function testColumns() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns([])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['*'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['id'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id"::integer AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['id::integer'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['Admins.id'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['id', 'login'])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id"::int AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns('id::int', 'login')->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", (SUM("id")) FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['id', DbExpr::create('SUM(`id`)')])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__not_id", (SUM("id")) AS "_Admins__sum" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['not_id' => 'id', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery())
        );
        static::assertEquals(
            'SELECT "Admins".*, (SUM("id")) AS "_Admins__sum" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['*', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery())
        );
        // test column alias shortening
        $query = $dbSelect->columns(['VeryLongColumnAliasSoItMustBeShortened' => 'id'])->getQuery();
        $shortAlias = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', ['VeryLongColumnAliasSoItMustBeShortened']);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__' . $shortAlias . '" FROM "admins" AS "Admins"',
            $query
        );
        $insertedData = static::fillAdminsTable();
        $expectedData = [];
        foreach ($insertedData as $data) {
            $expectedData[] = ['VeryLongColumnAliasSoItMustBeShortened' => $data['id']];
        }
        static::assertEquals($expectedData, $dbSelect->fetchMany());
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
        /** @noinspection PhpParamsInspection */
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
     * @expectedExceptionMessage There are no joins with names: OtherTable
     */
    public function testInvalidOrderBy7() {
        static::getNewSelect()->orderBy('OtherTable.id')->getQuery();
    }

    public function testOrderBy() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" ASC',
            $dbSelect->orderBy('id')->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" DESC',
            $dbSelect->orderBy('Admins.id', false, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id"::integer ASC',
            $dbSelect->orderBy('Admins.id::integer', true, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" DESC',
            $dbSelect->orderBy('Admins.id', false, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" DESC, "Admins"."email" DESC',
            $dbSelect->orderBy('email', false)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY (RANDOM())',
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
     * @expectedExceptionMessage There are no joins with names: OtherTable
     */
    public function testInvalidGroupBy6() {
        static::getNewSelect()->groupBy(['OtherTable.id'])->getQuery();
    }

    public function testGroupBy() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['Admins.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id", "Admins"."email"',
            $dbSelect->groupBy(['email'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY (RANDOM())',
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
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->limit([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $limit argument must be an integer
     */
    public function testInvalidLimit5() {
        /** @noinspection PhpParamsInspection */
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
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->offset([]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $offset argument must be an integer
     */
    public function testInvalidOffset5() {
        /** @noinspection PhpParamsInspection */
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
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->limit(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->noLimit()->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1 OFFSET 2',
            $dbSelect->limit(1)->offset(2)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" OFFSET 1',
            $dbSelect->limit(0)->offset(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 10 OFFSET 9',
            $dbSelect->page(10, 9)->getQuery()
        );
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage There are no joins with names: Test
     */
    public function testInvalidWhereUsingUnknownJoin() {
        static::getNewSelect()->where(['Test.id' => 1])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage There are no joins with names: Test
     */
    public function testInvalidHavingUsingUnknownJoin() {
        static::getNewSelect()->having(['Test.id' => 1])->getQuery();
    }

    public function testWhereAndHaving() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->where([])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' HAVING "Admins"."login"::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])->having(['login::varchar' => '2'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' AND "Admins"."login" = \'3\' HAVING "Admins"."login"::varchar = \'2\' AND "Admins"."email" = \'3\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])->having(['login::varchar' => '2', 'email' => '3'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])->having([DbExpr::create('SUM(`id`) > ``2``')])->getQuery()
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join with name 'Test' already defined
     */
    public function testInvalidJoin2() {
        $joinConfig = JoinInfo::create('Test', 'admins', 'id', JoinInfo::JOIN_INNER, 'settings', 'id');
        static::getNewSelect()->join($joinConfig)->join($joinConfig);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Invalid join name 'NotTest' used in columns list for join named 'Test'
     */
    public function testInvalidJoinColumns() {
        $joinConfig = JoinInfo::create('Test', 'admins', 'id', JoinInfo::JOIN_INNER, 'settings', 'id')
            ->setForeignColumnsToSelect('Test.key', 'NotTest.value');
        static::getNewSelect()->join($joinConfig)->getQuery();
    }

    public function testJoins() {
        $dbSelect = static::getNewSelect();
        $joinConfig = JoinInfo::create('Test', 'admins', 'id', JoinInfo::JOIN_INNER, 'settings', 'id')
            ->setForeignColumnsToSelect('key', 'value');
        static::assertEquals(
            'SELECT "Admins".*, "Test"."key" AS "_Test__key", "Test"."value" AS "_Test__value" FROM "admins" AS "Admins" INNER JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinInfo::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'key' => 'name'
            ]);
        static::assertEquals(
            'SELECT "Admins".*, "Test".* FROM "admins" AS "Admins" LEFT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinInfo::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['value']);
        static::assertEquals(
            'SELECT "Admins".*, "Test"."value" AS "_Test__value" FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinInfo::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        // test join name shortening
        $joinConfig
            ->setJoinName('VeryLongJoinNameSoItMustBeShortened')
            ->setAdditionalJoinConditions(['VeryLongJoinNameSoItMustBeShortened.parentId' => null]);
        $query = $dbSelect->join($joinConfig, false)->getQuery();
        $shortJoinName = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', [$joinConfig->getJoinName()]);
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "' . $shortJoinName . '" ON ("Admins"."id" = "' . $shortJoinName . '"."id" AND "' . $shortJoinName .'"."parentId" IS NULL)',
            $query
        );

    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $selectAlias argument must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)
     */
    public function testInvalidWith1() {
        static::getNewSelect()->with(static::getNewSelect(), 'asdas as das das');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $selectAlias argument must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)
     */
    public function testInvalidWith2() {
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->with(static::getNewSelect(), []);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $selectAlias argument must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)
     */
    public function testInvalidWith3() {
        static::getNewSelect()->with(static::getNewSelect(), $this);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage WITH query with name 'test' already defined
     */
    public function testInvalidWith4() {
        static::getNewSelect()->with(static::getNewSelect(), 'test')->with(static::getNewSelect(), 'test');
    }

    public function testWith() {
        $dbSelect = static::getNewSelect();
        $dbSelect->with(static::getNewSelect(), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->getQuery()
        );
        $dbSelect->where([
            'id IN' => Select::from('subselect', static::getValidAdapter())
        ]);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Admins".* FROM "admins" AS "Admins" WHERE "Admins"."id" IN (SELECT "Subselect".* FROM "subselect" AS "Subselect")',
            $dbSelect->getQuery()
        );
        $dbSelect = Select::from('subselect', static::getValidAdapter())
            ->with(Select::from('admins', static::getValidAdapter()), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Subselect".* FROM "subselect" AS "Subselect"',
            $dbSelect->getQuery()
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
                'join_name' => null,
                'type_cast' => null
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int AS "_JoinAlias__colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [[
                'name' => 'colname',
                'alias' => null,
                'join_name' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int AS "_JoinAlias__colalias"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
    }

    public function testMakeTableNameWithAliasForQuery() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"admins" AS "Admins"',
            $this->callObjectMethod($dbSelect, 'makeTableNameWithAliasForQuery', ['admins', 'Admins'])
        );
        $expr = $this->callObjectMethod(
            $dbSelect,
            'makeTableNameWithAliasForQuery',
            ['admins', 'SomeTooLongTableAliasToMakeSystemShortenIt', 'other']
        );
        static::assertNotEquals('"other"."admins" AS "SomeTooLongTableAliasToMakeSystemShortenIt"', $expr);
        static::assertRegExp('%"other"\."admins" AS "[a-z][a-z0-9]+[0-9]"%', $expr);
    }

    public function testMakeColumnNameForCondition() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"Admins"."colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null
            ]])
        );
        static::assertEquals(
            '"Admins"."colname"::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => null,
                'type_cast' => 'int'
            ]])
        );
        static::assertEquals(
            '"JoinAlias"."colname"::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [[
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => 'JoinAlias',
                'type_cast' => 'int'
            ]])
        );
    }

    public function testGetShortJoinAlias() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'Admins',
            $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['Admins'])
        );
        for ($i = 0; $i < 30; $i++) {
            // it uses rand so it will be better to test it many times
            $alias = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['SomeTooLongTableAliasToMakeSystemShortenIt']);
            static::assertNotEquals('SomeTooLongTableAliasToMakeSystemShortenIt', $alias);
            static::assertRegExp('%^[a-z][a-z0-9]+$%', $alias);
        }
    }

    public function testGetShortColumnAlias() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'parent_id',
            $this->callObjectMethod($dbSelect, 'getShortColumnAlias', ['parent_id'])
        );
        for ($i = 0; $i < 30; $i++) {
            // it uses rand so it will be better to test it many times
            $alias = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', ['SomeTooLongColumnAliasToMakeSystemShortenIt']);
            static::assertNotEquals('SomeTooLongColumnAliasToMakeSystemShortenIt', $alias);
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
        /** @noinspection PhpParamsInspection */
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
     * @expectedExceptionMessage HAVING key in $conditionsAndOptions argument must be an array like conditions
     */
    public function testInvalidFromConfigsArrayHaving1() {
        static::getNewSelect()->fromConfigsArray([
            'HAVING' => $this
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage HAVING key in $conditionsAndOptions argument must be an array like conditions
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
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must contain only instances of JoinInfo class
     */
    public function testInvalidFromConfigsArrayJoins3() {
        static::getNewSelect()->fromConfigsArray([
            'JOINS' => ['string']
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage JOINS key in $conditionsAndOptions argument must contain only instances of JoinInfo class
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
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            static::getNewSelect()->fromConfigsArray([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE "Admins"."colname" = \'value\'',
            static::getNewSelect()->fromConfigsArray(['colname' => 'value'])->getQuery()
        );
        $configs = [
            'colname' => 'value',
            'OR' => [
                'colname2' => 'value2',
                'colname3' => 'value3',
            ],
            'COLUMNS' => ['colname', 'colname2', 'colname3', '*'],
            'ORDER' => ['colname', 'Test.admin_id' => 'desc'],
            'LIMIT' => 10,
            'OFFSET' => 20,
            'GROUP' => ['colname', 'Test.admin_id'],
            'HAVING' => [
                'colname3' => 'value',
                'Test.admin_id >' => '1',
            ],
            'JOIN' => [
                JoinInfo::create('Test', 'admins', 'id', JoinInfo::JOIN_LEFT, 'settings', 'admin_id')
                    ->setForeignColumnsToSelect(['admin_id', 'setting_value' => 'Test.value'])
            ]
        ];
        static::assertEquals(
            'SELECT "Admins"."colname" AS "_Admins__colname", "Admins"."colname2" AS "_Admins__colname2", "Admins"."colname3" AS "_Admins__colname3", "Admins".*, "Test"."admin_id" AS "_Test__admin_id", "Test"."value" AS "_Test__setting_value" FROM "admins" AS "Admins" LEFT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."admin_id") WHERE "Admins"."colname" = \'value\' AND ("Admins"."colname2" = \'value2\' OR "Admins"."colname3" = \'value3\') GROUP BY "Admins"."colname", "Test"."admin_id" HAVING "Admins"."colname3" = \'value\' AND "Test"."admin_id" > \'1\' ORDER BY "Admins"."colname" ASC, "Test"."admin_id" DESC LIMIT 10 OFFSET 20',
            static::getNewSelect()->fromConfigsArray($configs)->getQuery()
        );
        // todo: add tests for WITH
    }

    public function testNormalizeRecord() {
        // todo: add tests
    }
}