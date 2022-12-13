<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use Carbon\CarbonTimeZone;
use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimezoneOffsetColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class TimeZoneOffsetColumnTest extends BaseTestCase
{
    public function testTimezoneOffsetColumn(): void
    {
        $columnStr = new TimezoneOffsetColumn('tz_offset');
        static::assertEquals(TableColumnDataType::TIMEZONE_OFFSET, $columnStr->getDataType());
        static::assertEquals(
            [
                $columnStr->getName() . '_as_carbon' => 'carbon'
            ],
            $columnStr->getColumnNameAliases()
        );
        // has value
        $valueContainer = $this->newRecordValueContainer($columnStr);
        static::assertFalse($columnStr->hasValue($valueContainer, false));
        static::assertFalse($columnStr->hasValue($valueContainer, true));
        $this->testValidateValueCommon($columnStr);

        $columnInt = new TimezoneOffsetColumn('tz_offset_int');
        $columnInt->convertsStringOffsetToInteger();

        foreach ($this->getValuesForTesting() as $values) {
            $this->testValidateGoodValue($columnStr, $values['test']);
            $this->testDefaultValues($columnStr, $values['test'], $values['expected']);
            $this->testNonDbValues($columnStr, $values['test'], $values['expected']);
            $this->testDbValues($columnStr, $values['test'], $values['expected']);
            // convert expected value to int
            preg_match('%^([-+])(\d\d):(\d\d)$%', $values['expected'], $matches);
            [, $sign, $hours, $minutes] = $matches;
            $validatedValue = (int)$hours * 3600 + (int)$minutes * 60;
            $values['expected'] =  $sign === '-' ? -$validatedValue : $validatedValue;
            $this->testValidateGoodValue($columnInt, $values['test']);
            $this->testDefaultValues($columnInt, $values['test'], $values['expected']);
            $this->testNonDbValues($columnInt, $values['test'], $values['expected']);
            $this->testDbValues($columnInt, $values['test'], $values['expected']);
        }

    }

    public function testTimezoneOffsetColumnFormatters(): void
    {
        $value = '+12:00';
        // string offset column
        $column = new TimezoneOffsetColumn('tz_offset');
        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            $value,
            false,
            false
        );
        $carbon = $column->getValue($container, 'carbon');
        static::assertInstanceOf(CarbonTimeZone::class, $carbon);
        static::assertEquals($value, $carbon->toOffsetName());
        // int offset column
        $column = new TimezoneOffsetColumn('tz_offset_int');
        $column->convertsStringOffsetToInteger();
        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            $value,
            false,
            false
        );
        $carbon = $column->getValue($container, 'carbon');
        static::assertInstanceOf(CarbonTimeZone::class, $carbon);
        static::assertEquals($value, $carbon->toOffsetName());
    }

    private function getValuesForTesting(): array
    {
        date_default_timezone_set('America/Los_Angeles');
        return [
            [
                'test' => '+00:00',
                'expected' => '+00:00'
            ],
            [
                'test' => '+01:00',
                'expected' => '+01:00'
            ],
            [
                'test' => '+12:00',
                'expected' => '+12:00'
            ],
            [
                'test' => '+14:00',
                'expected' => '+14:00'
            ],
            [
                'test' => '-12:00',
                'expected' => '-12:00'
            ],
            [
                'test' => '-01:00',
                'expected' => '-01:00'
            ],
            [
                'test' => 'Europe/Amsterdam',
                'expected' => '+01:00'
            ],
            [
                'test' => 'UTC',
                'expected' => '+00:00'
            ],
            [
                'test' => new \DateTimeZone('Europe/Amsterdam'),
                'expected' => '+01:00'
            ],
            [
                'test' => new \DateTimeZone('UTC'),
                'expected' => '+00:00'
            ],
            [
                'test' => new \DateTimeZone('-12:00'),
                'expected' => '-12:00'
            ],
            [
                'test' => new \DateTimeZone('+14:00'),
                'expected' => '+14:00'
            ],
            [
                'test' => new \DateTimeZone('GMT+04:45'),
                'expected' => '+04:45'
            ],
            [
                'test' => new CarbonTimeZone(),
                'expected' => '-08:00'
            ],
            [
                'test' => new CarbonTimeZone('Europe/Amsterdam'),
                'expected' => '+01:00'
            ],
            [
                'test' => new CarbonTimeZone('CEST'),
                'expected' => '+02:00'
            ],
            [
                'test' => new CarbonTimeZone('UTC'),
                'expected' => '+00:00'
            ],
            [
                'test' => new CarbonTimeZone('Japan'),
                'expected' => '+09:00'
            ],
            [
                'test' => new CarbonTimeZone('-12:00'),
                'expected' => '-12:00'
            ],
            [
                'test' => new CarbonTimeZone('+14:00'),
                'expected' => '+14:00'
            ],
            [
                'test' => new CarbonTimeZone('GMT+04:45'),
                'expected' => '+04:45'
            ],
            [
                'test' => 0,
                'expected' => '+00:00'
            ],
            [
                'test' => -60,
                'expected' => '-00:01'
            ],
            [
                'test' => 60,
                'expected' => '+00:01'
            ],
            [
                'test' => 50400,
                'expected' => '+14:00'
            ],
            [
                'test' => 50400 / 2,
                'expected' => '+07:00'
            ],
            [
                'test' => -43200,
                'expected' => '-12:00'
            ],
            [
                'test' => -43200 / 2,
                'expected' => '-06:00'
            ],
        ];
    }

    private function testDefaultValues(
        TimezoneOffsetColumn $column,
        mixed $testValue,
        string|int $normalizedValue
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
        // default value with value modifiers
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
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
        TimezoneOffsetColumn $column,
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
        TimezoneOffsetColumn $column,
        mixed $testValue,
        string|int $normalizedValue
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
        TimezoneOffsetColumn $column,
        mixed $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(TimezoneOffsetColumn $column): void {
        // empty string
        $expectedErrors = [
            'Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals($expectedErrors, $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, false));
        // null
        $expectedErrors = [
            'Null value is not allowed.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            'Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
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
        // too large
        static::assertEquals($expectedErrors, $column->validateValue(100000, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(100000, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(100000, true, false));
        static::assertEquals($expectedErrors, $column->validateValue(-100000, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(-100000, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(-100000, true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(TimezoneOffsetColumn $column): TimezoneOffsetColumn {
        $class = $column::class;
        /** @var TimezoneOffsetColumn $copy */
        $copy = new $class($column->getName());
        if ($column->shouldConvertToIntegerValues()) {
            $copy->convertsStringOffsetToInteger();
        }
        return $copy;
    }

    private function newRecordValueContainer(
        RealTableColumnAbstract $column
    ): RecordValueContainerInterface {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    private function getAssertMessageForValue(mixed $testValue): string {
        if ($testValue instanceof CarbonTimeZone) {
            return 'CarbonTimeZone(' . $testValue->getName() . ':' . $testValue->toOffsetName() . ')';
        }
        if ($testValue instanceof \DateTimeZone) {
            return 'DateTimeZone(' . $testValue->getName() . ')';
        }
        return (string)$testValue;
    }

    public function testEmptyStringValueExceptionForTimezoneOffsetColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '+18:00', false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 59, false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [tz_offset] Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.'
        );
        $column = new TimezoneOffsetColumn('tz_offset');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, -590, false, false);
    }
}