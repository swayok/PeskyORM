<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\FloatColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class FloatColumnTest extends BaseTestCase
{
    public function testFloatColumn(): void
    {
        $column = new FloatColumn('float');
        static::assertEquals(TableColumnDataType::FLOAT, $column->getDataType());
        static::assertEquals([], $column->getValueFormatersNames());
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
                'test' => 1,
                'expected' => 1.0
            ],
            [
                'test' => 0,
                'expected' => 0.0
            ],
            [
                'test' => 100000,
                'expected' => 100000.0
            ],
            [
                'test' => -100000,
                'expected' => -100000.0
            ],
            [
                'test' => 1.1,
                'expected' => 1.1
            ],
            [
                'test' => 0.1,
                'expected' => 0.1
            ],
            [
                'test' => -100000.1111,
                'expected' => -100000.1111
            ],
            [
                'test' => '1',
                'expected' => 1.0
            ],
            [
                'test' => '0',
                'expected' => 0.0
            ],
            [
                'test' => '1.1',
                'expected' => 1.1
            ],
            [
                'test' => '0.1',
                'expected' => 0.1
            ],
            [
                'test' => '100000',
                'expected' => 100000
            ],
            [
                'test' => '-100000',
                'expected' => -100000
            ],
            [
                'test' => '-100000.1111',
                'expected' => -100000.1111
            ],
        ];
    }

    private function testDefaultValues(
        FloatColumn $column,
        string|int|float $testValue,
        float $normalizedValue
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
        FloatColumn $column,
        string|int|float $testValue,
        float $normalizedValue
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
        FloatColumn $column,
        string|int|float $testValue,
        float $normalizedValue
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
        FloatColumn $column,
        string|int|float $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(FloatColumn $column): void {
        // empty string
        $expectedErrors = [
            'Numeric value expected.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals($expectedErrors, $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, false));
        // null
        $expectedErrors = [
            'Null value is not allowed.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            'Numeric value expected.'
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

    private function newColumn(FloatColumn $column): FloatColumn {
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

    public function testEmptyStringValueExceptionForFloatColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForFloatColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForFloatColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForFloatColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForFloatColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForFloatColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Numeric value expected.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForFloatColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [float] Null value is not allowed.'
        );
        $column = new FloatColumn('float');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }
}