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
use PeskyORM\ORM\TableStructure\TableColumn\Column\UnixTimestampColumn;
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

    public function testUnixTimestampColumn(): void
    {
        $column = new UnixTimestampColumn('unixtimestamp');
        $this->testCommonProperties($column, TableColumnDataType::UNIX_TIMESTAMP);
        $this->testValidateValueCommon($column, 'timestamp or positive integer');
        static::assertEquals(
            [
                $column->getName() . '_as_date' => 'date',
                $column->getName() . '_as_time' => 'time',
                $column->getName() . '_as_carbon' => 'carbon',
                $column->getName() . '_as_date_time' => 'date_time',
            ],
            $column->getColumnNameAliases()
        );

        foreach ($this->getValuesForTesting() as $testValue) {
            $this->testValidateGoodValue($column, $testValue);
            $this->testDefaultValues($column, $testValue);
            $this->testNonDbValues($column, $testValue);
            $this->testDbValues($column, $testValue);
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
            '+1 day',
            'now',
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
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
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
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): void {
        $message = $this->getAssertMessageForValue($column, $testValue);
        // default value
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        $normalizedValue = $this->getNormalizedValue($column, $testValue);
        static::assertEquals($testValue, $column->getDefaultValue(), $message);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        // default value with value modifiers
        $column = $this->newColumn($column);
        $normalizedValue = $this->getNormalizedValue($column, $testValue);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
        // default value as closure
        $column = $this->newColumn($column);
        $normalizedValue = $this->getNormalizedValue($column, $testValue);
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
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): void {
        $message = $this->getAssertMessageForValue($column, $testValue);
        $column = $this->newColumn($column);
        // setter & getter
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, false, false);
        static::assertTrue($valueContainer->hasValue(), $message);
        $normalizedValue = $this->getNormalizedValue($column, $testValue);
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
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): void {
        $message = $this->getAssertMessageForValue($column, $testValue);
        // not trusted DB value
        $column = $this->newColumn($column);
        $column->setDefaultValue('default');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, false);

        $expectedValue = $testValue;
        if ($column instanceof UnixTimestampColumn) {
            if (is_numeric($expectedValue)) {
                $expectedValue = (int)$expectedValue;
            } else {
                $expectedValue = $this->getNormalizedValue($column, $testValue);
            }
        } elseif (is_numeric($expectedValue) || is_object($expectedValue)) {
            $expectedValue = $this->getNormalizedValue($column, $testValue);
        }
        static::assertEquals($expectedValue, $column->getValue($valueContainer, null), $message);
        // it will not add time zone offset to timestamp without tz
        // trusted DB value
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $testValue, true, true);
        $expectedValue = $testValue;
        if ($column instanceof UnixTimestampColumn) {
            if (is_numeric($expectedValue)) {
                $expectedValue = (int)$expectedValue;
            } else {
                $expectedValue = $this->getNormalizedValue($column, $testValue);
            }
        } elseif (is_numeric($expectedValue) || is_object($expectedValue)) {
            $expectedValue = $this->getNormalizedValue($column, $testValue);
        }
        static::assertEquals($expectedValue, $column->getValue($valueContainer, null), $message);
        // Note: DbExpr and SelectQueryBuilderInterface not allowed
        // (tested in TableColumnsBasicsTest)
    }

    private function testValidateGoodValue(
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($column, $testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, true), $message);
    }

    private function testValidateValueCommon(
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
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
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column
    ): UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn {
        $class = $column::class;
        /** @var UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $ret */
        $ret = new $class($column->getName());
        if ($this->isTimezoneExpected($column)) {
            $ret->withTimezone();
        }
        return $ret;
    }

    private function isTimezoneExpected(RealTableColumnAbstract $column): ?bool
    {
        if (
            $column instanceof DateColumn
            || $column instanceof UnixTimestampColumn
        ) {
            return null;
        }
        /** @var TimestampColumn|TimeColumn $column */
        return $column->isTimezoneExpected();
    }

    private function newRecordValueContainer(
        RealTableColumnAbstract $column
    ): RecordValueContainerInterface {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    private function getNormalizedValue(
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): string|int {
        $withTimezone = $this->isTimezoneExpected($column);
        if ($column instanceof UnixTimestampColumn) {
            if (is_numeric($testValue)) {
                return (int)$testValue;
            }
            return Carbon::parse($testValue)->unix();
        }
        $format = $withTimezone ? $column::FORMAT_WITH_TZ : $column::FORMAT;
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
    }

    private function getAssertMessageForValue(
        UnixTimestampColumn|TimestampColumn|TimeColumn|DateColumn $column,
        mixed $testValue
    ): string {
        $value = is_object($testValue) ? 'DateTime(' . $testValue->format('Y-m-d H:i:s Z') . ')' : (string)$testValue;
        $suffix = '';
        $timezoneExpected = $this->isTimezoneExpected($column);
        if ($timezoneExpected !== null) {
            $suffix .= ' / ';
            $suffix .= $column->isTimezoneExpected()
                ? 'Expected with time zone'
                : 'Expected without time zone';
        }
        return $value . $suffix;
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