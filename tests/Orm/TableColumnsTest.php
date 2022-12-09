<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BlobColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\DateColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\EmailColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\FloatColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IpV4AddressColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\JsonArrayColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\JsonObjectColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\MixedJsonColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\PasswordColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimezoneOffsetColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\UnixTimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class TableColumnsTest extends BaseTestCase
{
    public function testInvalidConstructor(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($name) must be of type string, null given');
        /** @noinspection PhpStrictTypeCheckingInspection */
        new StringColumn(null);
    }

    public function testRelationNotExistsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*?'test'.* is not linked with 'Admin' relation%"
        );
        $column = new StringColumn('test');
        $column->getRelation('Admin');
    }

    public function testDefaultValueNotExistsException1(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'test'.* is not set%"
        );
        $column = new StringColumn('test');
        $column->getDefaultValue();
    }

    public function testDefaultValueNotExistsException2(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'test'.* is not set%"
        );
        $column = new StringColumn('test');
        $column->getValidDefaultValue();
    }

    public function testSetDefaultValueForPKColumn(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Primary key column .*?'test'.* is not allowed to have default value%"
        );
        $column = new IdColumn('test');
        $column->setDefaultValue(1);
    }

    public function testCommonMethods(): void
    {
        $columns = [
            BlobColumn::class,
            BooleanColumn::class,
            DateColumn::class,
            EmailColumn::class,
            FloatColumn::class,
            IdColumn::class,
            IntegerColumn::class,
            IpV4AddressColumn::class,
            JsonArrayColumn::class,
            JsonObjectColumn::class,
            MixedJsonColumn::class,
            PasswordColumn::class,
            StringColumn::class,
            TextColumn::class,
            TimeColumn::class,
            TimestampColumn::class,
            TimezoneOffsetColumn::class,
            UnixTimestampColumn::class
        ];

        $columnName = 'some_name';
        $relationName = 'Admin';
        $adminsTable = TestingAdminsTable::getInstance();
        $adminsTableStructure = TestingAdminsTableStructure::getInstance();
        $relationHasOne = new Relation($columnName, Relation::HAS_ONE, $adminsTable, 'id', $relationName . 'One');
        $relationBelongsTo = new Relation($columnName, Relation::BELONGS_TO, $adminsTable, 'id', $relationName . 'Foreign');
        $relationHasMany = new Relation($columnName, Relation::HAS_MANY, $adminsTable, 'parent_id', $relationName . 'Many');
        $adminRecord = new TestingAdmin();

        foreach ($columns as $i => $class) {
            /** @var RealTableColumnAbstract $column */
            $column = new $class($columnName);
            static::assertEquals($columnName, $column->getName(), $class);
            static::assertNotEmpty($column->getDataType(), $class);
            static::assertTrue($column->isReal(), $class);
            // table structure
            static::assertNull($column->getTableStructure(), $class);
            $column->setTableStructure($adminsTableStructure);
            static::assertEquals($adminsTableStructure, $column->getTableStructure(), $class);
            // primary key
            if ($column instanceof IdColumn) {
                static::assertTrue($column->isPrimaryKey(), $class);
                static::assertTrue($column->isNullableValues(), $class);
            } else {
                static::assertFalse($column->isPrimaryKey(), $class);
                static::assertFalse($column->isNullableValues(), $class);
            }
            // relations
            static::assertFalse($column->isForeignKey(), $class);
            static::assertEmpty($column->getRelations(), $class);
            static::assertFalse($column->hasRelation($relationName), $class);
            static::assertNull($column->getForeignKeyRelation(), $class);
            static::assertCount(0, $column->getRelations(), $class);
            $column->addRelation($relationHasOne);
            static::assertTrue($column->hasRelation($relationHasOne->getName()), $class);
            static::assertEquals($relationHasOne, $column->getRelation($relationHasOne->getName()), $class);
            static::assertFalse($column->isForeignKey(), $class);
            static::assertNull($column->getForeignKeyRelation(), $class);
            static::assertCount(1, $column->getRelations(), $class);
            $column->addRelation($relationHasMany);
            static::assertTrue($column->hasRelation($relationHasMany->getName()), $class);
            static::assertEquals($relationHasMany, $column->getRelation($relationHasMany->getName()), $class);
            static::assertFalse($column->isForeignKey(), $class);
            static::assertNull($column->getForeignKeyRelation(), $class);
            static::assertCount(2, $column->getRelations(), $class);
            $column->addRelation($relationBelongsTo);
            static::assertTrue($column->hasRelation($relationBelongsTo->getName()), $class);
            static::assertEquals($relationBelongsTo, $column->getRelation($relationBelongsTo->getName()), $class);
            static::assertTrue($column->isForeignKey(), $class);
            static::assertEquals($relationBelongsTo, $column->getForeignKeyRelation(), $class);
            static::assertCount(3, $column->getRelations(), $class);
            // default value existence
            static::assertFalse($column->hasDefaultValue(), $class);
            if (!$column->isPrimaryKey()) {
                $column->setDefaultValue("test");
                static::assertTrue($column->hasDefaultValue(), $class);
            }
            // flags
            static::assertFalse($column->isHeavyValues(), $class);
            static::assertFalse($column->isValueMustBeUnique(), $class);
            static::assertFalse($column->isAutoUpdatingValues(), $class);
            static::assertFalse($column->isReadonly(), $class);
            static::assertFalse($column->isFile(), $class);
            if ($column instanceof PasswordColumn) {
                static::assertTrue($column->isPrivateValues(), $class);
            } else {
                static::assertFalse($column->isPrivateValues(), $class);
            }
            // new record value container
            $container = $column->getNewRecordValueContainer($adminRecord);
            /** @noinspection UnnecessaryAssertionInspection */
            static::assertInstanceOf(RecordValueContainerInterface::class, $container, $class);
        }
    }

    public function testStringColumn(): void
    {
        $column = new StringColumn('string');
        static::assertEquals(TableColumnDataType::STRING, $column->getDataType());
        static::assertEquals([], $column->getColumnNameAliases());
        // todo: not finished
    }
}