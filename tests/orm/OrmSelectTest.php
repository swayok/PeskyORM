<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Select;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\OrmJoinInfo;
use PeskyORM\ORM\OrmSelect;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;
use Swayok\Utils\Set;

class OrmSelectTest extends \PHPUnit_Framework_TestCase {

    static public function setUpBeforeClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static public function tearDownAfterClass() {
        TestingApp::clearTables(static::getValidAdapter());
    }

    static protected function getValidAdapter() {
        return TestingApp::getPgsqlConnection();
    }

    static protected function getNewSelect() {
        return OrmSelect::from(TestingAdminsTable::getInstance());
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
     * @param OrmSelect $object
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
     * @param OrmSelect $object
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

    public function testConstructorAndBasicFetching() {
        // via new
        $dbSelect = $this->getNewSelect()->columns('id');
        static::assertInstanceOf(OrmSelect::class, $dbSelect);
        static::assertInstanceOf(TestingAdminsTable::class, $dbSelect->getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, $dbSelect->getTableStructure());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertCount(1, $this->getObjectPropertyValue($dbSelect, 'columnsRaw'));
        static::assertCount(0, $this->getObjectPropertyValue($dbSelect, 'columns'));


        static::assertEquals(['id'], $this->getObjectPropertyValue($dbSelect, 'columnsRaw'));
        static::assertEquals('SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "admins" AS "Admins"', rtrim($dbSelect->getCountQuery()));
        $expectedColsInfo = [
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ]
        ];
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));

        $insertedData = TestingApp::fillAdminsTable(2);
        $testData = static::convertTestDataForAdminsTableAssert($insertedData);
        $testData[0]['created_at'] .= '+00';
        $testData[0]['updated_at'] .= '+00';
        $testData[1]['created_at'] .= '+00';
        $testData[1]['updated_at'] .= '+00';
        $dbSelect->columns('*');
        $count = $dbSelect->fetchCount();
        static::assertEquals(2, $count);
        $data = $dbSelect->fetchMany();
        static::assertEquals($testData, $data);
        $data = $dbSelect->fetchOne();
        static::assertEquals($testData[0], $data);
        $data = $dbSelect->columns(['id'])->fetchColumn();
        static::assertEquals(Set::extract('/id', $testData), $data);
        $data = $dbSelect->fetchAssoc('id', 'login');
        static::assertEquals(Set::combine($testData, '/id', '/login'), $data);
        $sum = $dbSelect->fetchValue(\PeskyORM\Core\DbExpr::create('SUM(`id`)'));
        static::assertEquals(array_sum(Set::extract('/id', $testData)), $sum);

        // via static
        $dbSelect = OrmSelect::from(TestingAdminsTable::getInstance());
        static::assertInstanceOf(OrmSelect::class, $dbSelect);
        static::assertInstanceOf(Postgres::class, $dbSelect->getConnection());
        static::assertInstanceOf(TestingAdminsTable::class, $dbSelect->getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, $dbSelect->getTableStructure());
        static::assertEquals('admins', $dbSelect->getTableName());
        $data = $dbSelect->limit(1)->fetchNextPage();
        static::assertEquals([$testData[1]], $data);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: There is no relation 'OtherTable' in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidJoinsSet() {
        static::getNewSelect()->columns(['id', 'OtherTable.id'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName1() {
        static::getNewSelect()->columns(['asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName2() {
        static::getNewSelect()->columns(['id', 'Parent.asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName3() {
        // Parent is column alias, not a relation
        static::getNewSelect()->columns(['Parent' => 'asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.asid] and alias [asialias] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName4() {
        static::getNewSelect()->columns(['id', 'Parent' => ['asialias' => 'asid']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Failed to assemble DB query: there is no columns to be selected from main table
     */
    public function testEmptyColumnsList1() {
        static::getNewSelect()->columns(['Parent.id'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [asdasdqdasd] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidColNameInDbExpr() {
        static::getNewSelect()->columns(['sum' => DbExpr::create('SUM(`Admins`.`asdasdqdasd`)')])->getQuery();
    }

    public function testColumnsBasic() {
        $dbSelect = static::getNewSelect();
        $expectedColsInfo = [];
        $colsInSelect = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', [$column->getName()]);
                $expectedColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                ];
                $colsInSelect[] = '"Admins"."' . $column->getName() . '" AS "_Admins__' . $shortName . '"';
            }
        }
        $colsInSelect = implode(', ', $colsInSelect);

        $dbSelect->columns([]);
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(16, $this->getObjectPropertyValue($dbSelect, 'columns'));

        $dbSelect->columns(['*']);
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(16, $this->getObjectPropertyValue($dbSelect, 'columns'));

        $dbSelect->columns('*');
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
        );
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(16, $this->getObjectPropertyValue($dbSelect, 'columns'));

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['id'])->getQuery())
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
            'SELECT ' . $colsInSelect . ', (SUM("id")) AS "_Admins__sum" FROM "admins" AS "Admins"',
            rtrim($dbSelect->columns(['*', 'sum' => DbExpr::create('SUM(`id`)')])->getQuery())
        );
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect
     */
    public function testHasManyRelationException1() {
        static::getNewSelect()->columns('id', 'Children.*')->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect
     */
    public function testHasManyRelationException2() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children.*']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect
     */
    public function testHasManyRelationException3() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children' => '*']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect
     */
    public function testHasManyRelationException4() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children' => ['*']]])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect
     */
    public function testHasManyRelationException5() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children' => []]])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.qqq] and alias [Children] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testHasManyRelationException6() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children' => 'qqq']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.qqq] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidColNameInRelation1() {
        static::getNewSelect()->columns(['id', 'Parent' => ['qqq']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.qqq] and alias [key] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidColNameInRelation2() {
        static::getNewSelect()->columns(['id', 'Parent' => ['key' => 'qqq']])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: Column with name [Parent.qqq] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidColNameInRelation3() {
        static::getNewSelect()->columns(['id', 'Parent.qqq'])->getQuery();
    }

    public function testColumnsWithRelations() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id']])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", "Parent"."login" AS "_Parent__login" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'login']])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", "Parent2"."id" AS "_Parent2__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => ['id']]])->getQuery()
        );
        $colsInSelectForParent = [];
        $colsInSelectForParent2 = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', [$column->getName()]);
                $expectedColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                ];
                $colsInSelectForParent[] = '"Parent"."' . $column->getName() . '" AS "_Parent__' . $shortName . '"';
                $colsInSelectForParent2[] = '"Parent2"."' . $column->getName() . '" AS "_Parent2__' . $shortName . '"';
            }
        }
        $colsInSelectForParent = implode(', ', $colsInSelectForParent);
        $colsInSelectForParent2 = implode(', ', $colsInSelectForParent2);

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", ' . $colsInSelectForParent . ', "Parent2"."id" AS "_Parent2__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['*', 'Parent as Parent2' => ['id']]])->getQuery()
        );

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", ' . $colsInSelectForParent2 . ' FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => '*']])->getQuery()
        );

        $query = $dbSelect->columns([
            'VeryLongColumnAliasSoItMustBeShortened' => 'id',
            'VeryLongRelationNameSoItMustBeShortened' => ['id', 'VeryLongRelationNameSoItMustBeShortened as VeryLongRelationNameSoItMustBeShortened2' => ['VeryLongColumnAliasSoItMustBeShortened2' => 'id']]]
        )->getQuery();
        $shortJoinName = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['VeryLongRelationNameSoItMustBeShortened']);
        $shortJoinName2 = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['VeryLongRelationNameSoItMustBeShortened2']);
        $shortColumnName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', ['VeryLongColumnAliasSoItMustBeShortened']);
        $shortColumnName2 = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', ['VeryLongColumnAliasSoItMustBeShortened2']);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__' . $shortColumnName . '", "' . $shortJoinName .'"."id" AS "_' . $shortJoinName . '__id", "' . $shortJoinName2 .'"."id" AS "_' . $shortJoinName2 . '__' . $shortColumnName2 . '" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "' . $shortJoinName . '" ON ("Admins"."parent_id" = "' . $shortJoinName . '"."id") LEFT JOIN "admins" AS "' . $shortJoinName2 . '" ON ("' . $shortJoinName . '"."parent_id" = "' . $shortJoinName2 . '"."id")',
            $query
        );
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Invalid WHERE condition value provided for column [email]. Value: 0
     */
    public function testInvalidWhereCondition1() {
        static::getNewSelect()->where(['email' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Invalid HAVING condition value provided for column [email]. Value: 0
     */
    public function testInvalidHavingCondition1() {
        static::getNewSelect()->having(['email' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage WHERE: Column with name [invalid_____] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidWhereCondition2() {
        static::getNewSelect()->where(['invalid_____' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage HAVING: Column with name [invalid_____] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidHavingCondition2() {
        static::getNewSelect()->having(['invalid_____' => '0'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Join config with name [InvalidRel] not found
     */
    public function testInvalidRelationInWhere1() {
        static::getNewSelect()->where(['InvalidRel.col' => '0'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Join config with name [InvalidRel] not found
     */
    public function testInvalidRelationInHaving1() {
        static::getNewSelect()->having(['InvalidRel.col' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage WHERE: Column with name [Parent.col] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidRelationInWhere2() {
        static::getNewSelect()->where(['Parent.col' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage HAVING: Column with name [Parent.col] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidRelationInHaving2() {
        static::getNewSelect()->having(['Parent.col' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage WHERE: Column with name [Parent2.col] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidRelationInWhere3() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Parent as Parent2' => '*']])->where(['Parent2.col' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage HAVING: Column with name [Parent2.col] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidRelationInHaving3() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Parent as Parent2' => '*']])->having(['Parent2.col' => '0'])->getQuery();
    }

    public function testWhereAndHaving() {
        $dbSelect = static::getNewSelect()->columns('id');
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            $dbSelect->where([])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' HAVING "Admins"."login"::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])->having(['login::varchar' => '2'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' AND "Admins"."login" = \'3\' HAVING "Admins"."login"::varchar = \'2\' AND "Admins"."email" = \'test@test.ru\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])->having(['login::varchar' => '2', 'email' => 'test@test.ru'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])->having([DbExpr::create('SUM(`id`) > ``2``')])->getQuery()
        );
        // test relations usage
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") WHERE "Parent"."parent_id" IS NOT NULL',
            $dbSelect->where(['Parent.parent_id !=' => null])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") HAVING "Parent"."parent_id" IS NOT NULL',
            $dbSelect->where([])->having(['Parent.parent_id !=' => null])->getQuery()
        );
        $dbSelect
            ->columns(['id', 'Parent' => ['Parent as Parent2' => ['id']]])
            ->where(['Parent2.parent_id !=' => null])
            ->having(['Parent2.parent_id !=' => null])
            ->getQuery();
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent2"."id" AS "_Parent2__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id") WHERE "Parent2"."parent_id" IS NOT NULL HAVING "Parent2"."parent_id" IS NOT NULL',
            $dbSelect->getQuery()
        );
        // test long aliases
        $query = $dbSelect->where(['VeryLongRelationNameSoItMustBeShortened.parent_id !=' => null])->having([])->getQuery();
        $shortAlias = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['VeryLongRelationNameSoItMustBeShortened']);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "' . $shortAlias . '" ON ("Admins"."parent_id" = "' . $shortAlias . '"."id") WHERE "' . $shortAlias . '"."parent_id" IS NOT NULL',
            $query
        );
        $query = $dbSelect->where([])->having(['VeryLongRelationNameSoItMustBeShortened.parent_id !=' => null])->getQuery();
        $shortAlias = $this->callObjectMethod($dbSelect, 'getShortJoinAlias', ['VeryLongRelationNameSoItMustBeShortened']);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "' . $shortAlias . '" ON ("Admins"."parent_id" = "' . $shortAlias . '"."id") HAVING "' . $shortAlias . '"."parent_id" IS NOT NULL',
            $query
        );
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join config with name 'Test' is not valid
     */
    public function testInvalidJoin1() {
        $joinConfig = OrmJoinInfo::create('Test');
        static::getNewSelect()->join($joinConfig);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join with name 'Test' already defined
     */
    public function testInvalidJoin2() {
        $joinConfig = OrmJoinInfo::create('Test')
            ->setConfigForLocalTable(TestingAdminsTable::getInstance(), 'parent_id')
            ->setConfigForForeignTable(TestingAdminsTable::getInstance(), 'id')
            ->setJoinType(OrmJoinInfo::JOIN_INNER);
        static::getNewSelect()->join($joinConfig)->join($joinConfig);
    }

    public function testJoins() {
        $dbSelect = static::getNewSelect()->columns(['id']);
        $joinConfig = OrmJoinInfo::create('Test')
            ->setConfigForLocalTable(TestingAdminsTable::getInstance(), 'parent_id')
            ->setConfigForForeignTable(TestingAdminsTable::getInstance(), 'id')
            ->setJoinType(OrmJoinInfo::JOIN_INNER)
            ->setForeignColumnsToSelect('login', 'email');
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Test"."login" AS "_Test__login", "Test"."email" AS "_Test__email" FROM "admins" AS "Admins" INNER JOIN "admins" AS "Test" ON ("Admins"."parent_id" = "Test"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );

        $colsInSelectForTest = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $shortName = $this->callObjectMethod($dbSelect, 'getShortColumnAlias', [$column->getName()]);
                $colsInSelectForTest[] = '"Test"."' . $column->getName() . '" AS "_Test__' . $shortName . '"';
            }
        }
        $colsInSelectForTest = implode(', ', $colsInSelectForTest);

        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'email' => 'test@test.ru'
            ]);

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", ' . $colsInSelectForTest . ' FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Test" ON ("Admins"."parent_id" = "Test"."id" AND "Test"."email" = \'test@test.ru\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['email']);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Test"."email" AS "_Test__email" FROM "admins" AS "Admins" RIGHT JOIN "admins" AS "Test" ON ("Admins"."parent_id" = "Test"."id" AND "Test"."email" = \'test@test.ru\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(OrmJoinInfo::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" RIGHT JOIN "admins" AS "Test" ON ("Admins"."parent_id" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Test: Column with name [qqq] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidValidateColumnInfo1() {
        $this->callObjectMethod($this->getNewSelect(), 'validateColumnInfo', [
            [
                'name' => 'qqq',
                'join_name' => null,
                'alias' => null,
                'type_cast' => null
            ],
            'Test'
        ]);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Test: Column with name [Parent.qqq] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidValidateColumnInfo2() {
        $this->callObjectMethod($this->getNewSelect(), 'validateColumnInfo', [
            [
                'name' => 'qqq',
                'join_name' => 'Parent',
                'alias' => null,
                'type_cast' => null
            ],
            'Test'
        ]);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Test: Column with name [Parent.qqq] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidValidateColumnInfo3() {
        $this->callObjectMethod($this->getNewSelect(), 'validateColumnInfo', [
            [
                'name' => DbExpr::create('`Parent`.`id` + `Parent`.`qqq`'),
                'join_name' => null,
                'alias' => null,
                'type_cast' => null
            ],
            'Test'
        ]);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Relation 'Children' has type 'HAS MANY' and should not be used as JOIN (not optimal). Select that records outside of OrmSelect.
     */
    public function testInvalidValidateColumnInfo4() {
        $this->callObjectMethod($this->getNewSelect(), 'validateColumnInfo', [
            [
                'name' => 'id',
                'join_name' => 'Children',
                'alias' => null,
                'type_cast' => null
            ],
            'Test'
        ]);
    }

    public function testValidateColumnInfo() {
        $select = $this->getNewSelect();
        $info = [
            'name' => DbExpr::create('`there is no possibility to validate this =(` + Sum(`Parent`.`id` + `Parent`.`parent_id`)'),
            'join_name' => null,
            'alias' => null,
            'type_cast' => null
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', [$info, 'Test']);

        $info = [
            'name' => 'id',
            'join_name' => null,
            'alias' => null,
            'type_cast' => null
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', [$info, 'Test']);

        $info = [
            'name' => 'parent_id',
            'join_name' => 'Parent',
            'alias' => null,
            'type_cast' => null
        ];
        $this->callObjectMethod($select, 'validateColumnInfo', [$info, 'Test']);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Join config with name [Test] not found
     */
    public function testInvalidOrderBy1() {
        static::getNewSelect()->orderBy('Test.id')->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ORDER BY: Column with name [Parent.qweasd] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidOrderBy2() {
        static::getNewSelect()->orderBy('Parent.qweasd')->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER BY: Invalid column name or json selector: [Parent.qweasd ASC]
     */
    public function testInvalidOrderBy3() {
        static::getNewSelect()->orderBy('Parent.qweasd ASC')->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ORDER BY: Invalid column name or json selector: [Parent.Parent.id]
     */
    public function testInvalidOrderBy4() {
        static::getNewSelect()->orderBy('Parent.Parent.id')->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Join config with name [Test] not found
     */
    public function testInvalidGroupBy1() {
        static::getNewSelect()->groupBy(['Test.id'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage GROUP BY: Column with name [Parent.qweasd] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidGroupBy2() {
        static::getNewSelect()->groupBy(['Parent.qweasd'])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage GROUP BY: Invalid column name or json selector: [Parent.qweasd ASC]
     */
    public function testInvalidGroupBy3() {
        static::getNewSelect()->groupBy(['Parent.qweasd ASC'])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage GROUP BY: Invalid column name or json selector: [Parent.Parent.id]
     */
    public function testInvalidGroupBy4() {
        static::getNewSelect()->groupBy(['Parent.Parent.id'])->getQuery();
    }

    public function testOrderByAndGroupBy() {
        $select = static::getNewSelect()->columns('id');

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") ORDER BY "Parent"."id" ASC',
            $select->orderBy('Parent.id')->getQuery()
        );

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") GROUP BY "Parent"."id"',
            $select->removeOrdering()->groupBy(['Parent.id'])->getQuery()
        );

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" LEFT JOIN "admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") GROUP BY "Parent"."id", "Parent"."parent_id" ORDER BY "Parent"."id" ASC',
            $select->orderBy('Parent.id')->groupBy(['Parent.id', 'Parent.parent_id'])->getQuery()
        );
    }

    public function testWith() {
        $dbSelect = static::getNewSelect()->columns('id');
        $dbSelect->with(Select::from('admins', static::getValidAdapter()), 'subselect');
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins"',
            $dbSelect->getQuery()
        );
        $dbSelect->where([
            'id IN' => Select::from('subselect', static::getValidAdapter())
        ]);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Admins"."id" AS "_Admins__id" FROM "admins" AS "Admins" WHERE "Admins"."id" IN (SELECT "Subselect".* FROM "subselect" AS "Subselect")',
            $dbSelect->getQuery()
        );
        $fakeTable = FakeTable::makeNewFakeTable('subselect');
        $dbSelect = OrmSelect::from($fakeTable)
            ->with(Select::from('admins', static::getValidAdapter()), 'subselect')
            ->where(['created_at > ' => '2016-01-01']);
        static::assertEquals(
            'WITH "subselect" AS (SELECT "Admins".* FROM "admins" AS "Admins") SELECT "Subselect"."id" AS "_Subselect__id" FROM "subselect" AS "Subselect" WHERE "Subselect"."created_at" > \'2016-01-01\'',
            $dbSelect->getQuery()
        );
        $fakeTable = FakeTable::makeNewFakeTable('subselect2');
        $fakeTable->getTableStructure()->mimicTableStructure(\PeskyORMTest\TestingSettings\TestingSettingsTableStructure::getInstance());
        $dbSelect = OrmSelect::from($fakeTable)
            ->with(Select::from('settings', static::getValidAdapter()), 'subselect2')
            ->where(['key' => 'test']);
        static::assertEquals(
            'WITH "subselect2" AS (SELECT "Settings".* FROM "settings" AS "Settings") SELECT "Subselect2"."id" AS "_Subselect2__id", "Subselect2"."key" AS "_Subselect2__key", "Subselect2"."value" AS "_Subselect2__value" FROM "subselect2" AS "Subselect2" WHERE "Subselect2"."key" = \'test\'',
            $dbSelect->getQuery()
        );
    }


}
