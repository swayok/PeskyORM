<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\OrmJoinConfig;
use PeskyORM\ORM\OrmSelect;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;
use Swayok\Utils\Set;

class OrmSelectTest extends \PHPUnit_Framework_TestCase {

    static public function setUpBeforeClass() {
        TestingApp::init();
    }

    static public function tearDownAfterClass() {
        TestingApp::clearTables();
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
        static::assertCount(1, $this->getObjectPropertyValue($dbSelect, 'columns'));

        $expectedColsInfo = [
            [
                'name' => 'id',
                'alias' => null,
                'join_name' => null,
                'type_cast' => null,
            ]
        ];
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertEquals('SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getCountQuery()));

        TestingApp::clearTables();
        $insertedData = TestingApp::fillAdminsTable(2);
        $testData = static::convertTestDataForAdminsTableAssert($insertedData);
        $dbSelect->columns('*');
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
     * @expectedExceptionMessage There are no joins defined for next aliases: OtherTable
     */
    public function testInvalidJoinsSet() {
        static::getNewSelect()->columns(['OtherTable.id'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: column with name [asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName1() {
        static::getNewSelect()->columns(['asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: column with name [Parent.asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName2() {
        static::getNewSelect()->columns(['Parent.asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: column with name [asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName3() {
        // Parent is column alias, not a relation
        static::getNewSelect()->columns(['Parent' => 'asid'])->getQuery();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage SELECT: column with name [Parent.asid] not found in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testNotExistingColumnName4() {
        static::getNewSelect()->columns(['id', 'Parent' => ['asid']])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument must contain at least 1 column to be selected from main table
     */
    public function testEmptyColumnsList1() {
        static::getNewSelect()->columns(['Parent.id'])->getQuery();
    }

    public function testColumnsBasic() {
        $dbSelect = static::getNewSelect();
        $expectedColsInfo = [];
        $colsInSelect = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $expectedColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                ];
                $colsInSelect[] = '"Admins"."' . $column->getName() . '" AS "_Admins__' . $column->getName() . '"';
            }
        }
        $colsInSelect = implode(', ', $colsInSelect);

        $dbSelect->columns([]);
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(15, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
        );

        $dbSelect->columns(['*']);
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(15, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
        );

        $dbSelect->columns('*');
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertCount(15, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertEquals(
            'SELECT ' . $colsInSelect . ' FROM "public"."admins" AS "Admins"',
            rtrim($dbSelect->getQuery())
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
            'SELECT ' . $colsInSelect . ', (SUM("id")) AS "_Admins__sum" FROM "public"."admins" AS "Admins"',
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
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains unknown column 'qqq' on key 'Children' for join named 'Parent'
     */
    public function testHasManyRelationException6() {
        static::getNewSelect()->columns(['id', 'Parent' => ['Children' => 'qqq']])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains unknown column 'qqq' on key '0' for join named 'Parent'
     */
    public function testInvalidColNameInRelation1() {
        static::getNewSelect()->columns(['id', 'Parent' => ['qqq']])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains unknown column 'qqq' on key 'key' for join named 'Parent'
     */
    public function testInvalidColNameInRelation2() {
        static::getNewSelect()->columns(['id', 'Parent' => ['key' => 'qqq']])->getQuery();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columns argument contains unknown column 'qqq' on key '0' for join named 'Parent'
     */
    public function testInvalidColNameInRelation3() {
        static::getNewSelect()->columns(['id', 'Parent.qqq'])->getQuery();
    }

    public function testColumnsWithRelations() {
        $dbSelect = static::getNewSelect();
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent.id'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id']])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", "Parent"."login" AS "_Parent__login" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'login']])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", "Parent2"."id" AS "_Parent2__id" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "public"."admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => ['id']]])->getQuery()
        );
        $colsInSelectForParent = [];
        $colsInSelectForParent2 = [];
        foreach ($dbSelect->getTable()->getTableStructure()->getColumns() as $column) {
            if ($column->isItExistsInDb()) {
                $expectedColsInfo[] = [
                    'name' => $column->getName(),
                    'alias' => null,
                    'join_name' => null,
                    'type_cast' => null,
                ];
                $colsInSelectForParent[] = '"Parent"."' . $column->getName() . '" AS "_Parent__' . $column->getName() . '"';
                $colsInSelectForParent2[] = '"Parent2"."' . $column->getName() . '" AS "_Parent2__' . $column->getName() . '"';
            }
        }
        $colsInSelectForParent = implode(', ', $colsInSelectForParent);
        $colsInSelectForParent2 = implode(', ', $colsInSelectForParent2);

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", ' . $colsInSelectForParent . ', "Parent2"."id" AS "_Parent2__id" FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "public"."admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['*', 'Parent as Parent2' => ['id']]])->getQuery()
        );

        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id", "Parent"."id" AS "_Parent__id", ' . $colsInSelectForParent2 . ' FROM "public"."admins" AS "Admins" LEFT JOIN "public"."admins" AS "Parent" ON ("Admins"."parent_id" = "Parent"."id") LEFT JOIN "public"."admins" AS "Parent2" ON ("Parent"."parent_id" = "Parent2"."id")',
            $dbSelect->columns(['id', 'Parent' => ['id', 'Parent as Parent2' => '*']])->getQuery()
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
     * @expectedExceptionMessage Invalid column name [invalid_____] for WHERE
     */
    public function testInvalidWhereCondition2() {
        static::getNewSelect()->where(['invalid_____' => '0'])->getQuery();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Invalid column name [invalid_____] for HAVING
     */
    public function testInvalidHavingCondition2() {
        static::getNewSelect()->having(['invalid_____' => '0'])->getQuery();
    }

    // todo: test invalid relations usage

    public function testWhereAndHaving() {
        $dbSelect = static::getNewSelect()->columns('id');
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins"',
            $dbSelect->where([])->having([])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' HAVING "Admins"."login"::varchar = \'2\'',
            $dbSelect->where(['id::int' => '1'])->having(['login::varchar' => '2'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins" WHERE "Admins"."id"::int = \'1\' AND "Admins"."login" = \'3\' HAVING "Admins"."login"::varchar = \'2\' AND "Admins"."email" = \'test@test.ru\'',
            $dbSelect->where(['id::int' => '1', 'login' => '3'])->having(['login::varchar' => '2', 'email' => 'test@test.ru'])->getQuery()
        );
        static::assertEquals(
            'SELECT "Admins"."id" AS "_Admins__id" FROM "public"."admins" AS "Admins" WHERE (SUM("id") > \'1\') HAVING (SUM("id") > \'2\')',
            $dbSelect->where([DbExpr::create('SUM(`id`) > ``1``')])->having([DbExpr::create('SUM(`id`) > ``2``')])->getQuery()
        );
        // todo: test relations usage
        // conditions assembling tests are in Utils::assembleWhereConditionsFromArray()
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join config is not valid
     */
    public function testInvalidJoin1() {
        $joinConfig = OrmJoinConfig::create('Test');
        static::getNewSelect()->join($joinConfig);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Join with name 'Test' already defined
     */
    public function testInvalidJoin2() {
        $joinConfig = OrmJoinConfig::create('Test')
            ->setConfigForLocalTable('admins', 'id')
            ->setConfigForForeignTable('settings', 'id')
            ->setJoinType(OrmJoinConfig::JOIN_INNER);
        static::getNewSelect()->join($joinConfig)->join($joinConfig);
    }

    public function testJoins() {
        $dbSelect = static::getNewSelect();
        $joinConfig = OrmJoinConfig::create('Test')
            ->setConfigForLocalTable('admins', 'id')
            ->setConfigForForeignTable('settings', 'id')
            ->setJoinType(OrmJoinConfig::JOIN_INNER)
            ->setForeignColumnsToSelect('key', 'value');
        static::assertEquals(
            'SELECT "Admins".*, "Test"."key" AS "_Test__key", "Test"."value" AS "_Test__value" FROM "public"."admins" AS "Admins" INNER JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id")',
            $dbSelect->join($joinConfig)->getQuery()
        );
        $joinConfig
            ->setJoinType(OrmJoinConfig::JOIN_LEFT)
            ->setForeignColumnsToSelect('*')
            ->setAdditionalJoinConditions([
                'key' => 'name'
            ]);
        static::assertEquals(
            'SELECT "Admins".*, "Test".* FROM "public"."admins" AS "Admins" LEFT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(OrmJoinConfig::JOIN_RIGHT)
            ->setForeignColumnsToSelect(['value']);
        static::assertEquals(
            'SELECT "Admins".*, "Test"."value" AS "_Test__value" FROM "public"."admins" AS "Admins" RIGHT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id" AND "Test"."key" = \'name\')',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
        $joinConfig
            ->setJoinType(OrmJoinConfig::JOIN_RIGHT)
            ->setAdditionalJoinConditions([])
            ->setForeignColumnsToSelect([]);
        static::assertEquals(
            'SELECT "Admins".* FROM "public"."admins" AS "Admins" RIGHT JOIN "public"."settings" AS "Test" ON ("Admins"."admins" = "Test"."id")',
            $dbSelect->join($joinConfig, false)->getQuery()
        );
    }

    public function testInvalidValidateColumnInfo1() {

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
