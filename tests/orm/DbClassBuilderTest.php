<?php

use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\Column;

class DbClassBuilderTest extends PHPUnit_Framework_TestCase {

    /**
     * @param string $tableName
     * @return ClassBuilder
     */
    protected function getBuilder($tableName = 'admins') {
        return new ClassBuilder($tableName, \PeskyORMTest\TestingApp::getPgsqlConnection());
    }

    /**
     * @param ClassBuilder $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @internal param string $propertyName
     */
    private function callObjectMethod($object, $methodName, ...$args) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    public function testBuilderServiceMethods() {
        static::assertEquals('Admins', ClassBuilder::convertTableNameToClassName('admins'));
        static::assertEquals('SomeTables', ClassBuilder::convertTableNameToClassName('some_tables'));
        static::assertEquals('AdminsTable', ClassBuilder::makeTableClassName('admins'));
        static::assertEquals('SomeTablesTable', ClassBuilder::makeTableClassName('some_tables'));
        static::assertEquals('AdminsTableStructure', ClassBuilder::makeTableStructureClassName('admins'));
        static::assertEquals('SomeTablesTableStructure', ClassBuilder::makeTableStructureClassName('some_tables'));
        static::assertEquals('Admin', ClassBuilder::makeRecordClassName('admins'));
        static::assertEquals('SomeTable', ClassBuilder::makeRecordClassName('some_tables'));
        $builder = $this->getBuilder();
        static::assertEquals(
            'TestingAdmin',
            $this->callObjectMethod($builder, 'getShortClassName', \PeskyORMTest\TestingAdmins\TestingAdmin::class)
        );
        static::assertEquals(
            'Column::TYPE_STRING',
            $this->callObjectMethod($builder, 'getConstantNameForColumnType', 'string')
        );
        static::assertEquals(
            'Column::TYPE_INT',
            $this->callObjectMethod($builder, 'getConstantNameForColumnType', 'integer')
        );
    }

    public function testTableAndRecordClassBuilding() {
        $builder = $this->getBuilder();
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/table_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildTableClass('App\\Db'))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/table_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildTableClass('App\\Db', \PeskyORMTest\TestingBaseTable::class))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/record_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildRecordClass('App\\Db'))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/record_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildRecordClass('App\\Db', \PeskyORMTest\TestingAdmins\TestingAdmin::class))
        );
    }

    public function testMakeColumnConfig() {
        $builder = $this->getBuilder();
        $columnDescr = new \PeskyORM\Core\ColumnDescription('test', 'integer', Column::TYPE_INT);
        $columnDescr->setIsPrimaryKey(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->itIsPrimaryKey()->convertsEmptyStringToNull()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsPrimaryKey(false)
            ->setIsUnique(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->valueMustBeUnique(true)->convertsEmptyStringToNull()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsUnique(false)
            ->setIsNullable(false);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->valueIsNotNullable()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsNullable(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault('string with \' quotes " `');
        static::assertEquals(
            "Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue('string with \\' quotes \\\" `')",
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue(true)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(false);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue(false)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(null);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(111);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue(111)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(111.11);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue(111.11)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(\PeskyORM\Core\DbExpr::create("string with ' quotes \" `"));
        static::assertEquals(
            "Column::create(Column::TYPE_INT)->convertsEmptyStringToNull()->setDefaultValue(DbExpr::create('string with \' quotes \\\" `'))",
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(\PeskyORM\Core\DbExpr::create("string with ' quotes \" `"))
            ->setIsPrimaryKey(true);
        static::assertEquals(
            "Column::create(Column::TYPE_INT)->itIsPrimaryKey()->convertsEmptyStringToNull()",
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
    }

    public function testDbStructureClassBuilder() {
        $builder = $this->getBuilder();
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildStructureClass('App\\Db'))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildStructureClass('App\\Db', \PeskyORMTest\TestingAdmins\TestingAdminsTableStructure::class))
        );
    }
}
