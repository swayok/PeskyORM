<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\EmailColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class StringTextEmailTableColumnsTest extends BaseTestCase
{
    public function testStringColumn(): void
    {
        $column = new StringColumn('string');
        $testValue = ' VaLue ';
        $this->testCommonProperties($column, TableColumnDataType::STRING);
        $this->testDefaultValues($column, $testValue);
        $this->testNonDbValues($column, $testValue);
        $this->testDbValues($column, $testValue);
        $this->testValidateValue($column, $testValue);
    }

    public function testTextColumn(): void
    {
        $column = new TextColumn('text');
        $testValue = ' VaLue ';
        $this->testCommonProperties($column, TableColumnDataType::TEXT);
        $this->testDefaultValues($column, $testValue);
        $this->testNonDbValues($column, $testValue);
        $this->testDbValues($column, $testValue);
        $this->testValidateValue($column, $testValue);
    }

    public function testEmailColumn(): void
    {
        $testValue = ' Test@tEst.com ';
        $normalizedValue = 'test@test.com';
        // $normalizedValue is correct!
        $column = new EmailColumn('email');
        $this->testCommonProperties($column, TableColumnDataType::STRING);
        $this->testDefaultValues($column, $normalizedValue);
        $this->testNonDbValues($column, $normalizedValue, false);
        $this->testDbValues($column, $normalizedValue);
        $this->testValidateValue($column, $normalizedValue, false);
        // test lowercase and trim (value not from DB)
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertNotEquals($testValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // test lowercase and trim (value from DB and not trusted)
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, false);
        static::assertNotEquals($testValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // test lowercase and trim (value from DB and trusted)
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($testValue, $valueContainer->getValue());
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
    }

    private function testCommonProperties(
        StringColumn $column,
        string $type,
    ): void {
        $column = $this->newColumn($column);
        static::assertEquals($type, $column->getDataType());
        static::assertEquals([], $column->getColumnNameAliases());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    private function testDefaultValues(
        StringColumn $column,
        string $testValue
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        // default value
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertTrue($column->hasValue($valueContainer, true));
        static::assertEquals($testValue, $column->getDefaultValue());
        static::assertEquals($testValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // default value with value modifiers
        $column = $this->newColumn($column);
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // default value as closure
        $column = $this->newColumn($column);
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue(function () use ($testValue) {
                return $testValue;
            });
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertTrue($column->hasValue($valueContainer, true));
        static::assertEquals($normalizedValue, $column->getValidDefaultValue());
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
    }

    private function testNonDbValues(
        StringColumn $column,
        string $testValue,
        bool $allowsEmptyStringsByDefault = true
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        // setter & getter
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue());
        static::assertEquals($testValue, $valueContainer->getValue());
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // + trim
        $column->trimsValues();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertEquals(trim($testValue), $valueContainer->getValue());
        static::assertEquals(trim($testValue), $column->getValue($valueContainer, null));
        // + lowercase
        $column->lowercasesValues();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // empty string
        if ($allowsEmptyStringsByDefault) {
            $valueContainer = $this->newRecordValueContainer($column);
            $column->setValue($valueContainer, '', false, false);
            static::assertEquals('', $valueContainer->getValue());
            static::assertEquals('', $column->getValue($valueContainer, null));
        }
        // null
        $column->allowsNullValues();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($valueContainer->getValue());
        static::assertNull($column->getValue($valueContainer, null));
        // empty string to null
        $column->convertsEmptyStringValuesToNull();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
        static::assertNull($valueContainer->getValue());
        static::assertNull($column->getValue($valueContainer, null));
        // DbExpr
        $valueContainer = $this->newRecordValueContainer($column);
        $dbExpr = new DbExpr('test');
        $column->setValue($valueContainer, $dbExpr, false, false);
        static::assertEquals($dbExpr, $column->getValue($valueContainer, null));
        // SelectQueryBuilderInterface
        $valueContainer = $this->newRecordValueContainer($column);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, false, false);
        static::assertEquals($select, $column->getValue($valueContainer, null));
    }

    private function testDbValues(
        StringColumn $column,
        string $testValue
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        // not trusted DB value
        $column = $this->newColumn($column);
        $column
            ->trimsValues()
            ->lowercasesValues()
            ->setDefaultValue('default');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($testValue, $column->getValue($valueContainer, null));
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateValue(
        StringColumn $column,
        string $testValue,
        bool $allowsEmptyStringsByDefault = true
    ): void {
        $column = $this->newColumn($column);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false));
        static::assertEquals([], $column->validateValue($testValue, false, true));
        static::assertEquals([], $column->validateValue($testValue, true, false));
        // empty string
        if ($allowsEmptyStringsByDefault) {
            static::assertEquals([], $column->validateValue('', false, false));
            static::assertEquals([], $column->validateValue('', false, true));
            static::assertEquals([], $column->validateValue('', true, false));
        }
        // null
        $expectedErrors = [
            'Null value is not allowed.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            $column instanceof EmailColumn
                ? 'Value must be an email.'
                : 'String value expected.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($this, true, false));
        // bool
        static::assertEquals($expectedErrors, $column->validateValue(true, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(true, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(true, true, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(false, true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(StringColumn $column): StringColumn
    {
        $class = $column::class;
        /** @var StringColumn $ret */
        $ret = new $class($column->getName());
        return $ret;
    }

    private function newRecordValueContainer(StringColumn $column): RecordValueContainerInterface
    {
        return $column->getNewRecordValueContainer(new TestingAdmin());
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
                $valueContainer = $this->newRecordValueContainer($column);
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
                $valueContainer = $this->newRecordValueContainer($column);
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
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qqq', false, false);
    }

    public function testEmailColumnInvalidDbValue1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Value must be an email.'
        );
        $column = new EmailColumn('email');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qqq', true, false);
    }

    public function testEmailColumnInvalidDbValue2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Null value is not allowed.'
        );
        $column = new EmailColumn('email');
        $valueContainer = $this->newRecordValueContainer($column);
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
}