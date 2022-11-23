<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\Exception\OrmException;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmins4TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure2;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure3;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure4;
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
        
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSettingsTableStructure::i());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdminsTableStructure::i());
    }
    
    public function testTableStructureColumns(): void
    {
        $columns = TestingAdminsTableStructure::getColumns();
        static::assertCount(22, $columns);
        static::assertTrue(TestingAdminsTableStructure::hasPkColumn());
        static::assertEquals('id', TestingAdminsTableStructure::getPkColumnName());
        static::assertTrue(TestingAdminsTableStructure::hasColumn('login'));
        static::assertFalse(TestingAdminsTableStructure::hasColumn('abrakadabra'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(Column::class, TestingAdminsTableStructure::getColumn('login'));
        static::assertInstanceOf(Column::class, TestingAdminsTableStructure::getPkColumn());
        static::assertEquals(
            'id',
            TestingAdminsTableStructure::getPkColumn()
                ->getName()
        );
        static::assertTrue(TestingAdminsTableStructure::hasFileColumns());
        static::assertCount(2, TestingAdminsTableStructure::getFileColumns());
        static::assertTrue(TestingAdminsTableStructure::hasFileColumn('avatar'));
        static::assertFalse(TestingAdminsTableStructure::hasFileColumn('abrakadabra'));
    }
    
    public function testTableStructureRelations(): void
    {
        static::assertCount(4, TestingAdminsTableStructure::getRelations());
        static::assertTrue(TestingAdminsTableStructure::hasRelation('Parent'));
        static::assertFalse(TestingAdminsTableStructure::hasRelation('Abrakadabra'));
        $relation = TestingAdminsTableStructure::getRelation('Parent');
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(Relation::class, $relation);
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())
                ->hasRelation($relation->getName())
        );
        static::assertTrue(
            TestingAdminsTableStructure::getColumn($relation->getLocalColumnName())
                ->isItAForeignKey()
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
        $this->expectException(OrmException::class);
        $this->expectExceptionMessage(
            "Method PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure->invalid() must return instance of \PeskyORM\ORM\Column class"
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
        $this->expectExceptionMessage(
            "Method PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure->InvalidClass() must return instance of \PeskyORM\ORM\Relation class"
        );
        TestingInvalidRelationsInTableStructure::getRelation('InvalidClass');
    }
    
    public function testInvalidTableStructure4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$foreignTableClass argument contains invalid value: class '___class_invalid' does not exist");
        TestingInvalidRelationsInTableStructure2::getRelation('InvalidForeignTableClass');
    }
    
    public function testInvalidTableStructure5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure3 does not know about column named 'local_invalid'");
        TestingInvalidRelationsInTableStructure3::getRelation('InvalidLocalColumnName');
    }
    
    public function testInvalidTableStructure6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Related table PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable has no column 'foreign_invalid'");
        TestingInvalidRelationsInTableStructure4::getRelation('InvalidForeignColumnName')->getForeignColumnName();
    }
    
    public function testCreateMissingColumnConfigsFromDbTableDescription(): void
    {
        $structure = TestingAdmins4TableStructure::getInstance();
        static::assertCount(17, $structure::getColumns());
        static::assertEquals(
            [
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
        static::assertTrue(
            $structure::getColumn('id')
                ->isItPrimaryKey()
        );
        static::assertTrue(
            $structure::getColumn('parent_id')
                ->isItAForeignKey()
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
                ->isValueCanBeNull()
        );
        static::assertTrue(
            $structure::getColumn('email')
                ->isValueCanBeNull()
        );
        static::assertFalse(
            $structure::getColumn('login')
                ->isValueCanBeNull()
        );
        static::assertFalse(
            $structure::getColumn('password')
                ->isValueCanBeNull()
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
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('is_superadmin')
                ->hasDefaultValue()
        );
        static::assertEquals(
            false,
            $structure::getColumn('is_superadmin')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('remember_token')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure::getColumn('remember_token')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('created_at')
                ->hasDefaultValue()
        );
        static::assertEquals(
            DbExpr::create('now()'),
            $structure::getColumn('created_at')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('id')
                ->hasDefaultValue()
        );
        static::assertEquals(
            DbExpr::create("nextval('admins_id_seq'::regclass)"),
            $structure::getColumn('id')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('ip')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '192.168.1.1',
            $structure::getColumn('ip')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('role')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure::getColumn('role')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('is_active')
                ->hasDefaultValue()
        );
        static::assertEquals(
            true,
            $structure::getColumn('is_active')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('timezone')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'UTC',
            $structure::getColumn('timezone')
                ->getDefaultValueAsIs()
        );
        static::assertTrue(
            $structure::getColumn('not_changeable_column')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'not changable',
            $structure::getColumn('not_changeable_column')
                ->getDefaultValueAsIs()
        );
        // types
        static::assertEquals(
            Column::TYPE_BOOL,
            $structure::getColumn('is_active')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_BOOL,
            $structure::getColumn('is_superadmin')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('ip')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_INT,
            $structure::getColumn('id')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_INT,
            $structure::getColumn('parent_id')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_TIMESTAMP_WITH_TZ,
            $structure::getColumn('created_at')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_TIMESTAMP,
            $structure::getColumn('updated_at')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('language')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('login')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('password')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('remember_token')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('role')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('name')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('email')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('timezone')
                ->getType()
        );
        static::assertEquals(
            Column::TYPE_STRING,
            $structure::getColumn('not_changeable_column')
                ->getType()
        );
    }
    
}
