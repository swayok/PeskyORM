<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\Exception\TableStructureConfigException;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmins4TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidColumnsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingInvalidRelationsInTableStructure2;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingTwoPrimaryKeysColumnsTableStructure;

class TableStructureTest extends BaseTestCase
{
    public function testTableStructureCore(): void
    {
        $tableStructure = new TestingAdminsTableStructure();
        static::assertEquals('default', $tableStructure->getConnectionName(false));
        static::assertEquals('writable', $tableStructure->getConnectionName(true));
        static::assertEquals(null, $tableStructure->getSchema());
        static::assertEquals('admins', $tableStructure->getTableName());
    }
    
    public function testAbsentPkColumn(): void
    {
        $this->expectException(TableStructureConfigException::class);
        $this->expectExceptionMessage(
            "TableStructureOld PeskyORM\Tests\PeskyORMTest\TestingInvalidClasses\TestingNoPkColumnInTableStructure must contain primary key"
        );
        new TestingNoPkColumnInTableStructure();
    }
    
    public function testTableStructureColumns(): void
    {
        $structure = new TestingAdminsTableStructure();
        $columns = $structure->getColumns();
        static::assertCount(21, $columns);
        static::assertEquals('id', $structure->getPkColumnName());
        static::assertTrue($structure->hasColumn('login'));
        static::assertFalse($structure->hasColumn('abrakadabra'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableColumnInterface::class, $structure->getColumn('login'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableColumnInterface::class, $structure->getPkColumn());
        static::assertEquals('id', $structure->getPkColumn()->getName());
    }
    
    public function testTableStructureRelations(): void
    {
        $structure = new TestingAdminsTableStructure();
        static::assertCount(4, $structure->getRelations());
        static::assertTrue($structure->hasRelation('Parent'));
        static::assertFalse($structure->hasRelation('Abrakadabra'));
        $relation = $structure->getRelation('Parent');
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(RelationInterface::class, $relation);
        static::assertTrue(
            $structure->getColumn($relation->getLocalColumnName())
                ->hasRelation($relation->getName())
        );
        static::assertTrue(
            $structure->getColumn($relation->getLocalColumnName())
                ->isForeignKey()
        );
    }
    
    public function testInvalidColumnGet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "TestingAdminsTableStructure does not know about column named 'abrakadabra'"
        );
        (new TestingAdminsTableStructure())->getColumn('abrakadabra');
    }
    
