<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\EmailColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class EmailColumnTest extends BaseTestCase
{
    public function testEmailColumn(): void
    {
        $testValue = ' Test@tEst.com ';
        $column = new EmailColumn('email');
        $this->testCommonProperties($column, TableColumnDataType::STRING);
        $this->testDefaultValues($column);
        $this->testNonDbValues($column, $testValue);
        $this->testDbValues($column, $testValue);
        $this->testValidateValue($column, $testValue);
    }

    private function testCommonProperties(
        EmailColumn $column,
        string $type,
    ): void {
        $column = $this->newColumn($column);
        static::assertEquals($type, $column->getDataType());
        static::assertEquals([], $column->getValueFormatersNames());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    private function testDefaultValues(EmailColumn $column): void {
        // default values are not allowed
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasDefaultValue());
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    private function testNonDbValues(
        EmailColumn $column,
        string $testValue,
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        // setter & getter
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue());
        static::assertEquals($normalizedValue, $valueContainer->getValue());
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null));
        // null
        $column->allowsNullValues();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($valueContainer->getValue());
        static::assertNull($column->getValue($valueContainer, null));
        // empty string to null
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
        EmailColumn $column,
        string $testValue
    ): void {
        $normalizedValue = mb_strtolower(trim($testValue));
        // not trusted DB value
        $column = $this->newColumn($column);
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
        EmailColumn $column,
        string $testValue,
    ): void {
        $column = $this->newColumn($column);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false));
        static::assertEquals([], $column->validateValue($testValue, false, true));
        static::assertEquals([], $column->validateValue($testValue, true, false));
        // null
        $expectedErrors = [
            'Null value is not allowed.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            'Value must be an email.',
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
        // array
        static::assertEquals($expectedErrors, $column->validateValue([], false, false));
        static::assertEquals($expectedErrors, $column->validateValue([], false, true));
        static::assertEquals($expectedErrors, $column->validateValue([], true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(EmailColumn $column): EmailColumn
    {
        $class = $column::class;
        return new $class($column->getName());
    }

    private function newRecordValueContainer(EmailColumn $column): RecordValueContainerInterface
    {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    public function testEmptyStringConverstionToNullForEmailColumn1(): void
    {
        // non-db value
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Null value is not allowed.',
        );
        $column = new EmailColumn('email');
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->isNullableValues());
        $column->setValue($valueContainer, '', false, false);
    }

    public function testEmptyStringConverstionToNullForEmailColumn2(): void
    {
        // db value (not trusted)
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [email] Null value is not allowed.',
        );
        $column = new EmailColumn('email');
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->isNullableValues());
        $column->setValue($valueContainer, '', true, false);
    }

    public function testEmptyStringConverstionToNullForEmailColumn3(): void
    {
        // db value (trusted)
        $column = new EmailColumn('email');
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->isNullableValues());
        $column->setValue($valueContainer, '', true, true);
        static::assertEquals('', $column->getValue($valueContainer, null));
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
            "%Column .*'email'.* is not allowed to have default value%"
        );
        $column = new EmailColumn('email');
        $column->setDefaultValue('qqq');
    }
}