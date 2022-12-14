<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\JsonObjectColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingValueToObjectConverter;

class JsonObjectColumnTest extends BaseTestCase
{
    public function testJsonObjectColumn(): void
    {
        $column = new JsonObjectColumn('json_object');
        static::assertEquals(TableColumnDataType::JSON, $column->getDataType());
        static::assertEquals(
            [
                $column->getName() . '_as_array' => 'array',
                $column->getName() . '_as_object' => 'object'
            ],
            $column->getColumnNameAliases()
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

    public function testJsonObjectColumnFormatters(): void
    {
        $column = new JsonObjectColumn('json_object');

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '{}',
            true,
            false
        );
        static::assertEquals([], $column->getValue($container, 'array'));
        static::assertEquals(new \stdClass(), $column->getValue($container, 'object'));

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '[]',
            true,
            false
        );
        static::assertEquals([], $column->getValue($container, 'array'));
        static::assertEquals(new \stdClass(), $column->getValue($container, 'object'));

        $json = '{"key1":"value","key2":["a",1],"key3":{"b":2.1}}';
        $array = ['key1' => 'value', 'key2' => ['a', 1], 'key3' => ['b' => 2.1]];
        $object = json_decode($json, false);
        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            '{"key1":"value","key2":["a",1],"key3":{"b":2.1}}',
            true,
            false
        );
        static::assertEquals($array, $column->getValue($container, 'array'));
        static::assertEquals($object, $column->getValue($container, 'object'));

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            $array,
            true,
            false
        );
        static::assertEquals($array, $column->getValue($container, 'array'));
        static::assertEquals($object, $column->getValue($container, 'object'));
    }

    public function testJsonObjectColumnToClassInstanceFormatter(): void {
        $column = new JsonObjectColumn('json_object');
        $column->setClassNameForValueToClassInstanceConverter(
            TestingValueToObjectConverter::class
        );
        $array = [
            'key1' => 1,
            'key2' => 'a',
            'key3' => [2, 'b'],
            'key4' => ['c' => 'd']
        ];
        $expectedObject = TestingValueToObjectConverter::createObjectFromArray($array);
        static::assertInstanceOf(TestingValueToObjectConverter::class, $expectedObject);
        static::assertEquals($array, $expectedObject->other);
        static::assertEquals($array, $expectedObject->toArray());

        $container = $column->setValue(
            $this->newRecordValueContainer($column),
            $array,
            true,
            false
        );
        static::assertEquals($array, $column->getValue($container, 'array'));
        /** @var TestingValueToObjectConverter $actualObject */
        $actualObject = $column->getValue($container, 'object');
        static::assertEquals($expectedObject, $actualObject);
        static::assertEquals($expectedObject->toArray(), $actualObject->toArray());
    }

    private function getValuesForTesting(): array
    {
        $recordObject = new TestingAdmin();
        return [
            [
                'test' => [],
                'expected' => '{}',
            ],
            [
                'test' => '[]',
                'expected' => '{}',
            ],
            [
                'test' => '{}',
                'expected' => '{}',
            ],
            [
                'test' => ['key1' => 'value', 'key2' => ['a', 1], 'key3' => ['b' => 2.1]],
                'expected' => '{"key1":"value","key2":["a",1],"key3":{"b":2.1}}',
            ],
            [
                'test' => '{"key1":"value","key2":["a",1],"key3":{"b":2.1}}',
                'expected' => '{"key1":"value","key2":["a",1],"key3":{"b":2.1}}',
            ],
            [
                'test' => $recordObject,
                'expected' => json_encode($recordObject->toArray())
            ],
        ];
    }

    private function testDefaultValues(
        JsonObjectColumn $column,
        string|array|RecordInterface $testValue,
        string $normalizedValue
    ): void {
        $message = $this->getAssertMessageForValue($testValue);
        $normalizedDefaultValue = $testValue instanceof RecordInterface
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
        JsonObjectColumn $column,
        string|array|RecordInterface $testValue,
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
        JsonObjectColumn $column,
        string|array|RecordInterface $testValue,
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
        JsonObjectColumn $column,
        string|array|RecordInterface $testValue,
    ): void {
        $column = $this->newColumn($column);
        $message = $this->getAssertMessageForValue($testValue);
        // good value
        static::assertEquals([], $column->validateValue($testValue, false, false), $message);
        static::assertEquals([], $column->validateValue($testValue, false, true), $message);
        static::assertEquals([], $column->validateValue($testValue, true, false), $message);
    }

    private function testValidateValueCommon(JsonObjectColumn $column): void
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
            'Value must be a json-encoded key-value object or associative PHP array.',
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
        // indexed array
        static::assertEquals(
            $expectedErrors,
            $column->validateValue(['a', 'b'], false, false)
        );
        static::assertEquals(
            [],
            $column->validateValue(['a', 'b'], false, true)
        );
        static::assertEquals(
            $expectedErrors,
            $column->validateValue(['a', 'b'], true, false)
        );
        // json encoded array
        static::assertEquals(
            $expectedErrors,
            $column->validateValue('["a","b"]', false, false)
        );
        static::assertEquals(
            [],
            $column->validateValue('["a","b"]', false, true)
        );
        static::assertEquals(
            $expectedErrors,
            $column->validateValue('["a","b"]', true, false)
        );
        // DbExpr and SelectQueryBuilderInterface tested in TableColumnsBasicsTest
    }

    private function newColumn(JsonObjectColumn $column): JsonObjectColumn
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
        if ($testValue instanceof RecordInterface) {
            return 'RecordInterface(' . json_encode($testValue->toArray()) . ')';
        }
        return is_array($testValue) ? json_encode($testValue) : $testValue;
    }

    public function testEmptyStringValueExceptionForJsonObjectColumn(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Null value is not allowed.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '', false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn1(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'qweqwe', false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn2(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 'true', false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, true, false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, false, false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn5(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, $this, false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn6(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Null value is not allowed.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, null, false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn7(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1.00001, false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn8(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '1.00001', false, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn9(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, 1, true, false);
    }

    public function testInvalidValueExceptionForJsonObjectColumn10(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage(
            'Validation errors: [json_object] Value must be a json-encoded key-value object or associative PHP array.'
        );
        $column = new JsonObjectColumn('json_object');
        $valueContainer = $this->newRecordValueContainer($column);
        $column->setValue($valueContainer, '["a", "b"]', true, false);
    }
}