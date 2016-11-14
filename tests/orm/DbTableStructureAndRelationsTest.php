<?php

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableRelation;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;
use PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure;
use PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure;
use PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure;
use PeskyORMTest\TestingSettings\TestingSettingsTableStructure;

class DbTableStructureAndRelationsTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::init();
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public static function tearDownAfterClass() {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public function testTableStructureCore() {
        static::assertEquals('default', TestingAdminsTableStructure::getConnectionName());
        static::assertEquals('public', TestingAdminsTableStructure::getSchema());
        static::assertEquals('admins', TestingAdminsTableStructure::getTableName());
    }

    /**
     * @expectedException \PeskyORM\ORM\Exception\OrmException
     * @expectedExceptionMessage Table schema must contain primary key
     */
    public function testAbsentPkColumn() {
        TestingNoPkColumnInTableStructure::getInstance();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Attempt to create 2nd instance of class PeskyORM\ORM\DbTableStructure
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
        static::assertInstanceOf(DbTableColumn::class, TestingAdminsTableStructure::getColumn('login'));
        static::assertInstanceOf(DbTableColumn::class, TestingAdminsTableStructure::getPkColumn());
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
        static::assertInstanceOf(DbTableRelation::class, $relation);
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
     * @expectedExceptionMessage Method 'invalid' must return instance of \PeskyORM\ORM\DbTableColumn class
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
     * @expectedExceptionMessage Method 'InvalidClass' must return instance of \PeskyORM\ORM\DbTableRelation class
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

}
