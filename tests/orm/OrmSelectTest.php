<?php

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\DbJoinConfig;
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
        $dbSelect = $this->getNewSelect();
        static::assertInstanceOf(OrmSelect::class, $dbSelect);
        static::assertInstanceOf(TestingAdminsTable::class, $dbSelect->getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, $dbSelect->getTableStructure());
        static::assertEquals('admins', $dbSelect->getTableName());
        static::assertEquals('Admins', $dbSelect->getTableAlias());
        static::assertCount(15, $this->getObjectPropertyValue($dbSelect, 'columns'));
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
        static::assertEquals($expectedColsInfo, $this->getObjectPropertyValue($dbSelect, 'columns'));
        static::assertEquals('SELECT ' . implode(', ', $colsInSelect) . ' FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getQuery()));
        static::assertEquals('SELECT COUNT(*) FROM "public"."admins" AS "Admins"', rtrim($dbSelect->getCountQuery()));

        TestingApp::clearTables();
        $insertedData = TestingApp::fillAdminsTable(2);
        $testData = static::convertTestDataForAdminsTableAssert($insertedData);
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
