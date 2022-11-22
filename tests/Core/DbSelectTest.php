<?php
/** @noinspection SqlRedundantOrderingDirection */

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\DbExpr;
use PeskyORM\Join\JoinConfig;
use PeskyORM\Select\Select;
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
        static::assertInstanceOf(\PeskyORM\Select\Select::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertEquals([], $this->getObjectPropertyValue($dbSelect, 'columns')); //< not initialized before query builder launched
        static::assertEquals('SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "admins" AS "tbl_Admins_1"', rtrim($dbSelect->getCountQuery()));
        static::assertEquals('SELECT 1 FROM "admins" AS "tbl_Admins_2" LIMIT 1', rtrim($dbSelect->getExistenceQuery()));
        
        $insertedData = static::fillAdminsTable();
        $testData = $this->convertTestDataForAdminsTableAssert($insertedData);
        static::assertEquals(2, $dbSelect->fetchCount());
        static::assertTrue($dbSelect->fetchExistence());
        $data = $dbSelect->fetchMany();
        static::assertEquals($testData, $data);
        $data = $dbSelect->fetchOne();
        static::assertEquals($testData[0], $data);
        $data = $dbSelect->fetchColumn('id');
        static::assertEquals(Set::extract('/id', $testData), $data);
        $data = $dbSelect->fetchAssoc('id', 'login');
        static::assertEquals(Set::combine($testData, '/id', '/login'), $data);
        $sum = $dbSelect->fetchValue(\PeskyORM\DbExpr::create('SUM(`id`)'));
        static::assertEquals(array_sum(Set::extract('/id', $testData)), $sum);
        
        // via static
        $dbSelect = Select::from('admins', $adapter);
        static::assertInstanceOf(Select::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertEquals('admins', $dbSelect->getTableName());
        $data = $dbSelect->limit(1)->fetchNextPage();
        static::assertEquals([$testData[1]], $data);
    }
    
    public function testInvalidColumns1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getNewSelect()
            ->columns(null)
            ->getQuery();
    }
    
    public function testInvalidColumns3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of ');
        static::getNewSelect()
            ->columns(1)
            ->getQuery();
    }
    
    public function testInvalidColumns4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getNewSelect()
            ->columns([''])
            ->getQuery();
    }
    
    public function testInvalidColumns5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[qq] argument value cannot be empty');
        static::getNewSelect()
            ->columns(['qq' => ''])
            ->getQuery();
    }
    
    public function testInvalidColumns6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns argument contains empty string as key for value');
        static::getNewSelect()
            ->columns(['' => 'qq'])
            ->getQuery();
    }
    
    public function testInvalidColumns7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0]: value must be a string or instance of');
        static::getNewSelect()
            ->columns([$this])
            ->getQuery();
    }
    
    public function testInvalidColumns8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columns[0] argument value cannot be empty');
        static::getNewSelect()
            ->columns([[]])
            ->getQuery();
    }
    
    public function testInvalidAnalyzeColumnName11(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
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
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
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
        $dbExpr = \PeskyORM\DbExpr::create('Other.id::int');
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                $dbSelect->columns([])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1"',
            rtrim(
                $dbSelect->columns(['*'])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['id'])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT ("tbl_Admins_0"."id")::integer AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['id::integer'])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['Admins.id'])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Admins_0"."login" AS "col_Admins__login_1" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['id', 'login'])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT ("tbl_Admins_0"."id")::int AS "col_Admins__id_0", "tbl_Admins_0"."login" AS "col_Admins__login_1" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns('id::int', 'login')->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", (SUM("id")) FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['id', DbExpr::create('SUM(`id`)')])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__not_id_0", (SUM("id")) AS "col_Admins__sum_1" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['not_id' => 'id', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery()
            )
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".*, (SUM("id")) AS "col_Admins__sum_1" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['*', 'sum' => \PeskyORM\DbExpr::create('SUM(`id`)')])->getQuery()
            )
        );
        // test column alias shortening
        $dbSelect = static::getNewSelect()
            ->columns(['VeryLongColumnAliasSoItMustBeShortenedButWeNeedMoreThen60Caracters' => 'id']);

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admns__VrLngClmnAlsSItMstBShrtndBtWNdMrThn60Caracters_0" FROM "admins" AS "tbl_Admins_0"',
            $dbSelect->getQuery()
        );
        $insertedData = static::fillAdminsTable();
        $expectedData = [];
        foreach ($insertedData as $data) {
            $expectedData[] = ['VeryLongColumnAliasSoItMustBeShortenedButWeNeedMoreThen60Caracters' => $data['id']];
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
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(null);
    }
    
    public function testInvalidOrderBy3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(false);
    }
    
    public function testInvalidOrderBy4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        static::getNewSelect()->orderBy(true);
    }
    
    public function testInvalidOrderBy5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
        /** @noinspection PhpParamsInspection */
        static::getNewSelect()->orderBy([]);
    }
    
    public function testInvalidOrderBy6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($columnName) must be of type PeskyORM\DbExpr|string');
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" asc',
            $dbSelect->orderBy('id')->getQuery()
        );
        // using boolean as direction
        static::assertEquals(
            'SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1" ORDER BY "tbl_Admins_1"."id" desc',
            $dbSelect->orderBy('Admins.id', false, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_2".* FROM "admins" AS "tbl_Admins_2" ORDER BY ("tbl_Admins_2"."id")::integer asc',
            $dbSelect->orderBy('Admins.id::integer', true, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_3".* FROM "admins" AS "tbl_Admins_3" ORDER BY "tbl_Admins_3"."id" desc',
            $dbSelect->orderBy('Admins.id', false, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_4".* FROM "admins" AS "tbl_Admins_4" ORDER BY "tbl_Admins_4"."id" desc, "tbl_Admins_4"."email" asc',
            $dbSelect->orderBy('email')->getQuery()
        );
        // using constants as direction
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" asc',
            static::getNewSelect()->orderBy('Admins.id', Select::ORDER_DIRECTION_ASC, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" asc nulls first',
            static::getNewSelect()->orderBy('Admins.id', \PeskyORM\Select\Select::ORDER_DIRECTION_ASC_NULLS_FIRST, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" asc nulls last',
            static::getNewSelect()->orderBy('Admins.id', \PeskyORM\Select\Select::ORDER_DIRECTION_ASC_NULLS_LAST, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" desc',
            static::getNewSelect()->orderBy('Admins.id', Select::ORDER_DIRECTION_DESC, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" desc nulls first',
            static::getNewSelect()->orderBy('Admins.id', \PeskyORM\Select\Select::ORDER_DIRECTION_DESC_NULLS_FIRST, true)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY "tbl_Admins_0"."id" desc nulls last',
            static::getNewSelect()->orderBy('Admins.id', \PeskyORM\Select\Select::ORDER_DIRECTION_DESC_NULLS_LAST, true)->getQuery()
        );
        // DbExpr
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" ORDER BY RANDOM()',
            static::getNewSelect()->orderBy(\PeskyORM\DbExpr::create('RANDOM()'), '')->getQuery()
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" GROUP BY "tbl_Admins_0"."id"',
            $dbSelect->groupBy(['id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1" GROUP BY "tbl_Admins_1"."id"',
            $dbSelect->groupBy(['Admins.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_2".* FROM "admins" AS "tbl_Admins_2" GROUP BY "tbl_Admins_2"."id", "tbl_Admins_2"."email"',
            $dbSelect->groupBy(['email'])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_3".* FROM "admins" AS "tbl_Admins_3" GROUP BY RANDOM()',
            $dbSelect->groupBy([\PeskyORM\DbExpr::create('RANDOM()')], false)->getQuery()
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            $dbSelect->limit(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1" LIMIT 1',
            $dbSelect->limit(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_2".* FROM "admins" AS "tbl_Admins_2"',
            $dbSelect->limit(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_3".* FROM "admins" AS "tbl_Admins_3" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_4".* FROM "admins" AS "tbl_Admins_4" LIMIT 1 OFFSET 2',
            $dbSelect->limit(1)->offset(2)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_5".* FROM "admins" AS "tbl_Admins_5" LIMIT 1',
            $dbSelect->limit(1)->offset(0)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_6".* FROM "admins" AS "tbl_Admins_6" OFFSET 1',
            $dbSelect->limit(0)->offset(1)->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_7".* FROM "admins" AS "tbl_Admins_7" LIMIT 10 OFFSET 9',
            $dbSelect->page(10, 9)->getQuery()
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            $dbSelect->where([])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1" WHERE ("tbl_Admins_1"."id")::int = \'1\' HAVING ("tbl_Admins_1"."login")::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])
                ->having(['login::varchar' => '2'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_2".* FROM "admins" AS "tbl_Admins_2" WHERE ("tbl_Admins_2"."id")::int = \'1\' AND "tbl_Admins_2"."login" = \'3\' HAVING ("tbl_Admins_2"."login")::varchar = \'2\' AND "tbl_Admins_2"."email" = \'3\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])
                ->having(['login::varchar' => '2', 'email' => '3'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_3".* FROM "admins" AS "tbl_Admins_3" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])
                ->having([\PeskyORM\DbExpr::create('SUM(`id`) > ``2``')])
                ->getQuery()
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }
    
    public function testInvalidJoin2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Join with name 'Test' already defined");
        $joinConfig = \PeskyORM\Join\JoinConfig::create('Test', 'admins', 'id', \PeskyORM\Join\JoinConfig::JOIN_INNER, 'settings', 'id');
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
        $joinConfig = \PeskyORM\Join\JoinConfig::create('Test', 'admins', 'id', \PeskyORM\Join\JoinConfig::JOIN_INNER, 'settings', 'id')
            ->setForeignColumnsToSelect('key', 'value');
        static::assertEquals(
            'SELECT "tbl_Admins_0".*, "tbl_Test_1"."key" AS "col_Test__key_1", "tbl_Test_1"."value" AS "col_Test__value_2" FROM "admins" AS "tbl_Admins_0" INNER JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."id")',
            static::getNewSelect()->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(\PeskyORM\Join\JoinConfig::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'key' => 'name',
            ]);
        static::assertEquals(
            'SELECT "tbl_Admins_0".*, "tbl_Test_1".* FROM "admins" AS "tbl_Admins_0" LEFT JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."id" AND "tbl_Test_1"."key" = \'name\')',
            static::getNewSelect()->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['value']);
        static::assertEquals(
            'SELECT "tbl_Admins_0".*, "tbl_Test_1"."value" AS "col_Test__value_1" FROM "admins" AS "tbl_Admins_0" RIGHT JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."id" AND "tbl_Test_1"."key" = \'name\')',
            static::getNewSelect()->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(\PeskyORM\Join\JoinConfig::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" RIGHT JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."id")',
            static::getNewSelect()->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(\PeskyORM\Join\JoinConfig::JOIN_FULL)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" FULL JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."id")',
            static::getNewSelect()->join($joinConfig)->getQuery()
        );
        // test join name shortening
        $joinConfig
            ->setJoinType(JoinConfig::JOIN_RIGHT)
            ->setJoinName('VeryLongJoinNameSoItMustBeShortenedButWeNeedMoreThen60Characters')
            ->setAdditionalJoinConditions([
                'VeryLongJoinNameSoItMustBeShortenedButWeNeedMoreThen60Characters.parentId' => null
            ])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"'
            . ' RIGHT JOIN "settings" AS "tbl_VrLngJnNmSItMstBShrtenedButWeNeedMoreThen60Characters_1"'
            . ' ON ("tbl_Admins_0"."id" = "tbl_VrLngJnNmSItMstBShrtenedButWeNeedMoreThen60Characters_1"."id"'
            . ' AND "tbl_VrLngJnNmSItMstBShrtenedButWeNeedMoreThen60Characters_1"."parentId" IS NULL)',
            static::getNewSelect()->join($joinConfig)->getQuery()
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
            'WITH "subselect" AS (SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0")'
            . ' SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            $dbSelect->getQuery()
        );
        $dbSelect->where([
            'id IN' => Select::from('subselect', static::getValidAdapter()),
        ]);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1")'
            . ' SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1"'
            . ' WHERE "tbl_Admins_1"."id" IN (SELECT "tbl_Subselect_0".* FROM "subselect" AS "tbl_Subselect_0")',
            $dbSelect->getQuery()
        );
        $dbSelect = Select::from('subselect', static::getValidAdapter())
            ->with(Select::from('admins', static::getValidAdapter()), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0")'
            . ' SELECT "tbl_Subselect_0".* FROM "subselect" AS "tbl_Subselect_0"',
            $dbSelect->getQuery()
        );
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
        static::assertEquals('SELECT "tbl_Admins_0".* FROM "test_schema"."admins" AS "tbl_Admins_0"', $dbSelect->getQuery());
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
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            static::getNewSelect()
                ->fromConfigsArray([])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0" WHERE "tbl_Admins_0"."colname" = \'value\'',
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
                \PeskyORM\Join\JoinConfig::create('Test', 'admins', 'id', \PeskyORM\Join\JoinConfig::JOIN_LEFT, 'settings', 'admin_id')
                    ->setForeignColumnsToSelect(['admin_id', 'setting_value' => 'Test.value']),
            ],
        ];
        /** @noinspection SqlAggregates */
        static::assertEquals(
            'SELECT "tbl_Admins_0"."colname" AS "col_Admins__colname_0",'
            . ' "tbl_Admins_0"."colname2" AS "col_Admins__colname2_1",'
            . ' "tbl_Admins_0"."colname3" AS "col_Admins__colname3_2",'
            . ' "tbl_Admins_0".*,'
            . ' "tbl_Test_1"."admin_id" AS "col_Test__admin_id_4",'
            . ' "tbl_Test_1"."value" AS "col_Test__setting_value_5"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "settings" AS "tbl_Test_1" ON ("tbl_Admins_0"."id" = "tbl_Test_1"."admin_id")'
            . ' WHERE "tbl_Admins_0"."colname" = \'value\''
            . ' AND ("tbl_Admins_0"."colname2" = \'value2\' OR "tbl_Admins_0"."colname3" = \'value3\')'
            . ' GROUP BY "tbl_Admins_0"."colname", "tbl_Test_1"."admin_id"'
            . ' HAVING "tbl_Admins_0"."colname3" = \'value\' AND "tbl_Test_1"."admin_id" > \'1\''
            . ' ORDER BY "tbl_Admins_0"."colname" asc, "tbl_Test_1"."admin_id" desc'
            . ' LIMIT 10 OFFSET 20',
            static::getNewSelect()
                ->columns('colname', 'colname2', 'colname3', '*')
                ->fromConfigsArray($configs)
                ->getQuery()
        );

        static::assertEquals(
            'WITH "Subselect" AS (SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0")'
            . ' SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0"',
            static::getNewSelect()
                ->columns('*')
                ->fromConfigsArray([
                    'WITH' => ['Subselect' => static::getNewSelect()->columns('*')]
                ])
                ->getQuery()
        );
    }
    
    public function testNormalizeRecord(): void
    {
        static::assertTrue(true);
        // todo: add tests
    }
}
