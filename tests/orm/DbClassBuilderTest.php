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
        $builder1 = $this->getBuilder();
        $builder2 = $this->getBuilder('some_tables');
        static::assertEquals('Admins', $this->callObjectMethod($builder1, 'convertTableNameToClassName'));
        static::assertEquals('SomeTables', $this->callObjectMethod($builder2, 'convertTableNameToClassName'));
        static::assertEquals('AdminsTable', $this->callObjectMethod($builder1, 'makeTableClassName'));
        static::assertEquals('SomeTablesTable', $this->callObjectMethod($builder2, 'makeTableClassName'));
        static::assertEquals('AdminsTableStructure', $this->callObjectMethod($builder1, 'makeTableStructureClassName'));
        static::assertEquals('SomeTablesTableStructure', $this->callObjectMethod($builder2, 'makeTableStructureClassName'));
        static::assertEquals('Admin', $this->callObjectMethod($builder1, 'makeRecordClassName'));
        static::assertEquals('SomeTable', $this->callObjectMethod($builder2, 'makeRecordClassName'));
        static::assertEquals(
            'TestingAdmin',
            $this->callObjectMethod($builder1, 'getShortClassName', \PeskyORMTest\TestingAdmins\TestingAdmin::class)
        );
        static::assertEquals(
            'Column::TYPE_STRING',
            $this->callObjectMethod($builder1, 'getConstantNameForColumnType', 'string')
        );
        static::assertEquals(
            'Column::TYPE_INT',
            $this->callObjectMethod($builder1, 'getConstantNameForColumnType', 'integer')
        );
    }

    public function testTableAndRecordClassBuilding() {
        $builder = $this->getBuilder();
        static::assertEquals(
            file_get_contents(__DIR__ . '/classes_to_test_builder/table_class1.txt'),
            $builder->buildTableClass('App\\Db')
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/classes_to_test_builder/table_class2.txt'),
            $builder->buildTableClass('App\\Db', \PeskyORMTest\TestingBaseTable::class)
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/classes_to_test_builder/record_class1.txt'),
            $builder->buildRecordClass('App\\Db')
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/classes_to_test_builder/record_class2.txt'),
            $builder->buildRecordClass('App\\Db', \PeskyORMTest\TestingAdmins\TestingAdmin::class)
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
            file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class1.txt'),
            $builder->buildStructureClass('App\\Db')
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class2.txt'),
            $builder->buildStructureClass('App\\Db', \PeskyORMTest\TestingAdmins\TestingAdminsTableStructure::class)
        );
    }
}
