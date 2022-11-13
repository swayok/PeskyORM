<?php
/** @noinspection SqlRedundantOrderingDirection */

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Select;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\OrmJoinInfo;
use PeskyORM\ORM\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\Data\TestDataForAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableLongAlias;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTableStructure;
use Swayok\Utils\Set;

class OrmSelectTest extends BaseTestCase
{

    use TestDataForAdminsTable;

    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }

    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }

    protected static function getValidAdapter(): Postgres
    {
        return TestingApp::getPgsqlConnection();
    }

    protected static function getNewSelect(): OrmSelect
    {
        return OrmSelect::from(TestingAdminsTable::getInstance());
    }

    public function testConstructorAndBasicFetching(): void
    {
        // via new
        $dbSelect = self::getNewSelect()
            ->columns('id');
        static::assertInstanceOf(OrmSelect::class, $dbSelect);
        static::assertInstanceOf(TestingAdminsTable::class, $dbSelect->getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, $dbSelect->getTableStructure());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertCount(1, $this->getObjectPropertyValue($dbSelect, 'columnsRaw'));
        static::assertCount(0, $this->getObjectPropertyValue($dbSelect, 'columns'));


        static::assertEquals(['id'], $this->getObjectPropertyValue($dbSelect, 'columnsRaw'));
        static::assertEquals('SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "admins" AS "tbl_Admins_1"', rtrim($dbSelect->getCountQuery()));
        $expectedColsInfo = [
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
                'json_selector' => null,
            ],
        ];
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));

        $insertedData = TestingApp::fillAdminsTable(2);
        $testData = $this->convertTestDataForAdminsTableAssert($insertedData, true);
        unset($testData[0]['big_data'], $testData[1]['big_data']);
        $dbSelect->columns('*');
        $count = $dbSelect->fetchCount();
        static::assertEquals(2, $count);
        $data = $dbSelect->fetchMany();
        static::assertEquals($testData, $data);
        $data = $dbSelect->fetchOne();
        static::assertEquals($testData[0], $data);
        $data = $dbSelect->columns(['id'])
            ->fetchColumn();
        static::assertEquals(Set::extract('/id', $testData), $data);
        $data = $dbSelect->fetchAssoc('id', 'login');
        static::assertEquals(Set::combine($testData, '/id', '/login'), $data);
        $sum = $dbSelect->fetchValue(DbExpr::create('SUM(`id`)'));
        static::assertEquals(array_sum(Set::extract('/id', $testData)), $sum);

        // via static
        $dbSelect = OrmSelect::from(TestingAdminsTable::getInstance());
        static::assertInstanceOf(OrmSelect::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertInstanceOf(TestingAdminsTable::class, $dbSelect->getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, $dbSelect->getTableStructure());
        static::assertEquals('admins', $dbSelect->getTableName());
        $data = $dbSelect->limit(1)
            ->fetchNextPage();
        static::assertEquals([$testData[1]], $data);
    }

    public function testInvalidJoinsSet(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure does not know about relation named 'OtherTable'");
        static::getNewSelect()
            ->columns(['id', 'OtherTable.id'])
            ->getQuery();
    }

    public function testNotExistingColumnName1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [asid] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['asid'])
            ->getQuery();
    }

    public function testNotExistingColumnName2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [Parent.asid] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['id', 'Parent.asid'])
            ->getQuery();
    }

    public function testNotExistingColumnName3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [asid] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        // Parent is column alias, not a relation
        static::getNewSelect()
            ->columns(['Parent' => 'asid'])
            ->getQuery();
    }

    public function testNotExistingColumnName4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "SELECT: Column with name [Parent.asid] and alias [asialias] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['asialias' => 'asid']])
            ->getQuery();
    }

    public function testEmptyColumnsListForRootTable(): void
    {
        $query = static::getNewSelect()
            ->columns(['Parent.id'])
            ->getQuery();
        static::assertEquals(
            'SELECT "tbl_Parent_0"."id" AS "col_Parent__id_0" FROM "admins" AS "tbl_Admins_1"'
            . ' LEFT JOIN "admins" AS "tbl_Parent_0" ON ("tbl_Admins_1"."parent_id" = "tbl_Parent_0"."id")',
            $query
        );
    }

    public function testInvalidColNameInDbExpr(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [asdasdqdasd] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['sum' => DbExpr::create('SUM(`Admins`.`asdasdqdasd`)')])
            ->getQuery();
    }

    public function testColumnsBasic(): void
    {
        $dbSelect = static::getNewSelect();
        $expectedColsInfo = [];
        $colsInSelect = [];
        $bigDataColsInfo = [];
        $bigDataColsInSelect = [];
        $bigDataColsNames = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if (!$column->isItExistsInDb()) {
                continue;
            }
            $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', $column->getName(), 'Admins');
            if ($column->isValueHeavy()) {
                $bigDataColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                    'json_selector' => null,
                ];
                $bigDataColsInSelect[] = '"tbl_Admins_0"."' . $column->getName() . '" AS "' . $shortName . '"';
                $bigDataColsNames[] = $column->getName();
            } else {
                $expectedColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                    'json_selector' => null,
                ];
                $colsInSelect[] = '"tbl_Admins_0"."' . $column->getName() . '" AS "' . $shortName . '"';
            }
        }
        $colsInSelectWithoutLastOne = $colsInSelect;
        array_pop($colsInSelectWithoutLastOne);
        $colsInSelectWithoutLastOne = implode(', ', $colsInSelectWithoutLastOne);
        $expectedColsInfoWithoutLastOne = $expectedColsInfo;
        $excludedColumnInfo = array_pop($expectedColsInfoWithoutLastOne);
        $colsCountInSelect = count($colsInSelect);
        $colsInSelect = implode(', ', $colsInSelect);
        $bigDataColsInSelect = implode(', ', $bigDataColsInSelect);
        static::assertGreaterThanOrEqual(1, count($expectedColsInfo));

        $dbSelect = static::getNewSelect()->columns([]);
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfo), $this->getObjectPropertyValue($dbSelect, 'columns'));

        $dbSelect = static::getNewSelect()->columns(['*']);
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfo), $this->getObjectPropertyValue($dbSelect, 'columns'));

        $dbSelect = static::getNewSelect()->columns('*');
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfo), $this->getObjectPropertyValue($dbSelect, 'columns'));

        // test adding heavy valued column explicitely
        static::assertGreaterThanOrEqual(1, count($bigDataColsNames));
        $dbSelect = static::getNewSelect()->columns(array_merge(['*'], $bigDataColsNames));
        static::assertEquals(
            'SELECT ' . $colsInSelect . ', ' . $bigDataColsInSelect . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals(array_merge($expectedColsInfo, $bigDataColsInfo), $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfo) + count($bigDataColsInfo), $this->getObjectPropertyValue($dbSelect, 'columns'));

        // test excluding a column (string value)
        $dbSelect = static::getNewSelect()->columns(['*' => $excludedColumnInfo['name']]);
        static::assertEquals(
            'SELECT ' . $colsInSelectWithoutLastOne . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfoWithoutLastOne, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfoWithoutLastOne), $this->getObjectPropertyValue($dbSelect, 'columns'));

        // test excluding a column (array value)
        $dbSelect = static::getNewSelect()->columns(['*' => [$excludedColumnInfo['name']]]);
        static::assertEquals(
            'SELECT ' . $colsInSelectWithoutLastOne . ' FROM "admins" AS "tbl_Admins_0"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfoWithoutLastOne, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(count($expectedColsInfoWithoutLastOne), $this->getObjectPropertyValue($dbSelect, 'columns'));

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['id'])->getQuery()
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
            'SELECT ' . $colsInSelect . ', (SUM("id")) AS "col_Admins__sum_' . $colsCountInSelect . '" FROM "admins" AS "tbl_Admins_0"',
            rtrim(
                static::getNewSelect()->columns(['*', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery()
            )
        );
    }

    public function testHasManyRelationException1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect"
        );
        static::getNewSelect()
            ->columns('id', 'Children.*')
            ->getQuery();
    }

    public function testHasManyRelationException2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Children.*']])
            ->getQuery();
    }

    public function testHasManyRelationException3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Children' => '*']])
            ->getQuery();
    }

    public function testHasManyRelationException4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Children' => ['*']]])
            ->getQuery();
    }

    public function testHasManyRelationException5(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Children' => []]])
            ->getQuery();
    }

    public function testHasManyRelationException6(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "SELECT: Column with name [Parent.qqq] and alias [Children] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Children' => 'qqq']])
            ->getQuery();
    }

    public function testInvalidColNameInRelation1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [Parent.qqq] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['qqq']])
            ->getQuery();
    }

    public function testInvalidColNameInRelation2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "SELECT: Column with name [Parent.qqq] and alias [key] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure"
        );
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['key' => 'qqq']])
            ->getQuery();
    }

    public function testInvalidColNameInRelation3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: Column with name [Parent.qqq] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['id', 'Parent.qqq'])
            ->getQuery();
    }

    public function testColumnsWithRelations(): void
    {
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Parent_1"."id" AS "col_Parent__id_1" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id")',
            static::getNewSelect()->columns(['id', 'Parent.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Parent_1"."id" AS "col_Parent__id_1" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id")',
            static::getNewSelect()->columns(['id', 'Parent' => ['id']])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0",'
            . ' "tbl_Parent_1"."id" AS "col_Parent__id_1",'
            . ' "tbl_Parent_1"."login" AS "col_Parent__login_2"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id")',
            static::getNewSelect()->columns(['id', 'Parent' => ['id', 'login']])->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0",'
            . ' "tbl_Parent_1"."id" AS "col_Parent__id_1",' 
            . ' "tbl_Parent2_2"."id" AS "col_Parent2__id_2"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id")'
            . ' LEFT JOIN "admins" AS "tbl_Parent2_2" ON ("tbl_Parent_1"."parent_id" = "tbl_Parent2_2"."id")',
            static::getNewSelect()->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => ['id']]])->getQuery()
        );

        $columns = static::getNewSelect()->getTable()->getTableStructure()->getColumns();

        $colsInSelectForParent = [];
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'getShortColumnAlias', '*', 'Parent');
        foreach ($columns as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', $column->getName(), 'Parent');
                $colsInSelectForParent[] = '"tbl_Parent_1"."' . $column->getName() . '" AS "' . $shortName . '"';
            }
        }
        $colsInSelectForParent = implode(', ', $colsInSelectForParent);


        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", ' . $colsInSelectForParent
            . ', "tbl_Parent2_2"."id" AS "col_Parent2__id_18"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id")'
            . ' LEFT JOIN "admins" AS "tbl_Parent2_2" ON ("tbl_Parent_1"."parent_id" = "tbl_Parent2_2"."id")',
            static::getNewSelect()->columns(['id', 'Parent' => ['*', 'Parent as Parent2' => ['id']]])->getQuery()
        );

        $colsInSelectForParent2 = [];
        $dbSelect = static::getNewSelect();
        $this->callObjectMethod($dbSelect, 'getShortColumnAlias', '*', 'Parent2');
        $this->callObjectMethod($dbSelect, 'getShortColumnAlias', 'id', 'Parent');
        foreach ($columns as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', $column->getName(), 'Parent2');
                $colsInSelectForParent2[] = '"tbl_Parent2_2"."' . $column->getName() . '" AS "' . $shortName . '"';
            }
        }
        $colsInSelectForParent2 = implode(', ', $colsInSelectForParent2);

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Parent_1"."id" AS "col_Parent__id_1", ' . $colsInSelectForParent2 . ' FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") LEFT JOIN "admins" AS "tbl_Parent2_2" ON ("tbl_Parent_1"."parent_id" = "tbl_Parent2_2"."id")',
            static::getNewSelect()->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => '*']])->getQuery()
        );

        $dbSelect = static::getNewSelect()->columns([
            'VeryLongColumnAliasSoItMustBeShortenedButWeNeedAtLeast60Characters' => 'id',
            'VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters' => [
                'id',
                'VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters as VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters2' => ['VeryLongColumnAliasSoItMustBeShortenedButWeNeedAtLeast60Characters2' => 'id'],
            ],
        ]);
        $shortJoinName = 'tbl_VrLngRltnNmSItMstBShrtnedButWeNeedAtLeast60Characters_1';
        $shortJoinName2 = 'tbl_VrLngRltnNmSItMstBShrtndButWeNeedAtLeast60Characters2_2';
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admns__VrLngClmnAlsSItMstBShrtndBtWNdAtLst60Chracters_0",'
            . ' "' . $shortJoinName . '"."id" AS "col_VrLngRltnNmSItMstBShrtndBtWNedAtLeast60Characters__id_1",'
            . ' "' . $shortJoinName2 . '"."id" AS "col_VrLngRltnNmSItMstBShrtndBtWNdAtLst60Chrctrs2__VrLngClm_2"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "' . $shortJoinName . '"'
            . ' ON ("tbl_Admins_0"."login" = "' . $shortJoinName . '"."id")'
            . ' LEFT JOIN "admins" AS "' . $shortJoinName2 . '"'
            . ' ON ("' . $shortJoinName . '"."login" = "' . $shortJoinName2 . '"."id")',
            $dbSelect->getQuery()
        );
    }

    public function testInvalidWhereCondition1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Invalid WHERE condition value provided for column [email]. Value:");
        static::getNewSelect()
            ->where(['email' => [1, 2]])
            ->getQuery();
    }

    public function testInvalidHavingCondition1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Invalid HAVING condition value provided for column [email]. Value:");
        static::getNewSelect()
            ->having(['email' => [1, 2]])
            ->getQuery();
    }

    public function testInvalidWhereCondition2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("WHERE: Column with name [invalid_____] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->where(['invalid_____' => '0'])
            ->getQuery();
    }

    public function testInvalidHavingCondition2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("HAVING: Column with name [invalid_____] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->having(['invalid_____' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInWhere1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Join config with name [InvalidRel] not found");
        static::getNewSelect()
            ->where(['InvalidRel.col' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInHaving1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Join config with name [InvalidRel] not found");
        static::getNewSelect()
            ->having(['InvalidRel.col' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInWhere2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("WHERE: Column with name [Parent.col] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->where(['Parent.col' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInHaving2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("HAVING: Column with name [Parent.col] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->having(['Parent.col' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInWhere3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("WHERE: Column with name [Parent2.col] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Parent as Parent2' => '*']])
            ->where(['Parent2.col' => '0'])
            ->getQuery();
    }

    public function testInvalidRelationInHaving3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("HAVING: Column with name [Parent2.col] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->columns(['id', 'Parent' => ['Parent as Parent2' => '*']])
            ->having(['Parent2.col' => '0'])
            ->getQuery();
    }

    /** @noinspection SqlAggregates */
    public function testWhereAndHaving(): void
    {
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            static::getNewSelect()
                ->columns('id')
                ->where([])
                ->having([])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" WHERE ("tbl_Admins_0"."id")::int = \'1\' HAVING ("tbl_Admins_0"."login")::varchar = \'2\'',
            static::getNewSelect()
                ->columns('id')
                ->where(['id::int' => '1'])
                ->having(['login::varchar' => '2'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" WHERE ("tbl_Admins_0"."id")::int = \'1\' AND "tbl_Admins_0"."login" = \'3\' HAVING ("tbl_Admins_0"."login")::varchar = \'2\' AND "tbl_Admins_0"."email" = \'test@test.ru\'',
            static::getNewSelect()
                ->columns('id')
                ->where(['id::int' => '1', 'login' => '3'])
                ->having(['login::varchar' => '2', 'email' => 'test@test.ru'])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            static::getNewSelect()
                ->columns('id')
                ->where([DbExpr::create('SUM(`id`) > ``1``')])
                ->having([DbExpr::create('SUM(`id`) > ``2``')])
                ->getQuery()
        );
        // test relations usage
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") WHERE "tbl_Parent_1"."parent_id" IS NOT NULL',
            static::getNewSelect()
                ->columns('id')
                ->where(['Parent.parent_id !=' => null])
                ->having([])
                ->getQuery()
        );
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") HAVING "tbl_Parent_1"."parent_id" IS NOT NULL',
            static::getNewSelect()
                ->columns('id')
                ->where([])
                ->having(['Parent.parent_id !=' => null])
                ->getQuery()
        );
        $dbSelect = static::getNewSelect()
            ->columns(['id', 'Parent' => ['Parent as Parent2' => ['id']]])
            ->where(['Parent2.parent_id !=' => null])
            ->having(['Parent2.parent_id !=' => null]);
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0",'
            . ' "tbl_Parent2_1"."id" AS "col_Parent2__id_1"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "tbl_Parent_2" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_2"."id")'
            . ' LEFT JOIN "admins" AS "tbl_Parent2_1" ON ("tbl_Parent_2"."parent_id" = "tbl_Parent2_1"."id")'
            . ' WHERE "tbl_Parent2_1"."parent_id" IS NOT NULL HAVING "tbl_Parent2_1"."parent_id" IS NOT NULL',
            $dbSelect->getQuery()
        );
        // test long aliases
        $dbSelect = static::getNewSelect()
            ->where(['VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters.parent_id !=' => null])
            ->columns(['id'])
            ->having([]);
        $shortAlias = 'tbl_VrLngRltnNmSItMstBShrtnedButWeNeedAtLeast60Characters_1';
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "' . $shortAlias
            . '" ON ("tbl_Admins_0"."login" = "' . $shortAlias . '"."id") WHERE "'
            . $shortAlias . '"."parent_id" IS NOT NULL',
            $dbSelect->getQuery()
        );
        $dbSelect = static::getNewSelect()
            ->columns(['id'])
            ->where([])
            ->having(['VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters.parent_id !=' => null]);
        $shortAlias = 'tbl_VrLngRltnNmSItMstBShrtnedButWeNeedAtLeast60Characters_1';
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "' . $shortAlias
            . '" ON ("tbl_Admins_0"."login" = "' . $shortAlias . '"."id") HAVING "'
            . $shortAlias . '"."parent_id" IS NOT NULL',
            $dbSelect->getQuery()
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }

    public function testJoins(): void
    {
        $dbSelect = static::getNewSelect()->columns(['id']);

        $joinConfig = OrmJoinInfo::create(
            'Test',
            TestingAdminsTable::getInstance(),
            'parent_id',
            OrmJoinInfo::JOIN_INNER,
            TestingAdminsTable::getInstance(),
            'id'
        );
        $joinConfig->setForeignColumnsToSelect('login', 'email');

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0",'
            . ' "tbl_Test_1"."login" AS "col_Test__login_1",'
            . ' "tbl_Test_1"."email" AS "col_Test__email_2"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' INNER JOIN "admins" AS "tbl_Test_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Test_1"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );

        $dbSelect = static::getNewSelect()->columns(['id']);
        $colsInSelectForTest = [];
        $adminsIdShortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', 'id', 'Admins');
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', $column->getName(), 'Test');
                $colsInSelectForTest[] = '"tbl_Test_1"."' . $column->getName() . '" AS "' . $shortName . '"';
            }
        }
        $colsInSelectForTest = implode(', ', $colsInSelectForTest);

        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'email' => 'test@test.ru',
            ]);
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "' . $adminsIdShortName . '", '
            . $colsInSelectForTest
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' LEFT JOIN "admins" AS "tbl_Test_1"'
            . ' ON ("tbl_Admins_0"."parent_id" = "tbl_Test_1"."id" AND "tbl_Test_1"."email" = \'test@test.ru\')',
            $dbSelect->join($joinConfig)->getQuery()
        );

        $dbSelect = static::getNewSelect()->columns(['id']);
        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['email']);
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Test_1"."email" AS "col_Test__email_1"'
            . ' FROM "admins" AS "tbl_Admins_0"'
            . ' RIGHT JOIN "admins" AS "tbl_Test_1"'
            . ' ON ("tbl_Admins_0"."parent_id" = "tbl_Test_1"."id" AND "tbl_Test_1"."email" = \'test@test.ru\')',
            $dbSelect->join($joinConfig)->getQuery()
        );

        $dbSelect = static::getNewSelect()->columns(['id']);
        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" RIGHT JOIN "admins" AS "tbl_Test_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Test_1"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );

        $dbSelect = static::getNewSelect()->columns(['id']);
        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_FULL)
            ->setForeignColumnsToSelect(['email']);
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0", "tbl_Test_1"."email" AS "col_Test__email_1" FROM "admins" AS "tbl_Admins_0" FULL JOIN "admins" AS "tbl_Test_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Test_1"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );
    }

    public function testInvalidValidateColumnInfo1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Test: Column with name [qqq] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        $this->callObjectMethod(
            static::getNewSelect(),
            'validateColumnInfo',
            [
                'name' => 'qqq',
                'join_name' => null,
                'alias' => null,
                'type_cast' => null,
            ],
            'Test'
        );
    }

    public function testInvalidValidateColumnInfo2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Test: Column with name [Parent.qqq] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        $this->callObjectMethod(
            static::getNewSelect(),
            'validateColumnInfo',
            [
                'name' => 'qqq',
                'join_name' => 'Parent',
                'alias' => null,
                'type_cast' => null,
            ],
            'Test'
        );
    }

    public function testInvalidValidateColumnInfo3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Test: Column with name [Parent.qqq] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        $this->callObjectMethod(
            self::getNewSelect(),
            'validateColumnInfo',
            [
                'name' => DbExpr::create('`Parent`.`id` + `Parent`.`qqq`'),
                'join_name' => null,
                'alias' => null,
                'type_cast' => null,
            ],
            'Test'
        );
    }

    public function testInvalidValidateColumnInfo4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect."
        );
        $this->callObjectMethod(
            self::getNewSelect(),
            'validateColumnInfo',
            [
                'name' => 'id',
                'join_name' => 'Children',
                'alias' => null,
                'type_cast' => null,
            ],
            'Test'
        );
    }

    public function testValidateColumnInfo(): void
    {
        // no exceptions should be thrown
        $select = self::getNewSelect();
        $info = [
            'name' => DbExpr::create('`there is no possibility to validate this =(` + Sum(`Parent`.`id` + `Parent`.`parent_id`)'),
            'join_name' => null,
            'alias' => null,
            'type_cast' => null,
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', $info, 'Test');

        $info = [
            'name' => 'id',
            'join_name' => null,
            'alias' => null,
            'type_cast' => null,
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', $info, 'Test');

        $info = [
            'name' => 'parent_id',
            'join_name' => 'Parent',
            'alias' => null,
            'type_cast' => null,
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', $info, 'Test');

        static::assertTrue(true);
    }

    public function testInvalidOrderBy1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Join config with name [Test] not found");
        static::getNewSelect()
            ->orderBy('Test.id')
            ->getQuery();
    }

    public function testInvalidOrderBy2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "ORDER BY: Column with name [Parent.qweasd] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure"
        );
        static::getNewSelect()
            ->orderBy('Parent.qweasd')
            ->getQuery();
    }

    public function testInvalidOrderBy3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("ORDER BY: Column with name [Parent.qweasd ASC] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->orderBy('Parent.qweasd ASC')
            ->getQuery();
    }

    public function testInvalidOrderBy4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: Parent.Parent");
        static::getNewSelect()
            ->orderBy('Parent.Parent.id')
            ->getQuery();
    }

    public function testInvalidGroupBy1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Join config with name [Test] not found");
        static::getNewSelect()
            ->groupBy(['Test.id'])
            ->getQuery();
    }

    public function testInvalidGroupBy2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "GROUP BY: Column with name [Parent.qweasd] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure"
        );
        static::getNewSelect()
            ->groupBy(['Parent.qweasd'])
            ->getQuery();
    }

    public function testInvalidGroupBy3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("GROUP BY: Column with name [Parent.qweasd ASC] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        static::getNewSelect()
            ->groupBy(['Parent.qweasd ASC'])
            ->getQuery();
    }

    public function testInvalidGroupBy4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Select does not have joins with next names: Parent.Parent");
        static::getNewSelect()
            ->groupBy(['Parent.Parent.id'])
            ->getQuery();
    }

    public function testOrderByAndGroupBy(): void
    {
        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") ORDER BY "tbl_Parent_1"."id" asc',
            static::getNewSelect()
                ->columns('id')
                ->orderBy('Parent.id')
                ->getQuery()
        );

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") GROUP BY "tbl_Parent_1"."id"',
            static::getNewSelect()
                ->orderBy('Parent.id')
                ->removeOrdering()
                ->columns('id')
                ->groupBy(['Parent.id'])
                ->getQuery()
        );

        static::assertEquals(
            'SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0" LEFT JOIN "admins" AS "tbl_Parent_1" ON ("tbl_Admins_0"."parent_id" = "tbl_Parent_1"."id") GROUP BY "tbl_Parent_1"."id", "tbl_Parent_1"."parent_id" ORDER BY "tbl_Parent_1"."id" asc',
            static::getNewSelect()
                ->columns('id')
                ->orderBy('Parent.id')
                ->groupBy(['Parent.id', 'Parent.parent_id'])
                ->getQuery()
        );
    }

    public function testWith(): void
    {
        $dbSelect = static::getNewSelect()
            ->columns('id')
            ->with(Select::from('admins', static::getValidAdapter()), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0")'
            . ' SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"',
            $dbSelect->getQuery()
        );
        $dbSelect->where([
            'id IN' => Select::from('subselect', static::getValidAdapter()),
        ]);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "tbl_Admins_1".* FROM "admins" AS "tbl_Admins_1")'
            . ' SELECT "tbl_Admins_1"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_1"'
            . ' WHERE "tbl_Admins_1"."id" IN (SELECT "tbl_Subselect_0".* FROM "subselect" AS "tbl_Subselect_0")',
            $dbSelect->getQuery()
        );
        $fakeTable = FakeTable::makeNewFakeTable('subselect');
        $dbSelect = OrmSelect::from($fakeTable)
            ->columns(['id'])
            ->with(Select::from('admins', static::getValidAdapter()), 'subselect')
            ->where(['created_at > ' => '2016-01-01']);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "tbl_Admins_0".* FROM "admins" AS "tbl_Admins_0") '
            . 'SELECT "tbl_Subselect_0"."id" AS "col_Subselect__id_0"'
            . ' FROM "subselect" AS "tbl_Subselect_0"'
            . ' WHERE "tbl_Subselect_0"."created_at" > \'2016-01-01\'',
            $dbSelect->getQuery()
        );
        $fakeTable = FakeTable::makeNewFakeTable('subselect2');
        $fakeTable->getTableStructure()->mimicTableStructure(TestingSettingsTableStructure::getInstance());
        $dbSelect = OrmSelect::from($fakeTable)
            ->columns(['id', 'key', 'value'])
            ->with(Select::from('settings', static::getValidAdapter()), 'subselect2')
            ->where(['key' => 'test']);
        static::assertEquals(
            'WITH "subselect2" AS (SELECT "tbl_Settings_0".* FROM "settings" AS "tbl_Settings_0")'
            . ' SELECT "tbl_Subselect2_0"."id" AS "col_Subselect2__id_0",'
            . ' "tbl_Subselect2_0"."key" AS "col_Subselect2__key_1",'
            . ' "tbl_Subselect2_0"."value" AS "col_Subselect2__value_2"'
            . ' FROM "subselect2" AS "tbl_Subselect2_0" WHERE "tbl_Subselect2_0"."key" = \'test\'',
            $dbSelect->getQuery()
        );

        $fakeTable2 = FakeTable::makeNewFakeTable('subselect3');
        $fakeTable2->getTableStructure()->mimicTableStructure(TestingSettingsTableStructure::getInstance());
        $subselect2 = Select::from('settings', static::getValidAdapter())->columns(['*']);
        static::assertEquals(
            'SELECT "tbl_Settings_0".* FROM "settings" AS "tbl_Settings_0"',
            $subselect2->buildQueryToBeUsedInWith()
        );
        $subselect3 = OrmSelect::from($fakeTable)->columns('*');
        static::assertEquals(
            'SELECT "tbl_Subselect2_0"."id", "tbl_Subselect2_0"."key", "tbl_Subselect2_0"."value"'
            . ' FROM "subselect2" AS "tbl_Subselect2_0"',
            $subselect3->buildQueryToBeUsedInWith()
        );
        $dbSelect2 = OrmSelect::from($fakeTable2)
            ->columns('id', 'key', 'value')
            ->with($subselect2, 'subselect2')
            ->with($subselect3, 'subselect3')
            ->where(['key' => 'test2']);
        static::assertEquals(
            'WITH "subselect2" AS (SELECT "tbl_Settings_1".* FROM "settings" AS "tbl_Settings_1"),'
            . ' "subselect3" AS (SELECT "tbl_Subselect2_1"."id", "tbl_Subselect2_1"."key", "tbl_Subselect2_1"."value"'
            . ' FROM "subselect2" AS "tbl_Subselect2_1")'
            . ' SELECT "tbl_Subselect3_0"."id" AS "col_Subselect3__id_0",'
            . ' "tbl_Subselect3_0"."key" AS "col_Subselect3__key_1",'
            . ' "tbl_Subselect3_0"."value" AS "col_Subselect3__value_2"'
            . ' FROM "subselect3" AS "tbl_Subselect3_0" WHERE "tbl_Subselect3_0"."key" = \'test2\'',
            $dbSelect2->getQuery()
        );

        $dbSelect = static::getNewSelect()
            ->columns('id')
            ->with(Select::from('settings', static::getValidAdapter()), 'subselect2')
            ->with(
                OrmSelect::from($fakeTable)
                    ->where(['key' => 'test']),
                'subselect3'
            )
            ->where(
                [
                    'id IN' => Select::from('subselect3', static::getValidAdapter())
                        ->where(['key' => 'test2']),
                ]
            );
        static::assertEquals(
            'WITH "subselect2" AS (SELECT "tbl_Settings_0".* FROM "settings" AS "tbl_Settings_0"),'
            . ' "subselect3" AS (SELECT "tbl_Subselect2_0"."id", "tbl_Subselect2_0"."key", "tbl_Subselect2_0"."value"'
            . ' FROM "subselect2" AS "tbl_Subselect2_0" WHERE "tbl_Subselect2_0"."key" = \'test\')'
            . ' SELECT "tbl_Admins_0"."id" AS "col_Admins__id_0" FROM "admins" AS "tbl_Admins_0"'
            . ' WHERE "tbl_Admins_0"."id" IN'
            . ' (SELECT "tbl_Subselect3_0".* FROM "subselect3" AS "tbl_Subselect3_0"'
            . ' WHERE "tbl_Subselect3_0"."key" = \'test2\')',
            $dbSelect->getQuery()
        );
    }

    public function testNoColumnsForWildcardNormalization(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('%PeskyORM.ORM.OrmSelect::normalizeWildcardColumn\(\): PeskyORM.ORM.Fakes.FakeTableStructure\d+ForFake\d+ has no columns that exist in DB%');
        $fakeTable = FakeTable::makeNewFakeTable('fake1');
        $select = OrmSelect::from($fakeTable)->columns('*');
        $select->buildQueryToBeUsedInWith();
    }

    public function testAnalyzeColumnNameForLongTableAlias(): void
    {
        $select = OrmSelect::from(TestingAdminsTableLongAlias::getInstance())
            ->columns('id');

        static::assertEquals(
            'SELECT "tbl_TstngAdmnsTblLngAlsRllLngButWeNeedAtLeast60Characters_0"."id" AS "col_TstngAdmnsTblLngAlsRllLngBtWNdAtLeast60Characters__id_0" '
            . 'FROM "admins" AS "tbl_TstngAdmnsTblLngAlsRllLngButWeNeedAtLeast60Characters_0"',
            $select->getQuery()
        );
        static::assertNotEquals(
            preg_replace('%^SELECT "(.+?)"."id",*$%', '$1', $select->getQuery()),
            TestingAdminsTableLongAlias::getAlias()
        );
    }


}
