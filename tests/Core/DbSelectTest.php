<?php
/** @noinspection SqlRedundantOrderingDirection */

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\JoinConfig;
use PeskyORM\Core\Select;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use Swayok\Utils\Set;
use UnexpectedValueException;

class DbSelectTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    protected static function fillAdminsTable(): array
    {
        TestingApp::clearTables(static::getValidAdapter());
        $data = static::getTestDataForAdminsTableInsert();
        static::getValidAdapter()
            ->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }
    
    protected static function getValidAdapter(): Postgres
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public static function getTestDataForAdminsTableInsert(): array
    {
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
                'timezone' => 'Europe/Moscow',
                'big_data' => 'maaaaaaaaaaaaaaaaaaaaaany data'
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
                'timezone' => 'Europe/Moscow',
                'big_data' => 'maaaaaaaaaaaaaaaaaaaaaany data'
            ],
        ];
    }
    
    protected static function getNewSelect(): Select
    {
        return Select::from('admins', static::getValidAdapter());
    }
    
    public function convertTestDataForAdminsTableAssert($data)
    {
        foreach ($data as &$item) {
            $item['id'] = (string)$item['id'];
            $item['is_superadmin'] = (bool)$item['is_superadmin'];
            $item['is_active'] = (bool)$item['is_active'];
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
    }
    
    public function testInvalidTableNameInConstructor1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$tableName argument value cannot be empty');
        Select::from('', static::getValidAdapter());
    }
    
    public function testInvalidTableNameInConstructor2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches('%Argument #1 .* must be of type string, null given%i');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Select::from(null, static::getValidAdapter());
    }
    
    public function testInvalidFetchNextPage(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is impossible to use pagination when there is no limit");
        static::getNewSelect()
            ->fetchNextPage();
    }
    
    public function testConstructorAndBasicFetching(): void
    {
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
        $data = $dbSelect->limit(1)
            ->fetchNextPage();
        static::assertEquals([$testData[1]], $data);
    }
    
    public function testInvalidColumns1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings and instances of DbExpr class");
        static::getNewSelect()
            ->columns(null)
            ->getQuery();
    }
    
    public function testInvalidColumns3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings and instances of DbExpr class");
        static::getNewSelect()
            ->columns(1)
            ->getQuery();
    }
    
    public function testInvalidColumns4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains an empty column name for a key");
        static::getNewSelect()
            ->columns([''])
            ->getQuery();
    }
    
    public function testInvalidColumns5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains an empty column name for a key 'qq'");
        static::getNewSelect()
            ->columns(['qq' => ''])
            ->getQuery();
    }
    
    public function testInvalidColumns6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains an empty column alias");
        static::getNewSelect()
            ->columns(['' => 'qq'])
            ->getQuery();
    }
    
    public function testInvalidColumns7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings and instances of DbExpr class");
        static::getNewSelect()
            ->columns([$this])
            ->getQuery();
    }
    
    public function testInvalidColumns8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument must contain only strings and instances of DbExpr class");
        static::getNewSelect()
            ->columns([[]])
            ->getQuery();
    }
    
    public function testInvalidAnalyzeColumnName11(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', null);
    }
    
    public function testInvalidAnalyzeColumnName12(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columnName argument is not allowed to be an empty string");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', '');
    }
    
    public function testInvalidAnalyzeColumnName13(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', []);
    }
    
    public function testInvalidAnalyzeColumnName14(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columnName argument is not allowed to be an empty string');
        $dbSelect = static::getNewSelect();
        // false converted to empty string
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', false);
    }
    
    public function testInvalidAnalyzeColumnName15(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column name: [1]');
        $dbSelect = static::getNewSelect();
        // true converted to '1'
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', true);
    }
    
    public function testInvalidAnalyzeColumnName16(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid column name: [0test]");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', '0test');
    }
    
    public function testInvalidAnalyzeColumnName21(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #2 .*? must be of type \?string, array given%");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', []);
    }
    
    public function testInvalidAnalyzeColumnName23(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columnAlias argument is not allowed to be an empty string");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', '');
    }
    
    public function testInvalidAnalyzeColumnName24(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columnAlias argument contains invalid db entity name: [1]");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', true);
    }
    
    public function testInvalidAnalyzeColumnName25(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columnAlias argument is not allowed to be an empty string");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', false);
    }
    
    public function testInvalidAnalyzeColumnName30(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$joinName argument contains invalid db entity name: [1]");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', null, true);
    }
    
    public function testInvalidAnalyzeColumnName31(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$joinName argument is not allowed to be an empty string");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', null, false);
    }
    
    public function testInvalidAnalyzeColumnName32(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #3 .*? must be of type \?string, array given%");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', null, []);
    }
    
    public function testInvalidAnalyzeColumnName33(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$joinName argument is not allowed to be an empty string");
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'test', null, '');
    }
    
    public function testAnalyzeColumnName(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            [
                'name' => '*',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
                'json_selector' => null
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', '*', 'test')
        );
        static::assertEquals(
            [
                'name' => '*',
                'alias' => null,
                'join_name' => 'JoinAlias',
                'type_cast' => null,
                'parent' => 'Admins',
                'json_selector' => null
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'JoinAlias.*', 'test')
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => 'not_id',
                'join_name' => null,
                'type_cast' => null,
                'json_selector' => null
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'id as not_id')
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => null,
                'type_cast' => 'int',
                'json_selector' => null
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'Admins.id::int')
        );
        static::assertEquals(
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => 'Other',
                'type_cast' => 'int',
                'json_selector' => null
            ],
            $this->callObjectMethod($dbSelect, 'analyzeColumnName', 'Other.id::int')
        );
        $dbExpr = DbExpr::create('Other.id::int');
        $columnInfo = $this->callObjectMethod($dbSelect, 'analyzeColumnName', $dbExpr);
        static::assertEquals(
            [
                'name' => $dbExpr,
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ],
            $columnInfo
        );
        static::assertInstanceOf(DbExpr::class, $columnInfo['name']);
        static::assertEquals($dbExpr->get(), $columnInfo['name']->get());
        $columnInfo = $this->callObjectMethod($dbSelect, 'analyzeColumnName', $dbExpr, 'dbexpr');
        static::assertEquals(
            [
                'name' => $dbExpr,
                'alias' => 'dbexpr',
                'join_name' => null,
                'type_cast' => null,
            ],
            $columnInfo
        );
        static::assertInstanceOf(DbExpr::class, $columnInfo['name']);
        static::assertEquals($dbExpr->get(), $columnInfo['name']->get());
    }
    
    public function testInvalidColumnsWithJoinName(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "You must use JoinConfig->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'"
        );
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(
                static::getNewSelect()
                    ->columns(['OtherTable.id'])
                    ->getQuery()
            )
        );
    }
    
    public function testInvalidColumnsWithJoinName2(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "You must use JoinConfig->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'"
        );
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(
                static::getNewSelect()
                    ->columns(['OtherTable' => '*'])
                    ->getQuery()
            )
        );
    }
    
    public function testInvalidColumnsWithJoinName3(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "You must use JoinConfig->setForeignColumnsToSelect() to set the columns list to select for join named 'OtherTable'"
        );
        static::assertEquals(
            'SELECT "OtherTable"."id" AS "_OtherTable__id" FROM "admins" AS "Admins"',
            rtrim(
                static::getNewSelect()
                    ->columns(['OtherTable' => ['col1']])
                    ->getQuery()
            )
        );
    }
    
    public function testColumns(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns([])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['*'])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['id'])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT ("Admins"."id")::integer AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['id::integer'])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['Admins.id'])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['id', 'login'])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT ("Admins"."id")::int AS "_Admins__id", "Admins"."login" AS "_Admins__login" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns('id::int', 'login')
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", (SUM("id")) FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['id', DbExpr::create('SUM(`id`)')])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__not_id", (SUM("id")) AS "_Admins__sum" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['not_id' => 'id', 'sum' => DbExpr::create('SUM(`id`)')])
                    ->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "Admins".*, (SUM("id")) AS "_Admins__sum" FROM "admins" AS "Admins"',
            rtrim(
                $dbSelect->columns(['*', 'sum' => DbExpr::create('SUM(`id`)')])
                    ->getQuery()
            )
        );
        // test column alias shortening
        $query = $dbSelect->columns(['VeryLongColumnAliasSoItMustBeShortened' => 'id'])
            ->getQuery();
        $shortAlias = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', 'VeryLongColumnAliasSoItMustBeShortened');
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
    
    public function testInvalidOrderBy1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columnName argument cannot be empty');
        static::getNewSelect()->orderBy('');
    }
    
    public function testInvalidOrderBy2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(null);
    }
    
    public function testInvalidOrderBy3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(false);
    }
    
    public function testInvalidOrderBy4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(true);
    }
    
    public function testInvalidOrderBy5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->orderBy([]);
    }
    
    public function testInvalidOrderBy6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\Core\DbExpr|string');
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->orderBy($this);
    }
    
    public function testInvalidOrderBy7(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: OtherTable");
        static::getNewSelect()
            ->orderBy('OtherTable.id')
            ->getQuery();
    }
    
    public function testOrderBy(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" asc',
            $dbSelect->orderBy('id')
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" desc',
            $dbSelect->orderBy('Admins.id', false, true)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY ("Admins"."id")::integer asc',
            $dbSelect->orderBy('Admins.id::integer', true, true)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" desc',
            $dbSelect->orderBy('Admins.id', false, true)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY "Admins"."id" desc, "Admins"."email" desc',
            $dbSelect->orderBy('email', false)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" ORDER BY RANDOM()',
            $dbSelect->orderBy(DbExpr::create('RANDOM()'), '', false)
                ->getQuery()
        );
    }
    
    public function testInvalidGroupBy1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains invalid value at index '0'");
        static::getNewSelect()
            ->groupBy([null]);
    }
    
    public function testInvalidGroupBy2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains invalid value at index '0'");
        static::getNewSelect()
            ->groupBy([true]);
    }
    
    public function testInvalidGroupBy3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains invalid value at index '0'");
        static::getNewSelect()
            ->groupBy([false]);
    }
    
    public function testInvalidGroupBy4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains invalid value at index '0'");
        static::getNewSelect()
            ->groupBy([$this]);
    }
    
    public function testInvalidGroupBy5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$columns argument contains invalid value at index '0'");
        static::getNewSelect()
            ->groupBy([[]]);
    }
    
    public function testInvalidGroupBy6(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: OtherTable");
        static::getNewSelect()
            ->groupBy(['OtherTable.id'])
            ->getQuery();
    }
    
    public function testGroupBy(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['id'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id"',
            $dbSelect->groupBy(['Admins.id'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY "Admins"."id", "Admins"."email"',
            $dbSelect->groupBy(['email'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" GROUP BY (RANDOM())',
            $dbSelect->groupBy([DbExpr::create('RANDOM()')], false)
                ->getQuery()
        );
    }
    
    public function testInvalidLimit1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->limit(null);
    }
    
    public function testInvalidLimit2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->limit(true);
    }
    
    public function testInvalidLimit3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->limit(false);
    }
    
    public function testInvalidLimit4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->limit([]);
    }
    
    public function testInvalidLimit5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->limit($this);
    }
    
    public function testInvalidLimit6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$limit argument value must be a positive integer or 0');
        static::getNewSelect()->limit(-1);
    }
    
    public function testInvalidOffset1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->offset(null);
    }
    
    public function testInvalidOffset2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->offset(true);
    }
    
    public function testInvalidOffset3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->offset(false);
    }
    
    public function testInvalidOffset4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->offset([]);
    }
    
    public function testInvalidOffset5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type int%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->offset($this);
    }
    
    public function testInvalidOffset6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$offset argument value must be a positive integer or 0');
        static::getNewSelect()
            ->offset(-1);
    }
    
    public function testLimitAndOffset(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->limit(0)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->limit(0)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)
                ->offset(0)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1 OFFSET 2',
            $dbSelect->limit(1)
                ->offset(2)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 1',
            $dbSelect->limit(1)
                ->offset(0)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" OFFSET 1',
            $dbSelect->limit(0)
                ->offset(1)
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" LIMIT 10 OFFSET 9',
            $dbSelect->page(10, 9)
                ->getQuery()
        );
    }
    
    public function testInvalidWhereUsingUnknownJoin(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: Test");
        static::getNewSelect()
            ->where(['Test.id' => 1])
            ->getQuery();
    }
    
    public function testInvalidHavingUsingUnknownJoin(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: Test");
        static::getNewSelect()
            ->having(['Test.id' => 1])
            ->getQuery();
    }
    
    /** @noinspection SqlAggregates */
    public function testWhereAndHaving(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->where([])
                ->having([])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE ("Admins"."id")::int = \'1\' HAVING ("Admins"."login")::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])
                ->having(['login::varchar' => '2'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE ("Admins"."id")::int = \'1\' AND "Admins"."login" = \'3\' HAVING ("Admins"."login")::varchar = \'2\' AND "Admins"."email" = \'3\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])
                ->having(['login::varchar' => '2', 'email' => '3'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])
                ->having([DbExpr::create('SUM(`id`) > ``2``')])
                ->getQuery()
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }
    
    public function testInvalidJoin2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Join with name 'Test' already defined");
        $joinConfig = JoinConfig::create('Test', 'admins', 'id', JoinConfig::JOIN_INNER, 'settings', 'id');
        static::getNewSelect()
            ->join($joinConfig)
            ->join($joinConfig);
    }
    
    public function testInvalidJoinColumns(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Invalid join name 'NotTest' used in columns list for join named 'Test'");
        $joinConfig = JoinConfig::create('Test', 'admins', 'id', JoinConfig::JOIN_INNER, 'settings', 'id')
            ->setForeignColumnsToSelect('Test.key', 'NotTest.value');
        static::getNewSelect()
            ->join($joinConfig)
            ->getQuery();
    }
    
    public function testJoins(): void
    {
        $dbSelect = static::getNewSelect();
        $joinConfig = JoinConfig::create('Test', 'admins', 'id', JoinConfig::JOIN_INNER, 'settings', 'id')
            ->setForeignColumnsToSelect('key', 'value');
        static::assertEquals(
            'SELECT "Admins".*, "Test"."key" AS "_Test__key", "Test"."value" AS "_Test__value" FROM "admins" AS "Admins" INNER JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'key' => 'name',
            ]);
        static::assertEquals(
            'SELECT "Admins".*, "Test".* FROM "admins" AS "Admins" LEFT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['value']);
        static::assertEquals(
            'SELECT "Admins".*, "Test"."value" AS "_Test__value" FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_FULL)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" FULL JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        // test join name shortening
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_RIGHT)
            ->setJoinName('VeryLongJoinNameSoItMustBeShortened')
            ->setAdditionalJoinConditions(['VeryLongJoinNameSoItMustBeShortened.parentId' => null])
            ->setForeignColumnsToSelect([]);
        $query = $dbSelect->join($joinConfig, false)->getQuery();
        $shortJoinName = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', $joinConfig->getJoinName());
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" RIGHT JOIN "settings" AS "' . $shortJoinName . '" ON ("Admins"."id" = "' . $shortJoinName . '"."id" AND "' . $shortJoinName . '"."parentId" IS NULL)',
            $query
        );
    }
    
    public function testInvalidWith2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #2 .*? must be of type string%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->with(static::getNewSelect(), []);
    }
    
    public function testInvalidWith3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #2 .*? must be of type string%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->with(static::getNewSelect(), $this);
    }
    
    public function testInvalidWith4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("WITH query with name 'test' already defined");
        static::getNewSelect()
            ->with(static::getNewSelect(), 'test')
            ->with(static::getNewSelect(), 'test');
    }
    
    public function testWith(): void
    {
        $dbSelect = static::getNewSelect();
        $dbSelect->with(static::getNewSelect(), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Admins".* FROM "admins" AS "Admins"',
            $dbSelect->getQuery()
        );
        $dbSelect->where([
            'id IN' => Select::from('subselect', static::getValidAdapter()),
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
    
    public function testMakeColumnAlias(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '_Admins__colname',
            $this->callObjectMethod($dbSelect, 'makeColumnAlias', 'colname')
        );
        static::assertEquals(
            '_JoinAlias__colname',
            $this->callObjectMethod($dbSelect, 'makeColumnAlias', 'colname', 'JoinAlias')
        );
    }
    
    public function testMakeColumnNameWithAliasForQuery(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"Admins"."colname" AS "_Admins__colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [
                'name' => 'colname',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ])
        );
        static::assertEquals(
            '("JoinAlias"."colname")::int AS "_JoinAlias__colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [
                'name' => 'colname',
                'alias' => null,
                'join_name' => 'JoinAlias',
                'type_cast' => 'int',
            ])
        );
        static::assertEquals(
            '("JoinAlias"."colname")::int AS "_JoinAlias__colalias"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameWithAliasForQuery', [
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => 'JoinAlias',
                'type_cast' => 'int',
            ])
        );
    }
    
    public function testMakeTableNameWithAliasForQuery(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"admins" AS "Admins"',
            $this->callObjectMethod($dbSelect, 'makeTableNameWithAliasForQuery', 'admins', 'Admins')
        );
        $expr = $this->callObjectMethod(
            $dbSelect,
            'makeTableNameWithAliasForQuery',
            'admins',
            'SomeTooLongTableAliasToMakeSystemShortenIt',
            'other'
        );
        static::assertNotEquals('"other"."admins" AS "SomeTooLongTableAliasToMakeSystemShortenIt"', $expr);
        static::assertRegExp('%"other"\."admins" AS "[a-z][a-z0-9]+[0-9]"%', $expr);
    }
    
    public function testMakeColumnNameForCondition(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            '"Admins"."colname"',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [
                'name' => 'colname',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ])
        );
        static::assertEquals(
            '("Admins"."colname")::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => null,
                'type_cast' => 'int',
            ])
        );
        static::assertEquals(
            '("JoinAlias"."colname")::int',
            $this->callObjectMethod($dbSelect, 'makeColumnNameForCondition', [
                'name' => 'colname',
                'alias' => 'colalias',
                'join_name' => 'JoinAlias',
                'type_cast' => 'int',
            ])
        );
    }
    
    public function testGetShortJoinAlias(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'Admins',
            $this->callObjectMethod($dbSelect, 'getShortJoinAlias', 'Admins')
        );
        for ($i = 0; $i < 30; $i++) {
            // it uses rand so it will be better to test it many times
            $alias = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', 'SomeTooLongTableAliasToMakeSystemShortenIt');
            static::assertNotEquals('SomeTooLongTableAliasToMakeSystemShortenIt', $alias);
            static::assertRegExp('%^[a-z][a-z0-9]+$%', $alias);
        }
    }
    
    public function testGetShortColumnAlias(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'parent_id',
            $this->callObjectMethod($dbSelect, 'getShortColumnAlias', 'parent_id')
        );
        for ($i = 0; $i < 30; $i++) {
            // it uses rand so it will be better to test it many times
            $alias = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', 'SomeTooLongColumnAliasToMakeSystemShortenIt');
            static::assertNotEquals('SomeTooLongColumnAliasToMakeSystemShortenIt', $alias);
            static::assertRegExp('%^[a-z][a-z0-9]+$%', $alias);
        }
    }
    
    public function testInvalidSetDbSchemaName1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type string, null given%");
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->setTableSchemaName(null);
    }
    
    public function testInvalidSetDbSchemaName2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$tableSchema argument value cannot be empty');
        static::getNewSelect()->setTableSchemaName('');
    }
    
    public function testInvalidSetDbSchemaName3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($tableSchema) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->setTableSchemaName(true);
    }
    
    public function testInvalidSetDbSchemaName4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($tableSchema) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->setTableSchemaName(false);
    }
    
    public function testInvalidSetDbSchemaName5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type string%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->setTableSchemaName(['arr']);
    }
    
    public function testInvalidSetDbSchemaName6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches("%Argument #1 .*? must be of type string%");
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->setTableSchemaName($this);
    }
    
    public function testSetDbSchemaName(): void
    {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'test_schema',
            $dbSelect->setTableSchemaName('test_schema')
                ->getTableSchemaName()
        );
        static::assertEquals('SELECT "Admins".* FROM "test_schema"."admins" AS "Admins"', $dbSelect->getQuery());
    }
    
    public function testInvalidFromConfigsArrayOrder1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'ORDER\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'ORDER' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayOrder2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'ORDER\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'ORDER' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayOrder3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'ORDER\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'ORDER' => 'colname ASC',
            ]);
    }
    
    public function testInvalidFromConfigsArrayOrder4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$direction argument must be a boolean or string');
        static::getNewSelect()
            ->fromConfigsArray([
                'ORDER' => ['colname' => 'NONE'],
            ]);
    }
    
    public function testInvalidFromConfigsArrayGroup1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'GROUP\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'GROUP' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayGroup2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'GROUP\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'GROUP' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayHaving1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'HAVING\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'HAVING' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayHaving2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'HAVING\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'HAVING' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayJoins1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'JOINS\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'JOINS' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayJoins2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'JOINS\']: value must be an array');
        static::getNewSelect()
            ->fromConfigsArray([
                'JOINS' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayJoins3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'JOINS\'][0]: value must be instance of');
        static::getNewSelect()
            ->fromConfigsArray([
                'JOINS' => ['string'],
            ]);
    }
    
    public function testInvalidFromConfigsArrayJoins4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$conditionsAndOptions[\'JOINS\'][0]: value must be instance of');
        static::getNewSelect()
            ->fromConfigsArray([
                'JOINS' => [$this],
            ]);
    }
    
    public function testInvalidFromConfigsArrayLimit1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($limit) must be of type int');
        static::getNewSelect()
            ->fromConfigsArray([
                'LIMIT' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayLimit2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($limit) must be of type int');
        static::getNewSelect()
            ->fromConfigsArray([
                'LIMIT' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayLimit3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$limit argument value must be a positive integer or 0');
        static::getNewSelect()
            ->fromConfigsArray([
                'LIMIT' => -1,
            ]);
    }
    
    public function testInvalidFromConfigsArrayOffset1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($offset) must be of type int');
        static::getNewSelect()
            ->fromConfigsArray([
                'OFFSET' => $this,
            ]);
    }
    
    public function testInvalidFromConfigsArrayOffset2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($offset) must be of type int');
        static::getNewSelect()
            ->fromConfigsArray([
                'OFFSET' => true,
            ]);
    }
    
    public function testInvalidFromConfigsArrayOffset3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$offset argument value must be a positive integer or 0');
        static::getNewSelect()
            ->fromConfigsArray([
                'OFFSET' => -1,
            ]);
    }
    
    public function testFromConfigsArray(): void
    {
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins"',
            static::getNewSelect()
                ->fromConfigsArray([])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins".* FROM "admins" AS "Admins" WHERE "Admins"."colname" = \'value\'',
            static::getNewSelect()
                ->fromConfigsArray(['colname' => 'value'])
                ->getQuery()
        );
        $configs = [
            'colname' => 'value',
            'OR' => [
                'colname2' => 'value2',
                'colname3' => 'value3',
            ],
            'ORDER' => ['colname', 'Test.admin_id' => 'desc'],
            'LIMIT' => 10,
            'OFFSET' => 20,
            'GROUP' => ['colname', 'Test.admin_id'],
            'HAVING' => [
                'colname3' => 'value',
                'Test.admin_id >' => '1',
            ],
            'JOIN' => [
                JoinConfig::create('Test', 'admins', 'id', JoinConfig::JOIN_LEFT, 'settings', 'admin_id')
                    ->setForeignColumnsToSelect(['admin_id', 'setting_value' => 'Test.value']),
            ],
        ];
        /** @noinspection SqlAggregates */
        static::assertEquals(
            'SELECT "Admins"."colname" AS "_Admins__colname", "Admins"."colname2" AS "_Admins__colname2", "Admins"."colname3" AS "_Admins__colname3", "Admins".*, "Test"."admin_id" AS "_Test__admin_id", "Test"."value" AS "_Test__setting_value" FROM "admins" AS "Admins" LEFT JOIN "settings" AS "Test" ON ("Admins"."id" = "Test"."admin_id") WHERE "Admins"."colname" = \'value\' AND ("Admins"."colname2" = \'value2\' OR "Admins"."colname3" = \'value3\') GROUP BY "Admins"."colname", "Test"."admin_id" HAVING "Admins"."colname3" = \'value\' AND "Test"."admin_id" > \'1\' ORDER BY "Admins"."colname" asc, "Test"."admin_id" desc LIMIT 10 OFFSET 20',
            static::getNewSelect()
                ->columns('colname', 'colname2', 'colname3', '*')
                ->fromConfigsArray($configs)
                ->getQuery()
        );
        // todo: add tests for WITH
    }
    
    public function testNormalizeRecord(): void
    {
        static::assertTrue(true);
        // todo: add tests
    }
}
