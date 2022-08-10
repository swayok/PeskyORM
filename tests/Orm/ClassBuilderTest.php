<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\Column;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;
use PeskyORM\Tests\PeskyORMTest\Traits\TestingCreatedAtColumnTrait;
use PeskyORM\Tests\PeskyORMTest\Traits\TestingIdColumnTrait;
use PeskyORM\Tests\PeskyORMTest\Traits\TestingTimestampColumnsTrait;

class ClassBuilderTest extends BaseTestCase
{
    
    /**
     * @param string $tableName
     * @return ClassBuilder
     */
    protected function getBuilder($tableName = 'admins')
    {
        return new ClassBuilder($tableName, TestingApp::getPgsqlConnection());
    }
    
    public function testBuilderServiceMethods()
    {
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
            $this->callObjectMethod($builder, 'getShortClassName', TestingAdmin::class)
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
    
    public function testTableAndRecordClassBuilding()
    {
        $builder = $this->getBuilder();
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/table_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildTableClass('App\\Db'))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/table_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildTableClass('App\\Db', TestingBaseTable::class))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/record_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildRecordClass('App\\Db'))
        );
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/record_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildRecordClass('App\\Db', TestingAdmin::class))
        );
    }
    
    public function testMakeColumnConfig()
    {
        $builder = $this->getBuilder();
        $columnDescr = new \PeskyORM\Core\ColumnDescription('test', 'integer', Column::TYPE_INT);
        $columnDescr->setIsPrimaryKey(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->primaryKey()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsPrimaryKey(false)
            ->setIsUnique(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->uniqueValues()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsUnique(false)
            ->setIsNullable(false);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->disallowsNullValues()->convertsEmptyStringToNull()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setIsNullable(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault('string with \' quotes " `');
        static::assertEquals(
            "Column::create(Column::TYPE_INT)->setDefaultValue('string with \\' quotes \\\" `')",
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->setDefaultValue(true)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(false);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->setDefaultValue(false)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(null);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(111);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->setDefaultValue(111)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(111.11);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->setDefaultValue(111.11)',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(\PeskyORM\Core\DbExpr::create("string with ' quotes \" `"));
        static::assertEquals(
            "Column::create(Column::TYPE_INT)->setDefaultValue(DbExpr::create('string with \' quotes \\\" `'))",
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
        $columnDescr
            ->setDefault(\PeskyORM\Core\DbExpr::create("string with ' quotes \" `"))
            ->setIsPrimaryKey(true);
        static::assertEquals(
            'Column::create(Column::TYPE_INT)->primaryKey()',
            preg_replace("%\n| {12}%m", '', $this->callObjectMethod($builder, 'makeColumnConfig', $columnDescr))
        );
    }
    
    public function testDbStructureClassBuilder()
    {
        $builder = $this->getBuilder();
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class1.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildStructureClass('App\\Db'))
        );
        $builder->setDbSchemaName('public');
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class2.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildStructureClass('App\\Db', TestingAdminsTableStructure::class))
        );
        $traits = [
            TestingIdColumnTrait::class,
            TestingTimestampColumnsTrait::class,
            TestingCreatedAtColumnTrait::class,
        ];
        static::assertEquals(
            preg_replace("%[\r\n\t]+%", '', file_get_contents(__DIR__ . '/classes_to_test_builder/structure_class3.txt')),
            preg_replace("%[\r\n\t]+%", '', $builder->buildStructureClass('App\\Db', TestingAdminsTableStructure::class, $traits))
        );
    }
}
