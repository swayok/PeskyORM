<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
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
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Utils\StringUtils;

class TableColumnsBasicsTest extends BaseTestCase
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
            UnixTimestampColumn::class,
        ];

        $relationName = 'Admin';
        $adminsTable = TestingAdminsTable::getInstance();
        $adminsTableStructure = TestingAdminsTableStructure::getInstance();
        $adminRecord = new TestingAdmin();

        foreach ($columns as $class) {
            $columnName = $this->getColumnNameFromClass($class);
            /** @var RealTableColumnAbstract $column */
            $column = new $class($columnName);
            static::assertEquals($columnName, $column->getName(), $class);
            static::assertNotEmpty($column->getDataType(), $class);
            static::assertTrue($column->isReal(), $class);
            // table structure
            static::assertNull($column->getTableStructure(), $class);
            $column->setTableStructure($adminsTableStructure);
            static::assertEquals($adminsTableStructure, $column->getTableStructure(), $class);
            // new record value container
            $container = $column->getNewRecordValueContainer($adminRecord);
            static::assertInstanceOf(RecordValueContainerInterface::class, $container, $class);
            static::assertFalse($column->hasValue($container, false));
            static::assertFalse($column->hasValue($container, true));
            // primary key
            if ($column instanceof IdColumn) {
                static::assertTrue($column->isPrimaryKey(), $class);
                static::assertTrue($column->isNullableValues(), $class);
            } else {
                static::assertFalse($column->isPrimaryKey(), $class);
                static::assertFalse($column->isNullableValues(), $class);
            }
            // relations
            $relationHasOne = new Relation($columnName, Relation::HAS_ONE, $adminsTable, 'id', $relationName . 'One');
            $relationBelongsTo = new Relation($columnName, Relation::BELONGS_TO, $adminsTable, 'id', $relationName . 'Foreign');
            $relationHasMany = new Relation($columnName, Relation::HAS_MANY, $adminsTable, 'parent_id', $relationName . 'Many');
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
            if ($this->isDefaultValueAllowedForColumn($column)) {
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
            // default value as DbExpr
            /** @var RealTableColumnAbstract $column */
            $column = new $class($columnName);
            if ($this->isDefaultValueAllowedForColumn($column)) {
                $dbExpr = new DbExpr('Test dbexpr');
                $column->setDefaultValue($dbExpr);
                static::assertTrue($column->hasDefaultValue(), $class);
                static::assertFalse($column->hasValue($container, false));
                static::assertTrue($column->hasValue($container, true));
                static::assertEquals($dbExpr, $column->getDefaultValue(), $class);
                static::assertEquals($dbExpr, $column->getValidDefaultValue(), $class);
                $container = $column->getNewRecordValueContainer($adminRecord);
                $column->setValue($container, null, false, false);
                static::assertEquals($dbExpr, $container->getValue(), $class);
                static::assertEquals($dbExpr, $column->getValue($container, null), $class);
            }
            // validate value
            // DbExpr
            $expectedErrors = [
                'Value received from DB cannot be instance of DbExpr.',
            ];
            $dbExpr = new DbExpr('test');
            static::assertEquals([], $column->validateValue($dbExpr, false, false));
            static::assertEquals([], $column->validateValue($dbExpr, false, true));
            static::assertEquals($expectedErrors, $column->validateValue($dbExpr, true, false));
            // SelectQueryBuilderInterface
            if (!$column->isPrimaryKey()) {
                $expectedErrors = [
                    'Value received from DB cannot be instance of SelectQueryBuilderInterface.',
                ];
                $select = new OrmSelect(TestingAdminsTable::getInstance());
                static::assertEquals([], $column->validateValue($select, false, false));
                static::assertEquals([], $column->validateValue($select, false, true));
                static::assertEquals($expectedErrors, $column->validateValue($select, true, false));
            }
        }
    }

    private function getColumnNameFromClass(string $class): string
    {
        return StringUtils::toSnakeCase(preg_replace('%^.*\\\(.*)$%', '$1', $class));
    }

    private function isDefaultValueAllowedForColumn(TableColumnInterface $column): bool
    {
        return (
            !($column instanceof BlobColumn)
            && !($column instanceof PasswordColumn)
            && !($column instanceof EmailColumn)
            && !$column->isPrimaryKey()
        );
    }

    public function testPrimaryKeyColumnException1(): void
    {
        $column = new IdColumn('pk');
        static::assertTrue($column->isPrimaryKey());
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Primary key column .*'pk'.* is not allowed to have default value%"
        );
        $column->setDefaultValue('test');
    }

    public function testNullValueForNotNullColumn(): void
    {
        $columns = [
            BlobColumn::class,
            BooleanColumn::class,
            DateColumn::class,
            EmailColumn::class,
            FloatColumn::class,
            // IdColumn is nullable by default
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
            UnixTimestampColumn::class,
        ];
        /** @var RealTableColumnAbstract $class */
        $record = new TestingAdmin();
        foreach ($columns as $class) {
            $columnName = $this->getColumnNameFromClass($class);
            // value is not from DB
            try {
                $column = new $class($columnName);
                $valueContainer = $column->getNewRecordValueContainer($record);
                static::assertFalse($column->isNullableValues(), $class);
                $column->setValue($valueContainer, null, false, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    "Validation errors: [{$columnName}] Null value is not allowed.",
                    $exception->getMessage(),
                    $class
                );
            }
            // value is from DB (not trusted)
            try {
                $column = new $class($columnName);
                // set default value to make use it will not be used instead of null
                if ($this->isDefaultValueAllowedForColumn($column)) {
                    $column->setDefaultValue(new DbExpr('test'));
                }
                static::assertFalse($column->isNullableValues(), $class);
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, null, true, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    "Validation errors: [{$columnName}] Null value is not allowed.",
                    $exception->getMessage(),
                    $class
                );
            }
            // value is from DB (trusted)
            $column = new $class($columnName);
            // set default value to make sure it will not be used instead of null
            if ($this->isDefaultValueAllowedForColumn($column)) {
                $column->setDefaultValue(new DbExpr('test'));
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, null, true, true);
                static::assertNull($column->getValue($valueContainer, null));
            }
        }
    }

    public function testObjectsAsValueFromDb(): void
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
            UnixTimestampColumn::class,
        ];
        /** @var RealTableColumnAbstract $columnClass */
        $record = new TestingAdmin();
        $dbExpr = new DbExpr('test');
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        foreach ($columns as $columnClass) {
            // DbExpr - not trusted
            try {
                $column = new $columnClass('column_name');
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, $dbExpr, true, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Value received from DB cannot be instance of DbExpr.',
                    $exception->getMessage(),
                    $columnClass
                );
            }
            // DbExpr - trusted
            try {
                $column = new $columnClass('column_name');
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, $dbExpr, true, true);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Value received from DB cannot be instance of DbExpr.',
                    $exception->getMessage(),
                    $columnClass
                );
            }
            // SelectQueryBuilderInterface - not trusted
            try {
                $column = new $columnClass('column_name');
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, $select, true, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Value received from DB cannot be instance of SelectQueryBuilderInterface.',
                    $exception->getMessage(),
                    $columnClass
                );
            } catch (\UnexpectedValueException $exception) {
                static::assertRegExp(
                    '%Value for primary key column .* can be an integer or instance of .*DbExpr%',
                    $exception->getMessage(),
                    $columnClass
                );
            }
            // SelectQueryBuilderInterface - trusted
            try {
                $column = new $columnClass('column_name');
                $valueContainer = $column->getNewRecordValueContainer($record);
                $column->setValue($valueContainer, $select, true, true);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Value received from DB cannot be instance of SelectQueryBuilderInterface.',
                    $exception->getMessage(),
                    $columnClass
                );
            }
        }
    }

    public function testPrimaryKeyColumnDefaultValueSetterException(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Primary key column.*'id'.* is not allowed to have default value.%"
        );
        $column = new IdColumn('id');
        static::assertTrue($column->isPrimaryKey());
        $column->setDefaultValue('qqq');
    }

    public function testInvalidValidateValueArgsCombination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Arguments $isFromDb and $isForCondition cannot both have true value.'
        );
        $column = new StringColumn('id');
        $column->validateValue('test', true, true);
    }
}