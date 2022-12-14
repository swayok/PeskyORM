<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BlobColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class BlobColumnTest extends BaseTestCase
{
    public function testBlobColumn(): void
    {
        $column = new BlobColumn('blob');
        static::assertEquals(TableColumnDataType::BLOB, $column->getDataType());
        static::assertEquals([], $column->getColumnNameAliases());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        $this->testValidateValueCommon($column);
        $this->testDefaultValues($column);

        // string
        $testValue = $expectedValue = ' string CONTENTS ';
        static::assertEquals([], $column->validateValue($testValue, false, false));
        static::assertEquals([], $column->validateValue($testValue, false, true));
        $this->testNonDbValues($column, $testValue, $expectedValue);
        // resource
        $expectedValue = file_get_contents(__FILE__);
        $testValue = fopen(__FILE__, 'rb');
        static::assertEquals([], $column->validateValue($testValue, false, false));
        static::assertEquals([], $column->validateValue($testValue, false, true));
        static::assertEquals([], $column->validateValue($testValue, true, false));
        $this->testNonDbValues($column, $testValue, $expectedValue);
        $this->testDbValues($column, $testValue);
    }

    private function testDefaultValues(
        BlobColumn $column,
    ): void {
        // default value
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        static::assertFalse($column->hasDefaultValue());
    }

    private function testNonDbValues(
        BlobColumn $column,
        mixed $testValue,
        string|int $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        $column = $this->newColumn($column);
        // setter & getter
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue(), $message);
        static::assertEquals($normalizedValue, $valueContainer->getValue(), $message);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
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
        BlobColumn $column,
        mixed $testValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        // not trusted DB value
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, false);

        static::assertEquals($testValue, $column->getValue($valueContainer, null), $message);
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($testValue, $column->getValue($valueContainer, null), $message);
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateValueCommon(BlobColumn $column): void {
        // empty string
        $expectedErrors = [
            'Null value is not allowed.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals([], $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, false));
        // null
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));

        $expectedErrors = [
            'String or resource value expected.'
        ];
        $expectedErrorsIfFromDb = [
            'Resource value expected.'
        ];
        // random object
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue($this, true, false));
        // bool
        static::assertEquals($expectedErrors, $column->validateValue(true, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(true, false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue(true, true, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue(false, true, false));
        // int
        static::assertEquals($expectedErrors, $column->validateValue(1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(1, false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue(1, true, false));
        // float
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue(1.1, true, false));
        // array
        static::assertEquals($expectedErrors, $column->validateValue([], false, false));
        static::assertEquals($expectedErrors, $column->validateValue([], false, true));
        static::assertEquals($expectedErrorsIfFromDb, $column->validateValue([], true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(BlobColumn $column): BlobColumn {
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

    public function testSetDefaultValueExceptionForBlobColumn(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*?'blob'.* is not allowed to have default value%"
        );
        // empty string
        $column = new BlobColumn('blob');
        $column->setDefaultValue('qqq');
    }

    public function testInvalidValueExceptionForBlobColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [blob] String or resource value expected.'
        );
        // empty string
        $column = new BlobColumn('blob');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForBlobColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [blob] String or resource value expected.'
        );
        // empty string
        $column = new BlobColumn('blob');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForBlobColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [blob] String or resource value expected.'
        );
        // empty string
        $column = new BlobColumn('blob');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 100, false, false);
    }

    public function testInvalidValueExceptionForBlobColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [blob] Resource value expected.'
        );
        // empty string
        $column = new BlobColumn('blob');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'string', true, false);
    }
}