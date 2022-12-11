<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\DateColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class DateTimeColumnsTest extends BaseTestCase
{
    public function testTimestampColumn(): void
    {
        date_default_timezone_set('Europe/Amsterdam');
        static::assertEquals(
            '2022-12-07 15:00:00+01:00',
            date(TimestampColumn::FORMAT_WITH_TZ, strtotime('2022-12-07 15:00:00')),
        );
        $column = new TimestampColumn('timestamp');
        $columnTz = new TimestampColumn('timestamptz');
        $columnTz->withTimezone();
        $this->testCommonProperties($column, TableColumnDataType::TIMESTAMP);
        $this->testCommonProperties($columnTz, TableColumnDataType::TIMESTAMP);
        $this->testValidateValueCommon($column, 'timestamp');
        $this->testValidateValueCommon($columnTz, 'timestamp');
        static::assertEquals(
            [
                $column->getName() . '_as_date' => 'date',
                $column->getName() . '_as_time' => 'time',
                $column->getName() . '_as_unix_ts' => 'unix_ts',
                $column->getName() . '_as_carbon' => 'carbon',
            ],
            $column->getColumnNameAliases()
        );
        static::assertEquals(
            [
                $columnTz->getName() . '_as_date' => 'date',
                $columnTz->getName() . '_as_time' => 'time',
                $columnTz->getName() . '_as_unix_ts' => 'unix_ts',
                $columnTz->getName() . '_as_carbon' => 'carbon',
            ],
            $columnTz->getColumnNameAliases()
        );

        foreach ($this->getValuesForTesting() as $testValue) {
            $testValue = $this->getTestValueClosure($testValue);
            $this->testValidateGoodValue($column, $testValue);
            $this->testValidateGoodValue($columnTz, $testValue);
            $this->testDefaultValues($column, $testValue);
            $this->testDefaultValues($columnTz, $testValue);
            $this->testNonDbValues($column, $testValue);
            $this->testNonDbValues($columnTz, $testValue);
            $this->testDbValues($column, $testValue);
            $this->testDbValues($columnTz, $testValue);
        }
    }

    public function testDateColumn(): void
    {
        date_default_timezone_set('Europe/Amsterdam');
        static::assertEquals(
            '2022-12-07 15:00:00+01:00',
            date(TimestampColumn::FORMAT_WITH_TZ, strtotime('2022-12-07 15:00:00')),
        );
        $column = new DateColumn('date');
        $this->testCommonProperties($column, TableColumnDataType::DATE);
        $this->testValidateValueCommon($column, 'date');
        static::assertEquals(
            [
                $column->getName() . '_as_unix_ts' => 'unix_ts',
                $column->getName() . '_as_carbon' => 'carbon',
            ],
            $column->getColumnNameAliases()
        );

        foreach ($this->getValuesForTesting() as $testValue) {
            $testValue = $this->getTestValueClosure($testValue);
            $this->testValidateGoodValue($column, $testValue);
            $this->testDefaultValues($column, $testValue);
            $this->testNonDbValues($column, $testValue);
            $this->testDbValues($column, $testValue);
        }
    }

    public function testTimeColumn(): void
    {
        date_default_timezone_set('Europe/Amsterdam');
        static::assertEquals(
            '2022-12-07 15:00:00+01:00',
            date(TimestampColumn::FORMAT_WITH_TZ, strtotime('2022-12-07 15:00:00')),
        );
        $column = new TimeColumn('time');
        $columnTz = new TimeColumn('timetz');
        $columnTz->withTimezone();
        $this->testCommonProperties($column, TableColumnDataType::TIME);
        $this->testCommonProperties($columnTz, TableColumnDataType::TIME);
        $this->testValidateValueCommon($column, 'time');
        $this->testValidateValueCommon($columnTz, 'time');
        static::assertEquals(
            [
                $column->getName() . '_as_unix_ts' => 'unix_ts',
            ],
            $column->getColumnNameAliases()
        );
        static::assertEquals(
            [
                $columnTz->getName() . '_as_unix_ts' => 'unix_ts',
            ],
            $columnTz->getColumnNameAliases()
        );

        foreach ($this->getValuesForTesting() as $testValue) {
            $testValue = $this->getTestValueClosure($testValue);
            $this->testValidateGoodValue($column, $testValue);
            $this->testValidateGoodValue($columnTz, $testValue);
            $this->testDefaultValues($column, $testValue);
            $this->testDefaultValues($columnTz, $testValue);
            $this->testNonDbValues($column, $testValue);
            $this->testNonDbValues($columnTz, $testValue);
            $this->testDbValues($column, $testValue);
            $this->testDbValues($columnTz, $testValue);
        }
    }

    private function getValuesForTesting(): array
    {
        return [
            '2022-12-07 15:00:00',
            '2022-12-07',
            '2022-12-31 23:59:59',
            '2022-12-01 00:00:00',
            '2022-12-1 00:00:00',
            '2022-12-1 01:00:00 AM',
            '22-12-01 00:00:00',
            '22-12-01 01:00:00 AM',
            '22-12-01',
            '22-2-1 00:00:00',
            '22-2-1 0:0:0',
            '22-2-1',
            '02/21/2018',
            '02/21/2018 12:00:00',
            '02/21/18 12:00:00',
            '02/21/18 12:00:00 AM',
            '2/21/18 12:00:00',
            '2/21/18 12:00:00 AM',
            '2/2/2018 1:0:0',
            'December 07, 2022',
            'December 7, 2022',
            'December 7, 2022, 12:00:00',
            'December 7, 2022, 12:00:00 AM',
            '12:00:00, December 7, 2022',
            'Dec 07, 2022',
            'Dec 7, 2022',
            'Dec 7, 2022, 12:00:00',
            'Dec 7, 2022, 12:00:00 AM',
            '11:00:00, Dec 7, 2022',
            '12:00:00',
            '12:00:00.865',
            '12:00:00 AM',
            '12:00:00 PM',
            '4.10 PM',
            '2009-06-15T13:45:30',
            '2009-06-15T13:45:30Z',
            '2009-06-15T13:45:30GMT',
            '2009-06-15T13:45:30GMT+3',
            '2009-06-15T13:45:30UTC+3',
            '20090615+3',
            '2009-06-15+3',
            '2009-06-15+03:00',
            '2009-06-15-03:00',
            '2009-06-15T14:00:00-03:00',
            '2018-12-25 23:50:55.999',
            '2018-12-25 23:50:55.999 +0530',
            '2018-12-25 23:50:55.999 GMT+05:30',
            function () {
                return '+1 day';
            },
            function () {
                return 'now';
            },
            1670764507,
            1,
            '1',
            1.1,
            '1.1',
            Carbon::parse('2022-12-07 15:00:00'),
            Carbon::parse('2022-12-07 15:00:00+01:00'),
            new \DateTime('2022-12-07 15:00:00'),
            new \DateTime('2022-12-07 15:00:00+01:00'),
        ];
    }

    private function testCommonProperties(
        TimestampColumn|TimeColumn|DateColumn $column,
        string $type,
    ): void {
        $column = $this->newColumn($column);
        static::assertEquals($type, $column->getDataType());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
    }

    private function testDefaultValues(
        TimestampColumn|TimeColumn|DateColumn $column,
        \Closure $testValueClosure
    ): void {
        $normalizedValueClosure = $this->getNormalizedValueClosure($column, $testValueClosure);
        $message = $this->getAssertMessageForValue($column, $testValueClosure);
        // default value
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValueClosure());
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        static::assertEquals($testValueClosure(), $column->getDefaultValue(), $message);
        static::assertEquals($normalizedValueClosure(), $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
        // default value with value modifiers
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValueClosure());
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValueClosure(), $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
        // default value as closure
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValueClosure);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        static::assertEquals($normalizedValueClosure(), $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
    }

    private function testNonDbValues(
        TimestampColumn|TimeColumn|DateColumn $column,
        \Closure $testValueClosure
    ): void {
        $normalizedValueClosure = $this->getNormalizedValueClosure($column, $testValueClosure);
        $message = $this->getAssertMessageForValue($column, $testValueClosure);
        $column = $this->newColumn($column);
        // setter & getter
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValueClosure(), false, false);
        static::assertTrue($valueContainer->hasValue(), $message);
        static::assertEquals($normalizedValueClosure(), $valueContainer->getValue(), $message);
        static::assertEquals($normalizedValueClosure(), $column->getValue($valueContainer, null), $message);
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
        TimestampColumn|TimeColumn|DateColumn $column,
        \Closure $testValueClosure
    ): void {
        $message = $this->getAssertMessageForValue($column, $testValueClosure);
        $normalizedValueClosure = $this->getNormalizedValueClosure($column, $testValueClosure);
        // not trusted DB value
        $column = $this->newColumn($column);
        $column->setDefaultValue('default');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValueClosure(), true, false);

        $expectedValue = $testValueClosure();
        if (is_numeric($expectedValue) || is_object($expectedValue)) {
            $expectedValue = $normalizedValueClosure();
        }
        static::assertEquals($expectedValue, $column->getValue($valueContainer, null), $message);
        // it will not add time zone offset to timestamp without tz
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValueClosure(), true, true);
        $expectedValue = $testValueClosure();
        if (is_numeric($expectedValue) || is_object($expectedValue)) {
            $expectedValue = $normalizedValueClosure();
        }
        static::assertEquals($expectedValue, $column->getValue($valueContainer, null), $message);
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateGoodValue(
        TimestampColumn|TimeColumn|DateColumn $column,
        \Closure $testValueClosure
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($column, $testValueClosure);
        // good value
        static::assertEquals([], $column->validateValue($testValueClosure(), false, false), $message);
        static::assertEquals([], $column->validateValue($testValueClosure(), false, true), $message);
        static::assertEquals([], $column->validateValue($testValueClosure(), true, true), $message);
    }

    private function testValidateValueCommon(
        TimestampColumn|TimeColumn|DateColumn $column,
        string $typeForError,
    ): void {
        // empty string
        $expectedErrors = [
            "Value must be a valid $typeForError.",
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals($expectedErrors, $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, true));
        // null
        $expectedErrors = [
            'Null value is not allowed.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals([], $column->validateValue(null, true, true));
        // random object
        $expectedErrors = [
            "Value must be a valid $typeForError.",
        ];
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($this, true, true));
        // bool
        static::assertEquals($expectedErrors, $column->validateValue(true, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(true, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(true, true, true));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(false, true, true));
        // negative
        static::assertEquals($expectedErrors, $column->validateValue(-1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(-1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(-1, true, true));
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

    private function newColumn(
        TimestampColumn|DateColumn|TimeColumn $column
    ): TimestampColumn|DateColumn|TimeColumn {
        $class = $column::class;
        /** @var TimestampColumn|DateColumn|TimeColumn $ret */
        $ret = new $class($column->getName());
        if (!($column instanceof DateColumn) && $column->isTimezoneExpected()) {
            $ret->withTimezone();
        }
        return $ret;
    }

    private function newRecordValueContainer(RealTableColumnAbstract $column): RecordValueContainerInterface
    {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    private function getNormalizedValueClosure(
        TimestampColumn|DateColumn|TimeColumn $column,
        \Closure $testValueClosure
    ): \Closure {
        $withTimezone = $column instanceof DateColumn ? false : $column->isTimezoneExpected();
        $format = $withTimezone ? $column::FORMAT_WITH_TZ : $column::FORMAT;
        return static function () use ($withTimezone, $format, $testValueClosure) {
            $testValue = $testValueClosure();
            if (is_object($testValue)) {
                /** @var \DateTimeInterface $testValue */
                return $testValue->format($format);
            }
            if (is_numeric($testValue)) {
                $testValue = CarbonImmutable::createFromTimestampUTC($testValue);
                if ($withTimezone) {
                    $testValue->timezone(null);
                }
                return $testValue->format($format);
            }
            return Carbon::parse($testValue)->format($format);
        };
    }

    private function getAssertMessageForValue(
        TimestampColumn|DateColumn|TimeColumn $column,
        \Closure $testValueClosure
    ): string {
        $testValue = $testValueClosure();
        $value = is_object($testValue) ? 'DateTime(' . $testValue->format('Y-m-d H:i:s Z') . ')' : (string)$testValue;
        $suffix = '';
        if (!($column instanceof DateColumn)) {
            $suffix .= ' / ';
            $suffix .= $column->isTimezoneExpected()
                ? 'Expected with time zone'
                : 'Expected without time zone';
        }
        return $value . $suffix;
    }

    private function getTestValueClosure(mixed $testValue): \Closure
    {
        if ($testValue instanceof \Closure) {
            return $testValue;
        }
        return static function () use ($testValue) {
            return $testValue;
        };
    }

    public function testEmptyStringValueExceptionForTimestampColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [timestamp] Value must be a valid timestamp.'
        );
        // empty string
        $column = new TimestampColumn('timestamp');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testEmptyStringValueExceptionForDateColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [date] Value must be a valid date.'
        );
        // empty string
        $column = new DateColumn('date');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testEmptyStringValueExceptionForTimeColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [time] Value must be a valid time.'
        );
        // empty string
        $column = new TimeColumn('time');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForTimestampColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [timestamp] Value must be a valid timestamp.'
        );
        // empty string
        $column = new TimestampColumn('timestamp');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueValueExceptionForDateColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [date] Value must be a valid date.'
        );
        // empty string
        $column = new DateColumn('date');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueValueExceptionForTimeColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [time] Value must be a valid time.'
        );
        // empty string
        $column = new TimeColumn('time');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }
}