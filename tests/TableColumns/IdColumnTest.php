<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class IdColumnTest extends BaseTestCase
{
    public function testIdColumn(): void
    {
        $column = new IdColumn('id_pk');
        static::assertEquals(TableColumnDataType::INT, $column->getDataType());
        static::assertTrue($column->isPrimaryKey());
        static::assertTrue($column->isNullableValues());
        static::assertFalse($column->isAutoUpdatingValues());
        static::assertEquals([], $column->getValueFormatersNames());
        // has value
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        $this->testValidateValueCommon($column);
        $this->testDefaultValues($column);

        foreach ($this->getValuesForTesting() as $values) {
            $this->testValidateGoodValue($column, $values['test']);
            $this->testNonDbValues($column, $values['test'], $values['expected']);
            $this->testDbValues($column, $values['test'], $values['expected']);
        }
    }

    private function getValuesForTesting(): array
    {
        return [
            [
                'test' => 1,
                'expected' => 1
            ],
            [
                'test' => 100000,
                'expected' => 100000
            ],
            [
                'test' => '1',
                'expected' => 1
            ],
            [
                'test' => '100000',
                'expected' => 100000
            ],
        ];
    }

    private function testDefaultValues(IdColumn $column): void {
        // we cannot set default value
        $column = $this->newColumn($column);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false));
        static::assertFalse($column->hasValue($valueContainer, true));
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($column->getValue($valueContainer, null));
    }

    private function testNonDbValues(
        IdColumn $column,
        string|int $testValue,
        int $normalizedValue
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
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
        static::assertNull($valueContainer->getValue(), $message);
        static::assertNull($column->getValue($valueContainer, null), $message);
        // DbExpr
        $valueContainer = $this->newRecordValueContainer($column);
        $dbExpr = new DbExpr('test');
        $column->setValue($valueContainer, $dbExpr, false, false);
        static::assertEquals($dbExpr, $column->getValue($valueContainer, null), $message);
        // SelectQueryBuilderInterface not allowed
    }

    private function testDbValues(
        IdColumn $column,
        string|int $testValue,
        int $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        // not trusted DB value
        $column = $this->newColumn($column);
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
        IdColumn $column,
        string|int $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(IdColumn $column): void {
        // empty string
        $expectedErrors = [
            'Integer value expected.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals($expectedErrors, $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, false));
        // null
        static::assertEquals([], $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        $expectedErrors = [
            'Null value is not allowed.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));
        // random object
        $expectedErrors = [
            'Integer value expected.'
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
        // negative
        $expectedErrors = [
            'Value must be a positive integer number.'
        ];
        static::assertEquals($expectedErrors, $column->validateValue(-1, false, false));
        static::assertEquals([], $column->validateValue(-1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(-1, true, false));
        // zero
        static::assertEquals($expectedErrors, $column->validateValue(0, false, false));
        static::assertEquals([], $column->validateValue(0, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(0, true, false));
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(IdColumn $column): IdColumn {
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

    public function testEmptyStringValueExceptionForIdColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForIdColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForIdColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForIdColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForIdColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForIdColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            "Validation errors: [id_pk] Integer value expected."
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForIdColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForIdColumn7(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, false, false);
    }

    public function testInvalidValueExceptionForIdColumn8(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '1.00001', false, false);
    }

    public function testInvalidValueExceptionForIdColumn9(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value must be a positive integer number.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, -1, false, false);
    }

    public function testInvalidValueExceptionForIdColumn10(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value must be a positive integer number.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, -1, true, false);
    }

    public function testInvalidValueExceptionForIdColumn11(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value must be a positive integer number.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 0, false, false);
    }

    public function testInvalidValueExceptionForIdColumn12(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value must be a positive integer number.'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 0, true, false);
    }

    public function testSetDefaultValueExceptionForIdColumn(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Primary key column .*'id_pk'.* is not allowed to have default value%"
        );
        $column = new IdColumn('id_pk');
        $column->setDefaultValue('qqq');
    }

    public function testOrmSelectValueExceptionForIdColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Integer value expected'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, false, false);
    }

    public function testOrmSelectValueExceptionForIdColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value received from DB cannot be instance of SelectQueryBuilderInterface'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, true, false);
    }

    public function testOrmSelectValueExceptionForIdColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [id_pk] Value received from DB cannot be instance of SelectQueryBuilderInterface'
        );
        $column = new IdColumn('id_pk');
        $valueContainer = $this->newRecordValueContainer($column);
        $select = new OrmSelect(TestingAdminsTable::getInstance());
        $column->setValue($valueContainer, $select, true, true);
    }

}