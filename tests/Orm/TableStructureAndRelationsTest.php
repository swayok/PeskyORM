<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\Exception\OrmException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmins4TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure2;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingTwoPrimaryKeysColumnsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTableStructure;
use ReflectionClass;

class TableStructureAndRelationsTest extends BaseTestCase
{
    
    public function testTableStructureCore(): void
    {
        static::assertEquals('default', TestingAdminsTableStructure::getConnectionName(false));
        static::assertEquals('writable', TestingAdminsTableStructure::getConnectionName(true));
        static::assertEquals(null, TestingAdminsTableStructure::getSchema());
        static::assertEquals('admins', TestingAdminsTableStructure::getTableName());
    }
    
    public function testAbsentPkColumn(): void
    {
        $this->expectException(OrmException::class);
        $this->expectExceptionMessage(
            "TableStructure PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure must contain primary key"
        );
        TestingNoPkColumnInTableStructure::getInstance();
    }
    
    public function testDuplicateConstruct(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Attempt to create 2nd instance of class PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        $class = new ReflectionClass(TestingAdminsTableStructure::class);
        $method = $class->getConstructor();
        $method->setAccessible(true);
        $method->invoke(TestingAdminsTableStructure::getInstance());
    }
    
    public function testStaticMethodsInDbTableConfigs(): void
    {
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSettingsTableStructure::getInstance());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdminsTableStructure::getInstance());
    }
    
    public function testTableStructureColumns(): void
    {
        $columns = TestingAdminsTableStructure::getColumns();
        static::assertCount(22, $columns);
        static::assertEquals('id', TestingAdminsTableStructure::getPkColumnName());
        static::assertTrue(TestingAdminsTableStructure::hasColumn('login'));
        static::assertFalse(TestingAdminsTableStructure::hasColumn('abrakadabra'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableColumnInterface::class, TestingAdminsTableStructure::getColumn('login'));
        static::assertInstanceOf(TableColumnInterface::class, TestingAdminsTableStructure::getPkColumn());
        static::assertEquals(
            'id',
            TestingAdminsTableStructure::getPkColumn()
                ->getName()
        );
    }
    
    public function testTableStructureRelations(): void
    {
        static::assertCount(4, TestingAdminsTableStructure::getRelations());
        static::assertTrue(TestingAdminsTableStructure::hasRelation('Parent'));
        static::assertFalse(TestingAdminsTableStructure::hasRelation('Abrakadabra'));
        $relation = TestingAdminsTableStructure::getRelation('Parent');
        static::assertInstanceOf(RelationInterface::class, $relation);
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())
                ->hasRelation($relation->getName())
        );
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())
                ->isForeignKey()
        );
    }
    
    public function testInvalidColumnGet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure does not know about column named 'abrakadabra'");
        TestingAdminsTableStructure::getColumn('abrakadabra');
    }
    
    public function testInvalidRelationGet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure does not know about relation named 'abrakadabra'"
        );
        TestingAdminsTableStructure::getRelation('abrakadabra');
    }
    
    public function testInvalidTableStructure1(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Method .*?->invalid\(\) must return an instance of class that implements .*?TableColumnInterface%"
        );
        TestingInvalidColumnsInTableStructure::getColumn('invalid');
    }
    
    public function testInvalidTableStructure2(): void
    {
        $this->expectException(OrmException::class);
        $this->expectExceptionMessage("2 primary keys in one table is forbidden");
        TestingTwoPrimaryKeysColumnsTableStructure::getColumn('pk1');
        TestingTwoPrimaryKeysColumnsTableStructure::getColumn('pk2');
    }
    
    public function testInvalidTableStructure3(): void
    {
        $this->expectException(OrmException::class);
        $this->expectExceptionMessageMatches(
            "%Method .*?->InvalidClass\(\) must return an instance of class that implements .*?RelationInterface%"
        );
        TestingInvalidRelationsInTableStructure::getRelation('InvalidClass');
    }
    
    public function testInvalidTableStructure4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #3 ($foreignTable) must be of type');
        /** @noinspection PhpParamsInspection */
        new Relation('valid', Relation::HAS_MANY, $this, 'id');
    }

    public function testInvalidTableStructure5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$foreignColumnName argument value (\'id\') refers to a primary key column. It makes no sense for HAS MANY relation.');
        new Relation('id', Relation::HAS_MANY, TestingAdminsTable::getInstance(), 'id');
    }
    
    public function testInvalidTableStructure6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("TestingInvalidRelationsInTableStructure2 does not know about column named 'local_invalid'");
        TestingInvalidRelationsInTableStructure2::getRelation('InvalidLocalColumnName');
    }

    public function testInvalidTableStructure7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("TestingAdminsTableStructure does not know about column named 'foreign_invalid'");
        new Relation('id', Relation::HAS_MANY, TestingAdminsTable::getInstance(), 'foreign_invalid');
    }
    
    public function testCreateMissingColumnConfigsFromDbTableDescription(): void
    {
        $structure = TestingAdmins4TableStructure::getInstance();
        static::assertCount(17, $structure::getColumns());
        $expected = [
            'updated_at',
            'id',
            'login',
            'password',
            'parent_id',
            'created_at',
            'remember_token',
            'is_superadmin',
            'language',
            'ip',
            'role',
            'is_active',
            'name',
            'email',
            'timezone',
            'not_changeable_column',
            'big_data',
        ];
        sort($expected);
        $actual = array_keys($structure::getColumns());
        sort($actual);
        static::assertEquals($expected, $actual);
        // defined column
        $col = $structure::getColumn('updated_at');
        static::assertFalse($col->isNullableValues());
        static::assertTrue($col->isAutoUpdatingValues());
        static::assertFalse($col->hasDefaultValue());
        // autoloaded columns
        // costraints
        static::assertTrue(
            $structure::getColumn('id')
                ->isPrimaryKey()
        );
        static::assertTrue(
            $structure::getColumn('parent_id')
                ->isForeignKey()
        ); //< it is not a mistake - it is affected only by existing relation
        static::assertTrue(
            $structure::getColumn('email')
                ->isValueMustBeUnique()
        );
        static::assertTrue(
            $structure::getColumn('login')
                ->isValueMustBeUnique()
        );
        // nullable
        static::assertTrue(
            $structure::getColumn('parent_id')
                ->isNullableValues()
        );
        static::assertTrue(
            $structure::getColumn('email')
                ->isNullableValues()
        );
        static::assertFalse(
            $structure::getColumn('login')
                ->isNullableValues()
        );
        static::assertFalse(
            $structure::getColumn('password')
                ->isNullableValues()
        );
        // defaults
        static::assertFalse(
            $structure::getColumn('email')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure::getColumn('parent_id')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure::getColumn('login')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure::getColumn('password')
                ->hasDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('language')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'en',
            $structure::getColumn('language')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('is_superadmin')
                ->hasDefaultValue()
        );
        static::assertEquals(
            false,
            $structure::getColumn('is_superadmin')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('remember_token')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure::getColumn('remember_token')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('created_at')
                ->hasDefaultValue()
        );
        static::assertEquals(
            DbExpr::create('now()'),
            $structure::getColumn('created_at')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('id')
                ->hasDefaultValue()
        );
        static::assertEquals(
            DbExpr::create("nextval('admins_id_seq'::regclass)"),
            $structure::getColumn('id')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('ip')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '192.168.1.1',
            $structure::getColumn('ip')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('role')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure::getColumn('role')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('is_active')
                ->hasDefaultValue()
        );
        static::assertEquals(
            true,
            $structure::getColumn('is_active')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('timezone')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'UTC',
            $structure::getColumn('timezone')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure::getColumn('not_changeable_column')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'not changable',
            $structure::getColumn('not_changeable_column')
                ->getDefaultValue()
        );
        // types
        static::assertEquals(
            TableColumn::TYPE_BOOL,
            $structure::getColumn('is_active')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_BOOL,
            $structure::getColumn('is_superadmin')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_STRING,
            $structure::getColumn('ip')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_INT,
            $structure::getColumn('id')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_INT,
            $structure::getColumn('parent_id')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_TIMESTAMP_WITH_TZ,
            $structure::getColumn('created_at')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_TIMESTAMP,
            $structure::getColumn('updated_at')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_STRING,
            $structure::getColumn('language')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_STRING,
            $structure::getColumn('login')
                ->getDataType()
        );
        static::assertEquals(
            \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING,
            $structure::getColumn('password')
                ->getDataType()
        );
        static::assertEquals(
            \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING,
            $structure::getColumn('remember_token')
                ->getDataType()
        );
        static::assertEquals(
            \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING,
            $structure::getColumn('role')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_STRING,
            $structure::getColumn('name')
                ->getDataType()
        );
        static::assertEquals(
            \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING,
            $structure::getColumn('email')
                ->getDataType()
        );
        static::assertEquals(
            \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING,
            $structure::getColumn('timezone')
                ->getDataType()
        );
        static::assertEquals(
            TableColumn::TYPE_STRING,
            $structure::getColumn('not_changeable_column')
                ->getDataType()
        );
    }
    
}
