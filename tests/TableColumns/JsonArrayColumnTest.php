<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\RecordsCollection\RecordsCollectionInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\JsonArrayColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class JsonArrayColumnTest extends BaseTestCase
{
    public function testJsonArrayColumn(): void
    {
        $column = new JsonArrayColumn('json_array');
        static::assertEquals(TableColumnDataType::JSON, $column->getDataType());
        static::assertEquals(
            [
                $column->getName() . '_as_array' => 'array'
            ],
            $column->getValueFormatersNames()
        );
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

    public function testJsonArrayColumnFormatters(): void
    {
        $column = new JsonArrayColumn('json_array');

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '{}',
            true,
            false
        );
        static::assertEquals([], $column->getValue($container, 'array'));

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '[]',
            true,
            false
        );
        static::assertEquals([], $column->getValue($container, 'array'));

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '[1,2.1,"3","a",[],["test"],{"key":"value"}]',
            true,
            false
        );
        static::assertEquals(
            [1, 2.1, '3', 'a', [], ['test'], ['key' => 'value']],
            $column->getValue($container, 'array')
        );

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            [1, 2.1, '3', 'a', [], ['test'], ['key' => 'value']],
            true,
            false
        );
        static::assertEquals(
            [1, 2.1, '3', 'a', [], ['test'], ['key' => 'value']],
            $column->getValue($container, 'array')
        );
    }

    private function getValuesForTesting(): array
    {
        $recordsArray = new RecordsArray(
            TestingAdminsTable::getInstance(),
            [
                new TestingAdmin(),
                new TestingAdmin(),
            ],
            false
        );
        return [
            [
                'test' => [],
                'expected' => '[]',
            ],
            [
                'test' => '[]',
                'expected' => '[]',
            ],
            [
                'test' => '{}',
                'expected' => '[]',
            ],
            [
                'test' => [1, 2.1, '3', 'a', [], ['test'], ['key' => 'value']],
                'expected' => '[1,2.1,"3","a",[],["test"],{"key":"value"}]',
            ],
            [
                'test' => '[1,2.1,"3","a",[],["test"],{"key":"value"}]',
                'expected' => '[1,2.1,"3","a",[],["test"],{"key":"value"}]',
            ],
            [
                'test' => $recordsArray,
                'expected' => json_encode($recordsArray->toArrays())
            ],
            [
                'test' => $recordsArray->toObjects(),
                'expected' => json_encode($recordsArray->toArrays())
            ]
        ];
    }

    private function testDefaultValues(
        JsonArrayColumn $column,
        string|array|RecordsCollectionInterface $testValue,
        string $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        $normalizedDefaultValue = $testValue instanceof RecordsCollectionInterface
            ? $testValue
            : $normalizedValue;
        // default value
        $column = $this->newColumn($column);
        $column->setDefaultValue($testValue);
        $valueContainer = $this->newRecordValueContainer($column);
        static::assertFalse($column->hasValue($valueContainer, false), $message);
        static::assertTrue($column->hasValue($valueContainer, true), $message);
        static::assertFalse($valueContainer->hasValue(), $message);
        static::assertEquals($testValue, $column->getDefaultValue(), $message);
        static::assertEquals($normalizedDefaultValue, $column->getValidDefaultValue(), $message);
        // use null value to use default value
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
        static::assertEquals($normalizedDefaultValue, $column->getValidDefaultValue(), $message);
        $column->setValue($valueContainer, null, false, false);
        static::assertEquals($normalizedValue, $column->getValue($valueContainer, null), $message);
    }

    private function testNonDbValues(
        JsonArrayColumn $column,
        string|array|RecordsCollectionInterface $testValue,
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
        JsonArrayColumn $column,
        string|array|RecordsCollectionInterface $testValue,
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
        JsonArrayColumn $column,
        string|array|RecordsCollectionInterface $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(JsonArrayColumn $column): void
    {
        // empty string
        $expectedErrors = [
            'Null value is not allowed.',
        ];
        static::assertEquals($expectedErrors, $column->validateValue('', false, false));
        static::assertEquals([], $column->validateValue('', false, true));
        static::assertEquals($expectedErrors, $column->validateValue('', true, false));
        // null
        static::assertEquals($expectedErrors, $column->validateValue(null, false, false));
        static::assertEquals([], $column->validateValue(null, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(null, true, false));

        $expectedErrors = [
            'Value must be a json-encoded indexed array or indexed PHP array.',
        ];
        // random object
        static::assertEquals($expectedErrors, $column->validateValue($this, false, false));
        static::assertEquals($expectedErrors, $column->validateValue($this, false, true));
        static::assertEquals($expectedErrors, $column->validateValue($this, true, false));
        // bool
        static::assertEquals($expectedErrors, $column->validateValue(true, false, false));
        static::assertEquals([], $column->validateValue(true, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(true, true, false));
        static::assertEquals($expectedErrors, $column->validateValue(false, false, false));
        static::assertEquals([], $column->validateValue(false, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(false, true, false));
        // float
        static::assertEquals($expectedErrors, $column->validateValue(1.1, false, false));
        static::assertEquals([], $column->validateValue(1.1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(1.1, true, false));
        // int
        static::assertEquals($expectedErrors, $column->validateValue(1, false, false));
        static::assertEquals([], $column->validateValue(1, false, true));
        static::assertEquals($expectedErrors, $column->validateValue(1, true, false));
        // assoc array
        static::assertEquals(
            $expectedErrors,
            $column->validateValue(['key' => 'value'], false, false)
        );
        static::assertEquals(
            [],
            $column->validateValue(['key' => 'value'], false, true)
        );
        static::assertEquals(
            $expectedErrors,
            $column->validateValue(['key' => 'value'], true, false)
        );
        // json encoded object
        static::assertEquals(
            $expectedErrors,
            $column->validateValue('{"key":"value"}', false, false)
        );
        static::assertEquals(
            [],
            $column->validateValue('{"key":"value"}', false, true)
        );
        static::assertEquals(
            $expectedErrors,
            $column->validateValue('{"key":"value"}', true, false)
        );
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(JsonArrayColumn $column): JsonArrayColumn
    {
        $class = $column::class;
        return new $class($column->getName());
    }

    private function newRecordValueContainer(
        RealTableColumnAbstract $column
    ): RecordValueContainerInterface {
        return $column->getNewRecordValueContainer(new TestingAdmin());
    }

    private function getAssertMessageForValue(mixed $testValue): string
    {
        if ($testValue instanceof RecordsCollectionInterface) {
            return 'RecordsArray(' . json_encode($testValue->toArrays()) . ')';
        }
        return is_array($testValue) ? json_encode($testValue) : $testValue;
    }

    public function testEmptyStringValueExceptionForJsonArrayColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Null value is not allowed.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Null value is not allowed.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn7(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn8(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '1.00001', false, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn9(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1, true, false);
    }

    public function testInvalidValueExceptionForJsonArrayColumn10(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_array] Value must be a json-encoded indexed array or indexed PHP array.'
        );
        $column = new JsonArrayColumn('json_array');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '{"key":"value"}', true, false);
    }
}