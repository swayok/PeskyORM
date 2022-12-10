<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\TableStructure\TableColumn\Column\EmailColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class StringTextEmailTableColumnsTest extends BaseTestCase
{
    public function testStringColumn(): void
    {
        $this->testCommonProperties(new StringColumn('text'), TableColumnDataType::STRING);
        $this->testDefaultValues(new StringColumn('text'), ' VaLue ');
        $this->testNonDbValues(new StringColumn('text'), ' VaLue ');
        $this->testDbValues(new StringColumn('text'), ' VaLue ');
        $this->testValidateValue(new TextColumn('text'), ' VaLue ');
    }

    public function testTextColumn(): void
    {
        $this->testCommonProperties(new TextColumn('text'), TableColumnDataType::TEXT);
        $this->testDefaultValues(new TextColumn('text'), ' VaLue ');
        $this->testNonDbValues(new TextColumn('text'), ' VaLue ');
        $this->testDbValues(new TextColumn('text'), ' VaLue ');
        $this->testValidateValue(new TextColumn('text'), ' VaLue ');
    }

    public function testEmailColumn(): void
    {
        $testValue = ' Test@tEst.com ';
        $normalizedValue = 'test@test.com';
        // $normalizedValue is correct!
        $this->testCommonProperties(new EmailColumn('email'), TableColumnDataType::STRING);
        $this->testDefaultValues(new EmailColumn('email'), $normalizedValue);
        $this->testNonDbValues(new EmailColumn('email'), $normalizedValue, false);
        $this->testDbValues(new EmailColumn('email'), $normalizedValue);
        $this->testValidateValue(new TextColumn('text'), $normalizedValue, false);
        // test lowercase and trim (value not from DB)
        $column = new EmailColumn('email');
        $valueContainer = $column->getNewRecordValueContainer(new TestingAdmin());
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertNotEquals($testValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // test lowercase and trim (value from DB and not trusted)
        $valueContainer = $column->getNewRecordValueContainer(new TestingAdmin());
        $column->setValue($valueContainer, $testValue, true, false);
        static::assertNotEquals($testValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // test lowercase and trim (value from DB and trusted)
        $valueContainer = $column->getNewRecordValueContainer(new TestingAdmin());
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($testValue, $valueContainer->getValue());
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
    }

    protected function testCommonProperties(
        StringColumn $column,
        string $type,
    ): void {
        static::assertEquals($type, $column->getDataType());
        static::assertEquals([], $column->getColumnNameAliases());
        // has value
        $valueContainer = $column->getNewRecordValueContainer(new TestingAdmin());
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    protected function testDefaultValues(
        TableColumnInterface $column,
        string $testValue
    ): void {
        /** @var StringColumn $columnClass */
        $columnClass = $column::class;
        $normalizedValue = mb_strtolower(trim($testValue));
        $record = new TestingAdmin();
        // default value
        $column = new $columnClass($column->getName());
        $column->setDefaultValue($testValue);
        $valueContainer = $column->getNewRecordValueContainer($record);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertTrue($column->hasValue($valueContainer, true));
        static::assertEquals($testValue, $column->getDefaultValue());
        static::assertEquals($testValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        $valueContainer = $column->getNewRecordValueContainer($record);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // default value with value modifiers
        $column = new $columnClass($column->getName());
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue($testValue);
        $valueContainer = $column->getNewRecordValueContainer($record);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        $valueContainer = $column->getNewRecordValueContainer($record);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // default value as closure
        $column = new $columnClass($column->getName());
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue(function () use ($testValue) {
                return $testValue;
            });
        $valueContainer = $column->getNewRecordValueContainer($record);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertTrue($column->hasValue($valueContainer, true));
        static::assertEquals($normalizedValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
    }

    protected function testNonDbValues(
        StringColumn $column,
        string $testValue,
        bool $allowsEmptyStringsByDefault = true
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        $record = new TestingAdmin();
        // setter & getter
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue());
        static::assertEquals($testValue, $valueContainer->getValue());
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // + trim
        $column->trimsValues();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertEquals(trim($testValue), $valueContainer->getValue());
        static::assertEquals(trim($testValue), $column->getValue($valueContainer, null));
        // + lowercase
        $column->lowercasesValues();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // empty string
        if ($allowsEmptyStringsByDefault) {
            $valueContainer = $column->getNewRecordValueContainer($record);
            $column->setValue($valueContainer, '', false, false);
            static::assertEquals('', $valueContainer->getValue());
            static::assertEquals('', $column->getValue($valueContainer, null));
        }
        // null
        $column->allowsNullValues();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($valueContainer->getValue());
        static::assertNull($column->getValue($valueContainer, null));
        // empty string to null
        $column->convertsEmptyStringValuesToNull();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, '', false, false);
        static::assertNull($valueContainer->getValue());
        static::assertNull($column->getValue($valueContainer, null));
        // DbExpr
        $valueContainer = $column->getNewRecordValueContainer($record);
        $dbExpr = new DbExpr('test');
        $column->setValue($valueContainer, $dbExpr, false, false);
        static::assertEquals($dbExpr, $column->getValue($valueContainer, null));
        // SelectQueryBuilderInterface
        $valueContainer = $column->getNewRecordValueContainer($record);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, false, false);
        static::assertEquals($select, $column->getValue($valueContainer, null));
    }

    protected function testDbValues(
        StringColumn $column,
        string $testValue
    ): void {
        /** @var StringColumn $columnClass */
        $columnClass = $column::class;
        $normalizedValue = mb_strtolower(trim($testValue));
        $record = new TestingAdmin();
        // not trusted DB value
        $column = new $columnClass($column->getName());
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue('default');
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, $testValue, true, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // trusted DB value
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    protected function testValidateValue(
        StringColumn $column,
        string $testValue,
        bool $allowsEmptyStringsByDefault = true
    ): void {
        /** @var StringColumn $columnClass */
        $columnClass = $column::class;
        $column = new $columnClass($column->getName());
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false));
        static::assertEquals([], $column->validateValue($testValue, false, true));
        static::assertEquals([], $column->validateValue($testValue, true, true));
        // empty string
        if ($allowsEmptyStringsByDefault) {
            static::assertEquals([], $column->validateValue('', false, false));
            static::assertEquals([], $column->validateValue('', false, true));
            static::assertEquals([], $column->validateValue('', true, true));
        }
        // null
        $expectedErrors = [
            'Null value is not allowed.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals([], $column->validateValue(null, true, true));
        // random object
        $expectedErrors = [
            'Value must be a string.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($this, true, true));
        // DbExpr
        $dbExpr = new DbExpr('test');
        static::assertEquals([], $column->validateValue($dbExpr, false, false));
        static::assertEquals([], $column->validateValue($dbExpr, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($dbExpr, true, true));
        // SelectQueryBuilderInterface
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        static::assertEquals([], $column->validateValue($select, false, false));
        static::assertEquals([], $column->validateValue($select, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($select, true, true));
    }

    public function testEmptyStringConverstionToNullWhenColumnNotNullable(): void
    {
        $columns = [
            EmailColumn::class,
            StringColumn::class,
            TextColumn::class,
        ];
        foreach ($columns as $columnClass) {
            // non-db value
            try {
                /** @var StringColumn $column */
                $column = new $columnClass('column_name');
                $column->convertsEmptyStringValuesToNull();
                $record = new TestingAdmin();
                $valueContainer = $column->getNewRecordValueContainer($record);
                static::assertFalse($column->isNullableValues(), $columnClass);
                $column->setValue($valueContainer, '', false, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Null value is not allowed.',
                    $exception->getMessage(),
                    $columnClass
                );
            }
            // db value
            try {
                /** @var StringColumn $column */
                $column = new $columnClass('column_name');
                $column
                    ->convertsEmptyStringValuesToNull()
                    ->setDefaultValue('test@test.com'); //< should not be used
                $record = new TestingAdmin();
                $valueContainer = $column->getNewRecordValueContainer($record);
                static::assertFalse($column->isNullableValues(), $columnClass);
                $column->setValue($valueContainer, '', true, false);
                static::fail('Exception should be thrown');
            } catch (InvalidDataException $exception) {
                static::assertEquals(
                    'Validation errors: [column_name] Null value is not allowed.',
                    $exception->getMessage(),
                    $columnClass
                );
            }
        }
    }

    public function testEmailColumnInvalidNonDbValue(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Value must be an email.'
        );
        $column = new EmailColumn('email');
        $record = new TestingAdmin();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, 'qqq', false, false);
    }

    public function testEmailColumnInvalidDbValue1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Value must be an email.'
        );
        $column = new EmailColumn('email');
        $record = new TestingAdmin();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, 'qqq', true, false);
    }

    public function testEmailColumnInvalidDbValue2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Null value is not allowed.'
        );
        $column = new EmailColumn('email');
        $record = new TestingAdmin();
        $valueContainer = $column->getNewRecordValueContainer($record);
        $column->setValue($valueContainer, '', true, false);
    }

    public function testEmailColumnInvalidDefaultValue(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column.*'email'.* is not valid\. Errors: Value must be an email\.%"
        );
        $column = new EmailColumn('email');
        $column->setDefaultValue('qqq');
        static::assertEquals('qqq', $column->getDefaultValue());
        $column->getValidDefaultValue();
    }

    public function testIdColumnDefaultValueSetterException(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Primary key column.*'id'.* is not allowed to have default value.%"
        );
        $column = new IdColumn('id');
        $column->setDefaultValue('qqq');
    }
}