    public function testInvalidRelationGet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "TestingAdminsTableStructure does not know about relation named 'abrakadabra'"
        );
        (new TestingAdminsTableStructure())->getRelation('abrakadabra');
    }
    
    public function testInvalidTableStructure1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches(
            '%Argument #1 \(\$column\) must be of type .*TableColumnInterface%'
        );
        new TestingInvalidColumnsInTableStructure();
    }
    
    public function testInvalidTableStructure2(): void
    {
        $this->expectException(TableStructureConfigException::class);
        $this->expectExceptionMessage("2 primary keys in one table is forbidden");
        new TestingTwoPrimaryKeysColumnsTableStructure();
    }
    
    public function testInvalidTableStructure3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches(
            '%Argument #1 \(\$relation\) must be of type .*RelationInterface%'
        );
        new TestingInvalidRelationsInTableStructure();
    }
    
    public function testInvalidTableStructure4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "TestingInvalidRelationsInTableStructure2 does not know about column named 'local_invalid'"
        );
        (new TestingInvalidRelationsInTableStructure2())
            ->getRelation('InvalidLocalColumnName');
    }

    public function testCreateMissingColumnConfigsFromDbTableDescription(): void
    {
        $structure = new TestingAdmins4TableStructure();
        static::assertCount(17, $structure->getColumns());
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
        $actual = array_keys($structure->getColumns());
        sort($actual);
        static::assertEquals($expected, $actual);
        // defined column
        $col = $structure->getColumn('updated_at');
        static::assertFalse($col->isNullableValues());
        static::assertTrue($col->isAutoUpdatingValues());
        static::assertFalse($col->hasDefaultValue());
        // autoloaded columns
        // costraints
        static::assertTrue(
            $structure->getColumn('id')
                ->isPrimaryKey()
        );
        static::assertTrue(
            $structure->getColumn('parent_id')
                ->isForeignKey()
        ); //< it is not a mistake - it is affected only by existing relation
        static::assertTrue(
            $structure->getColumn('email')
                ->isValueMustBeUnique()
        );
        static::assertTrue(
            $structure->getColumn('login')
                ->isValueMustBeUnique()
        );
        // nullable
        static::assertTrue(
            $structure->getColumn('parent_id')
                ->isNullableValues()
        );
        static::assertTrue(
            $structure->getColumn('email')
                ->isNullableValues()
        );
        static::assertFalse(
            $structure->getColumn('login')
                ->isNullableValues()
        );
        static::assertFalse(
            $structure->getColumn('password')
                ->isNullableValues()
        );
        // defaults
        static::assertFalse(
            $structure->getColumn('email')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure->getColumn('parent_id')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure->getColumn('login')
                ->hasDefaultValue()
        );
        static::assertFalse(
            $structure->getColumn('password')
                ->hasDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('language')
                ->hasDefaultValue()
        );
        static::assertEquals(
            'en',
            $structure->getColumn('language')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('is_superadmin')
                ->hasDefaultValue()
        );
        static::assertEquals(
            false,
            $structure->getColumn('is_superadmin')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('remember_token')
                ->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure->getColumn('remember_token')
                ->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('created_at')
                ->hasDefaultValue()
        );
        static::assertEquals(
            DbExpr::create('now()'),
            $structure->getColumn('created_at')
                ->getDefaultValue()
        );
        static::assertFalse($structure->getColumn('id')->hasDefaultValue());
        static::assertTrue($structure->getColumn('ip')->hasDefaultValue());
        static::assertEquals(
            '192.168.1.1',
            $structure->getColumn('ip')->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('role')->hasDefaultValue()
        );
        static::assertEquals(
            '',
            $structure->getColumn('role')->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('is_active')->hasDefaultValue()
        );
        static::assertEquals(
            true,
            $structure->getColumn('is_active')->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('timezone')->hasDefaultValue()
        );
        static::assertEquals(
            'UTC',
            $structure->getColumn('timezone')->getDefaultValue()
        );
        static::assertTrue(
            $structure->getColumn('not_changeable_column')->hasDefaultValue()
        );
        static::assertEquals(
            'not changable',
            $structure->getColumn('not_changeable_column')->getDefaultValue()
        );
        // types
        static::assertEquals(
            TableColumnDataType::BOOL,
            $structure->getColumn('is_active')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::BOOL,
            $structure->getColumn('is_superadmin')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('ip')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::INT,
            $structure->getColumn('id')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::INT,
            $structure->getColumn('parent_id')->getDataType()
        );
        /** @var TimestampColumn $createdAt */
        $createdAt = $structure->getColumn('created_at');
        static::assertEquals(
            TableColumnDataType::TIMESTAMP,
            $createdAt->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::TIMESTAMP,
            $createdAt->isTimezoneExpected()
        );
        static::assertEquals(
            TableColumnDataType::TIMESTAMP,
            $structure->getColumn('updated_at')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('language')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('login')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('password')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('remember_token')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('role')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('name')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('email')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('timezone')->getDataType()
        );
        static::assertEquals(
            TableColumnDataType::STRING,
            $structure->getColumn('not_changeable_column')->getDataType()
        );
    }

    public function testColumnsGroups(): void
    {
        $structure = new TestingAdminsTableStructure();

        static::assertEquals('id', $structure->getPkColumn()->getName());

        $allcolumns = [
            'id',
            'parent_id',
            'login',
            'password',
            'created_at',
            'updated_at',
            'remember_token',
            'is_superadmin',
            'language',
            'ip',
            'role',
            'is_active',
            'name',
            'email',
            'timezone',
            'avatar',
            'some_file',
            'not_changeable_column',
            'not_existing_column',
            'not_existing_column_with_calculated_value',
            'big_data',
        ];
        static::assertEquals(
            $allcolumns,
            array_keys($structure->getColumns())
        );

        $realColumns = [
            'id',
            'parent_id',
            'login',
            'password',
            'created_at',
            'updated_at',
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
            'big_data'
        ];
        static::assertEquals(
            $realColumns,
            array_keys($structure->getRealColumns())
        );
        $virtualColumns = [
            'avatar',
            'some_file',
            'not_existing_column',
            'not_existing_column_with_calculated_value',
        ];
        static::assertEquals(
            $virtualColumns,
            array_keys($structure->getVirtualColumns())
        );
        $notHeavyColumns = [
            'id',
            'parent_id',
            'login',
            'password',
            'created_at',
            'updated_at',
            'remember_token',
            'is_superadmin',
            'language',
            'ip',
            'role',
            'is_active',
            'name',
            'email',
            'timezone',
            'avatar',
            'some_file',
            'not_changeable_column',
            'not_existing_column',
            'not_existing_column_with_calculated_value',
        ];
        static::assertEquals(
            $notHeavyColumns,
            array_keys($structure->getNotHeavyColumns())
        );
        $notPrivateColumns = [
            'id',
            'parent_id',
            'login',
            'created_at',
            'updated_at',
            'remember_token',
            'is_superadmin',
            'language',
            'ip',
            'role',
            'is_active',
            'name',
            'email',
            'timezone',
            'avatar',
            'some_file',
            'not_changeable_column',
            'not_existing_column',
            'not_existing_column_with_calculated_value',
            'big_data'
        ];
        static::assertEquals(
            $notPrivateColumns,
            array_keys($structure->getNotPrivateColumns())
        );

        $savableColumns = [
            'id',
            'parent_id',
            'login',
            'password',
            'created_at',
            'updated_at',
            'remember_token',
            'is_superadmin',
            'language',
            'ip',
            'role',
            'is_active',
            'name',
            'email',
            'timezone',
            'big_data',
        ];
        static::assertEquals(
            $savableColumns,
            array_keys($structure->getColumnsWhichValuesCanBeSavedToDb())
        );

        $autoupdatingColumns = [
            'updated_at'
        ];
        static::assertEquals(
            $autoupdatingColumns,
            array_keys($structure->getRealAutoupdatingColumns())
        );
    }

    public function testRelationsList(): void
    {
        $structure = new TestingAdminsTableStructure();
        $relations = [
            'Parent',
            'HasOne',
            'Children',
            'VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters',
        ];
        static::assertEquals($relations, array_keys($structure->getRelations()));
    }
}
