<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class BooleanColumnTest extends BaseTestCase
{
    public function testBooleanColumn(): void
    {
        $column = new BooleanColumn('bool');
        static::assertEquals(TableColumnDataType::BOOL, $column->getDataType());
        static::assertEquals([], $column->getColumnNameAliases());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        $this->testValidateValueCommon($column);

        foreach ($this->getValuesForTesting() as $values) {
            $this->testValidateGoodValue($column, $values['test']);
            $this->testDefaultValues($column, $values['test'], $values['expected']);
            $this->testNonDbValues($column, $values['test'], $values['expected']);
            $this->testDbValues($column, $values['test'], $values['expected']);
        }
    }

    private function getValuesForTesting(): array
    {
        return [
            [
                'test' => true,
                'expected' => true
            ],
            [
                'test' => false,
                'expected' => false
            ],
            [
                'test' => 1,
                'expected' => true
            ],
            [
                'test' => 0,
                'expected' => false
            ],
            [
                'test' => '1',
                'expected' => true
            ],
            [
                'test' => '0',
                'expected' => false
            ]
        ];
    }

    private function testDefaultValues(
        BooleanColumn $column,
        string|int|bool $testValue,
        bool $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        // default value
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        static::assertEquals($testValue, $column->getDefaultValue(), $message);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        // default value as closure
        $column = $this->newColumn($column);
        $column->setDefaultValue(function () use ($testValue) {
            return $testValue;
        });
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
    }

    private function testNonDbValues(
        BooleanColumn $column,
        string|int|bool $testValue,
        bool $normalizedValue
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
        BooleanColumn $column,
        string|int|bool $testValue,
        bool $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        // not trusted DB value
        $column = $this->newColumn($column);
        $column->setDefaultValue('default');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, false);

        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, true);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateGoodValue(
        BooleanColumn $column,
        string|int|bool $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(BooleanColumn $column): void {
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
        // random object
        $expectedErrors = [
            'Boolean value expected.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($this, true, false));
        // int != 1 and 0
        static::assertEquals($expectedErrors, $column->validateValue(2, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(2, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(2, true, false));
        // float
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(BooleanColumn $column): BooleanColumn {
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

    public function testEmptyStringValueExceptionForBooleanColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Null value is not allowed.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Boolean value expected.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Boolean value expected.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Boolean value expected.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'false', false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Boolean value expected.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 59, false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Boolean value expected.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForBooleanColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [bool] Null value is not allowed.'
        );
        $column = new BooleanColumn('bool');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }
}