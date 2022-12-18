<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IpV4AddressColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class IpV4AddressColumnTest extends BaseTestCase
{
    public function testIpV4AddressColumn(): void
    {
        $column = new IpV4AddressColumn('ipv4');
        static::assertEquals(TableColumnDataType::IPV4_ADDRESS, $column->getDataType());
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
                'test' => '0.0.0.0',
                'expected' => '0.0.0.0'
            ],
            [
                'test' => '0.0.0.1',
                'expected' => '0.0.0.1'
            ],
            [
                'test' => '255.255.255.255',
                'expected' => '255.255.255.255',
            ],
            [
                'test' => '200.200.200.200',
                'expected' => '200.200.200.200',
            ],
            [
                'test' => '100.100.100.100',
                'expected' => '100.100.100.100',
            ],
            [
                'test' => '127.0.0.0',
                'expected' => '127.0.0.0',
            ],
        ];
    }

    private function testDefaultValues(
        IpV4AddressColumn $column,
        string $testValue,
        string $normalizedValue
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
        IpV4AddressColumn $column,
        string $testValue,
        string $normalizedValue
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
        IpV4AddressColumn $column,
        string $testValue,
        string $normalizedValue
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
        IpV4AddressColumn $column,
        string $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(IpV4AddressColumn $column): void {
        // empty string
        $expectedErrors = [
            'Value must be an IPv4 address.'
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
            'Value must be an IPv4 address.'
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
        // float
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, false));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, true, false));
        // array
        static::assertEquals($expectedErrors, $column->validateValue([], false, false));
        static::assertEquals($expectedErrors, $column->validateValue([], false, true));
        static::assertEquals($expectedErrors, $column->validateValue([], true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(IpV4AddressColumn $column): IpV4AddressColumn {
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

    public function testEmptyStringValueExceptionForIpV4AddressColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Null value is not allowed.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn7(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn8(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '1.00001', false, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn9(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, true, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn10(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '0.11.11', true, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn11(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '0.11.11.11.11', true, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn12(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '256.0.0.0', true, false);
    }

    public function testInvalidValueExceptionForIpV4AddressColumn13(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [ipv4] Value must be an IPv4 address.'
        );
        $column = new IpV4AddressColumn('ipv4');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '192.168.1.1/24', true, false);
    }
}