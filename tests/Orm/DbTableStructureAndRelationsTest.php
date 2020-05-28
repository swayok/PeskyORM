<?php

namespace Tests\Orm;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\PeskyORMTest\TestingAdmins\TestingAdmins4TableStructure;
use Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use Tests\PeskyORMTest\TestingApp;
use Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure;
use Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure;
use Tests\PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure;
use Tests\PeskyORMTest\TestingSettings\TestingSettingsTableStructure;

class DbTableStructureAndRelationsTest extends TestCase {

    public static function setUpBeforeClass(): void {
        TestingApp::getPgsqlConnection();
        TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public static function tearDownAfterClass(): void {
        TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public function testTableStructureCore() {
        static::assertEquals('default', TestingAdminsTableStructure::getConnectionName(false));
        static::assertEquals('writable', TestingAdminsTableStructure::getConnectionName(true));
        static::assertEquals(null, TestingAdminsTableStructure::getSchema());
        static::assertEquals('admins', TestingAdminsTableStructure::getTableName());
    }

    /**
     * @expectedException \PeskyORM\Exception\OrmException
     * @expectedExceptionMessage Table schema must contain primary key
     */
    public function testAbsentPkColumn() {
        TestingNoPkColumnInTableStructure::getInstance();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Attempt to create 2nd instance of class PeskyORM\ORM\TableStructure
     */
    public function testDuplicateConstruct() {
        $class = new ReflectionClass(TestingAdminsTableStructure::class);
        $method = $class->getConstructor();
        $method->setAccessible(true);
        $method->invoke(TestingAdminsTableStructure::getInstance());
    }

    public function testStaticMethodsInDbTableConfigs() {
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSettingsTableStructure::getInstance());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdminsTableStructure::getInstance());

        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSettingsTableStructure::i());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdminsTableStructure::i());
    }

    public function testTableStructureColumns() {
        $columns = TestingAdminsTableStructure::getColumns();
        static::assertCount(19, $columns);
        static::assertTrue(TestingAdminsTableStructure::hasPkColumn());
        static::assertEquals('id', TestingAdminsTableStructure::getPkColumnName());
        static::assertTrue(TestingAdminsTableStructure::hasColumn('login'));
        static::assertFalse(TestingAdminsTableStructure::hasColumn('abrakadabra'));
        static::assertInstanceOf(Column::class, TestingAdminsTableStructure::getColumn('login'));
        static::assertInstanceOf(Column::class, TestingAdminsTableStructure::getPkColumn());
        static::assertEquals('id', TestingAdminsTableStructure::getPkColumn()->getName());
        static::assertTrue(TestingAdminsTableStructure::hasFileColumns());
        static::assertCount(2, TestingAdminsTableStructure::getFileColumns());
        static::assertTrue(TestingAdminsTableStructure::hasFileColumn('avatar'));
        static::assertFalse(TestingAdminsTableStructure::hasFileColumn('abrakadabra'));
    }

    public function testTableStructureRelations() {
        static::assertCount(4, TestingAdminsTableStructure::getRelations());
        static::assertTrue(TestingAdminsTableStructure::hasRelation('Parent'));
        static::assertFalse(TestingAdminsTableStructure::hasRelation('Abrakadabra'));
        $relation = TestingAdminsTableStructure::getRelation('Parent');
        static::assertInstanceOf(Relation::class, $relation);
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())->hasRelation($relation->getName())
        );
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())->isItAForeignKey()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Table does not contain column named 'abrakadabra'
     */
    public function testInvalidColumnGet() {
        TestingAdminsTableStructure::getColumn('abrakadabra');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage There is no relation 'abrakadabra' in PeskyORMTest\TestingAdmins\TestingAdminsTableStructure
     */
    public function testInvalidRelationGet() {
        TestingAdminsTableStructure::getRelation('abrakadabra');
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Method 'invalid' must return instance of \PeskyORM\ORM\Column class
     */
    public function testInvalidTableStructure1() {
        TestingInvalidColumnsInTableStructure::getColumn('invalid');
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage 2 primary keys in one table is forbidden
     */
    public function testInvalidTableStructure2() {
        TestingInvalidColumnsInTableStructure::getColumn('pk1');
        TestingInvalidColumnsInTableStructure::getColumn('pk2');
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Method 'InvalidClass' must return instance of \PeskyORM\ORM\Relation class
     */
    public function testInvalidTableStructure3() {
        TestingInvalidRelationsInTableStructure::getRelation('InvalidClass');
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Table 'some_table' has no column 'local_invalid' or column is not defined yet
     */
    public function testInvalidTableStructure4() {
        TestingInvalidRelationsInTableStructure::getRelation('InvalidLocalColumnName');
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Table 'admins' has no column 'foreign_invalid' or column is not defined yet
     */
    public function testInvalidTableStructure5() {
        TestingInvalidRelationsInTableStructure::getRelation('InvalidForeignColumnName');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $foreignTableClass argument contains invalid value: class '___class_invalid' does not exist
     */
    public function testInvalidTableStructure6() {
        TestingInvalidRelationsInTableStructure::getRelation('InvalidForeignTableClass');
    }

    public function testCreateMissingColumnConfigsFromDbTableDescription() {
        $structure = TestingAdmins4TableStructure::getInstance();
        static::assertCount(16, $structure::getColumns());
        static::assertEquals(
            [
                'updated_at', 'id', 'login', 'password', 'parent_id', 'created_at', 'remember_token', 'is_superadmin',
                'language', 'ip', 'role', 'is_active', 'name', 'email', 'timezone', 'not_changeable_column'
            ],
            array_keys($structure::getColumns())
        );
        // defined column
        $col = $structure::getColumn('updated_at');
        static::assertFalse($col->isValueCanBeNull());
        static::assertTrue($col->isAutoUpdatingValue());
        static::assertFalse($col->hasDefaultValue());
        // autoloaded columns
        // costraints
        static::assertTrue($structure::getColumn('id')->isItPrimaryKey());
        static::assertTrue($structure::getColumn('parent_id')->isItAForeignKey()); //< its not a mistake - it is affected only be existing relation
        static::assertTrue($structure::getColumn('email')->isValueMustBeUnique());
        static::assertTrue($structure::getColumn('login')->isValueMustBeUnique());
        // nullable
        static::assertTrue($structure::getColumn('parent_id')->isValueCanBeNull());
        static::assertTrue($structure::getColumn('email')->isValueCanBeNull());
        static::assertFalse($structure::getColumn('login')->isValueCanBeNull());
        static::assertFalse($structure::getColumn('password')->isValueCanBeNull());
        // defaults
        static::assertFalse($structure::getColumn('email')->hasDefaultValue());
        static::assertFalse($structure::getColumn('parent_id')->hasDefaultValue());
        static::assertFalse($structure::getColumn('login')->hasDefaultValue());
        static::assertFalse($structure::getColumn('password')->hasDefaultValue());
        static::assertTrue($structure::getColumn('language')->hasDefaultValue());
        static::assertEquals('en', $structure::getColumn('language')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('is_superadmin')->hasDefaultValue());
        static::assertEquals(false, $structure::getColumn('is_superadmin')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('remember_token')->hasDefaultValue());
        static::assertEquals('', $structure::getColumn('remember_token')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('created_at')->hasDefaultValue());
        static::assertEquals(DbExpr::create('now()'), $structure::getColumn('created_at')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('id')->hasDefaultValue());
        static::assertEquals(
            DbExpr::create("nextval('admins_id_seq'::regclass)"),
            $structure::getColumn('id')->getDefaultValueAsIs()
        );
        static::assertTrue($structure::getColumn('ip')->hasDefaultValue());
        static::assertEquals('192.168.1.1', $structure::getColumn('ip')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('role')->hasDefaultValue());
        static::assertEquals('', $structure::getColumn('role')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('is_active')->hasDefaultValue());
        static::assertEquals(true, $structure::getColumn('is_active')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('timezone')->hasDefaultValue());
        static::assertEquals('UTC', $structure::getColumn('timezone')->getDefaultValueAsIs());
        static::assertTrue($structure::getColumn('not_changeable_column')->hasDefaultValue());
        static::assertEquals('not changable', $structure::getColumn('not_changeable_column')->getDefaultValueAsIs());
        // types
        static::assertEquals(Column::TYPE_BOOL, $structure::getColumn('is_active')->getType());
        static::assertEquals(Column::TYPE_BOOL, $structure::getColumn('is_superadmin')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('ip')->getType());
        static::assertEquals(Column::TYPE_INT, $structure::getColumn('id')->getType());
        static::assertEquals(Column::TYPE_INT, $structure::getColumn('parent_id')->getType());
        static::assertEquals(Column::TYPE_TIMESTAMP_WITH_TZ, $structure::getColumn('created_at')->getType());
        static::assertEquals(Column::TYPE_TIMESTAMP, $structure::getColumn('updated_at')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('language')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('login')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('password')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('remember_token')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('role')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('name')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('email')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('timezone')->getType());
        static::assertEquals(Column::TYPE_STRING, $structure::getColumn('not_changeable_column')->getType());
    }

}
