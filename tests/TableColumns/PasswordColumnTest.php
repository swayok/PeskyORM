<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\PasswordColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class PasswordColumnTest extends BaseTestCase
{
    public function testPasswordColumn(): void
    {
        $column = new PasswordColumn('password');
        static::assertEquals(TableColumnDataType::STRING, $column->getDataType());
        static::assertEquals([], $column->getValueFormatersNames());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        $this->testValidateValueCommon($column);
        $this->testValidateGoodValues($column);
        $this->testDefaultValues($column);

        $testValue = 'te0546&*#$(st';
        $this->testNonDbValues($column, $testValue);
        $this->testDbValues($column, $testValue);
    }

    private function testDefaultValues(PasswordColumn $column): void {
        // default values are not allowed
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasDefaultValue());
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    private function testNonDbValues(
        PasswordColumn $column,
        string $testValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        $column = $this->newColumn($column);
        // setter & getter
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue(), $message);
        static::assertTrue(
            $column->verifyPassword($testValue, $valueContainer->getValue()),
            $message
        );
        static::assertEquals(
            $column->verifyPassword($testValue, $valueContainer->getValue()),
            $message
        );
        // null
        $column->allowsNullValues();
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($valueContainer->getValue(), $message);
        static::assertNull($column->getValue($valueContainer, null), $message);
        // DbExpr
        $valueContainer = $this->newRecordValueContainer($column);
        $dbExpr = new DbExpr('test');
        $column->setValue($valueContainer, $dbExpr, false, false);
        static::assertEquals($dbExpr, $column->getValue($valueContainer, null), $message);
        // SelectQueryBuilderInterface
        $valueContainer = $this->newRecordValueContainer($column);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, false, false);
        static::assertEquals($select, $column->getValue($valueContainer, null), $message);
    }

    private function testDbValues(
        PasswordColumn $column,
        string $testValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        $column = $this->newColumn($column);
        $hashedValue = $column->hashPassword($testValue);
        // not trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $hashedValue, true, false);
        static::assertTrue(
            $column->verifyPassword($testValue, $column->getValue($valueContainer, null)),
            $message
        );
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $hashedValue, true, true);
        static::assertTrue(
            $column->verifyPassword($testValue, $column->getValue($valueContainer, null)),
            $message
        );
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateGoodValues(
        PasswordColumn $column
    ): void {
        $column = $this->newColumn($column);
        // good new value
        $testValue = 'te0546&*#$(st';
        $message = $this->getAssertMessageForValue($testValue);
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        // good value form db
        $testValue = $column->hashPassword($testValue);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(PasswordColumn $column): void {
        // empty string
        $expectedNullErrors = [
            'Null value is not allowed.'
        ];
        $expectedPasswordHashErrors = [
            'Value must be a password hash.'
        ];
        static::assertEquals($expectedNullErrors, $column->validateValue('', false, false));
        static::assertEquals([], $column->validateValue('', false, true));
        static::assertEquals($expectedPasswordHashErrors, $column->validateValue('', true, false));
        // null
        static::assertEquals($expectedNullErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedNullErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            'String value expected.'
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
        // int
        static::assertEquals($expectedErrors, $column->validateValue(100, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(100, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(100, true, false));
        // float
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, true, false));
        // array
        static::assertEquals($expectedErrors, $column->validateValue([], false, false));
        static::assertEquals($expectedErrors, $column->validateValue([], false, true));
        static::assertEquals($expectedErrors, $column->validateValue([], true, false));
        // plain password
        static::assertEquals([], $column->validateValue('qqqq', false, false));
        static::assertEquals([], $column->validateValue('qqqq', false, true));
        static::assertEquals($expectedPasswordHashErrors, $column->validateValue('qqqq', true, false));
        // hashed password
        $expectedErrors = [
            'Value must be a plain password (password hash detected).'
        ];
        $hash = $column->hashPassword('qqqq');
        static::assertEquals($expectedErrors, $column->validateValue($hash, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($hash, false, true));
        static::assertEquals([], $column->validateValue($hash, true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(PasswordColumn $column): PasswordColumn {
        $class = $column::class;
        return new $class($column->getName());
    }

    private function newRecordValueContainer(
        RealTableColumnAbstract $column
    ): RecordValueContainerInterface {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    private function getAssertMessageForValue(mixed $testValue): string {
        return (string)$testValue;
    }

    public function testEmptyStringValueExceptionForPasswordColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] Null value is not allowed.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] String value expected.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] String value expected.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] String value expected.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] Null value is not allowed.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] String value expected.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, false, false);
    }

    public function testInvalidValueExceptionForPasswordColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [password] String value expected.'
        );
        $column = new PasswordColumn('password');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, true, false);
    }

    public function testSetDefaultValueExceptionForPasswordColumn(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'password'.* is not allowed to have default value%"
        );
        $column = new PasswordColumn('password');
        $column->setDefaultValue('111');
    }
}