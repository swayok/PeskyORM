<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormatter;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormatterJsonObject;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormattersTableStructure;

// todo: refactor this
class RecordValueFormattersTest extends BaseTestCase
{

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->callObjectMethod(TestingFormatter::newEmptyRecord(), 'resetColumnsCache');
    }

    public function testTimestampFormatters(): void
    {
        $formatters = ColumnValueFormatters::getTimestampFormatters();
        static::assertIsArray($formatters);

        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_DATE => ColumnValueFormatters::getTimestampToDateFormatter(),
                ColumnValueFormatters::FORMAT_TIME => ColumnValueFormatters::getTimestampToTimeFormatter(),
                ColumnValueFormatters::FORMAT_UNIX_TS => ColumnValueFormatters::getDateTimeToUnixTsFormatter(),
                ColumnValueFormatters::FORMAT_CARBON => ColumnValueFormatters::getTimestampToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_DATE]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_UNIX_TS]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_CARBON]);

        $ts = time();
        $dateTime = date('Y-m-d H:i:s', $ts);
        $record = TestingFormatter::fromArray(['created_at' => $dateTime]);
        $valueContainer = $record->getValueContainer('created_at');
        static::assertEquals($dateTime, $record->created_at);
        static::assertEquals($dateTime, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['created_at' => $dateTime])
            ->getValueContainer('created_at'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // date formatter
        $date = date('Y-m-d', $ts);
        static::assertEquals($date, $record->created_at_as_date);
        static::assertEquals($date, ColumnValueFormatters::getTimestampToDateFormatter()($valueContainerAlt));
        // time formatter
        $time = date('H:i:s', $ts);
        static::assertEquals($time, $record->created_at_as_time);
        static::assertEquals($time, ColumnValueFormatters::getTimestampToTimeFormatter()($valueContainerAlt));
        // unix_ts formatter
        static::assertEquals($ts, $record->created_at_as_unix_ts);
        static::assertEquals($ts, ColumnValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->created_at_as_carbon);
        static::assertEquals($dateTime, $record->created_at_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, ColumnValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, ColumnValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));

        // test value changing and formatted values cache cleanup
        $updatedDate = '2022-01-01';
        $updatedTime = '23:59:59';
        $updatedDateTime = $updatedDate . ' ' . $updatedTime;
        $record->setCreatedAt($updatedDateTime);
        static::assertNotSame($valueContainer, $record->getValueContainer('created_at'));
        static::assertEquals($updatedDateTime, $record->created_at);
        static::assertEquals($updatedDate, $record->created_at_as_date);
        static::assertEquals($updatedTime, $record->created_at_as_time);
        static::assertEquals(strtotime($updatedDateTime), $record->created_at_as_unix_ts);
        static::assertEquals($updatedDateTime, $record->created_at_as_carbon->format('Y-m-d H:i:s'));
    }

    public function testUnixTimestampFormatters(): void
    {
        $formatters = ColumnValueFormatters::getUnixTimestampFormatters();
        static::assertIsArray($formatters);
        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_DATE_TIME => ColumnValueFormatters::getUnixTimestampToDateTimeFormatter(),
                ColumnValueFormatters::FORMAT_DATE => ColumnValueFormatters::getTimestampToDateFormatter(),
                ColumnValueFormatters::FORMAT_TIME => ColumnValueFormatters::getTimestampToTimeFormatter(),
                ColumnValueFormatters::FORMAT_CARBON => ColumnValueFormatters::getTimestampToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_DATE_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_DATE]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_CARBON]);

        $ts = time();
        $record = TestingFormatter::fromArray(['created_at_unix' => $ts]);
        $valueContainer = $record->getValueContainer('created_at_unix');
        static::assertEquals($ts, $record->created_at_unix);
        static::assertEquals($ts, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['created_at_unix' => $ts])
            ->getValueContainer('created_at_unix'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // data-time formatter
        $dateTime = date('Y-m-d H:i:s', $ts);
        static::assertEquals($dateTime, $record->created_at_unix_as_date_time);
        static::assertEquals($dateTime, ColumnValueFormatters::getUnixTimestampToDateTimeFormatter()($valueContainerAlt));
        // date formatter
        $date = date('Y-m-d', $ts);
        static::assertEquals($date, $record->created_at_unix_as_date);
        static::assertEquals($date, ColumnValueFormatters::getTimestampToDateFormatter()($valueContainerAlt));
        // time formatter
        $time = date('H:i:s', $ts);
        static::assertEquals($time, $record->created_at_unix_as_time);
        static::assertEquals($time, ColumnValueFormatters::getTimestampToTimeFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->created_at_unix_as_carbon);
        static::assertEquals($dateTime, $record->created_at_unix_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, ColumnValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, ColumnValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));

        // test value changing and formatted values cache cleanup
        $updatedDate = '2022-01-01';
        $updatedTime = '23:59:59';
        $updatedDateTime = $updatedDate . ' ' . $updatedTime;
        $ts = strtotime($updatedDateTime);
        $record->setCreatedAtUnix($ts);
        static::assertNotSame($valueContainer, $record->getValueContainer('created_at_unix'));
        static::assertEquals($ts, $record->created_at_unix);
        static::assertEquals($updatedDate, $record->created_at_unix_as_date);
        static::assertEquals($updatedTime, $record->created_at_unix_as_time);
        static::assertEquals($updatedDateTime, $record->created_at_unix_as_carbon->format('Y-m-d H:i:s'));
    }

    public function testDateFormatters(): void
    {
        $formatters = ColumnValueFormatters::getDateFormatters();
        static::assertIsArray($formatters);

        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_UNIX_TS => ColumnValueFormatters::getDateTimeToUnixTsFormatter(),
                ColumnValueFormatters::FORMAT_CARBON => ColumnValueFormatters::getDateToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_UNIX_TS]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_CARBON]);

        $date = date('Y-m-d');
        $dateTime = date('Y-m-d 00:00:00');
        $ts = strtotime($dateTime);
        $record = TestingFormatter::fromArray(['creation_date' => $date]);
        $valueContainer = $record->getValueContainer('creation_date');
        static::assertEquals($date, $record->creation_date);
        static::assertEquals($date, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['creation_date' => $dateTime])
            ->getValueContainer('creation_date'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // unix_ts formatter
        static::assertEquals($ts, $record->creation_date_as_unix_ts);
        static::assertEquals($ts, ColumnValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->creation_date_as_carbon);
        static::assertEquals($dateTime, $record->creation_date_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, ColumnValueFormatters::getDateToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, ColumnValueFormatters::getDateToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));
    }

    public function testTimeFormatters(): void
    {
        $formatters = ColumnValueFormatters::getTimeFormatters();
        static::assertIsArray($formatters);

        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_UNIX_TS => ColumnValueFormatters::getDateTimeToUnixTsFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_UNIX_TS]);

        $time = '23:59:59';
        $ts = strtotime('23:59:59');
        $record = TestingFormatter::fromArray(['creation_time' => $time]);
        $valueContainer = $record->getValueContainer('creation_time');
        static::assertEquals($time, $record->creation_time);
        static::assertEquals($time, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['creation_time' => $time])
            ->getValueContainer('creation_time'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // unix_ts formatter
        static::assertEquals($ts, $record->creation_time_as_unix_ts);
        static::assertEquals($ts, ColumnValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
    }

    public function testJsonFormatters1(): void
    {
        $formatters = ColumnValueFormatters::getJsonFormatters();
        static::assertIsArray($formatters);

        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_ARRAY => ColumnValueFormatters::getJsonToDecodedValueFormatter(),
                ColumnValueFormatters::FORMAT_DECODED => ColumnValueFormatters::getJsonToDecodedValueFormatter(),
                ColumnValueFormatters::FORMAT_OBJECT => ColumnValueFormatters::getJsonToObjectFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_ARRAY]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_OBJECT]);

        $data = [
            'key1' => 1,
            'key2' => '2',
            'key3' => true,
            'key4' => null,
            'key5' => 'string',
            'key6' => [
                'subkey' => '',
            ],
            'key7' => [
                1,
                2,
                3,
            ],
        ];
        $json = json_encode($data);
        $record = TestingFormatter::fromArray(['json_data1' => $json]);
        $valueContainer = $record->getValueContainer('json_data1');
        static::assertEquals($json, $record->json_data1);
        static::assertEquals($json, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['json_data1' => $json])
            ->getValueContainer('json_data1'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // array formatter
        static::assertEquals($data, $record->json_data1_as_array);
        static::assertEquals($data, ColumnValueFormatters::getJsonToDecodedValueFormatter()($valueContainerAlt));
        // object formatter
        static::assertInstanceOf(\stdClass::class, $record->json_data1_as_object);
        $object = (object)$data;
        $object->key6 = (object)$object->key6;
        static::assertEquals($object, $record->json_data1_as_object);
        static::assertEquals($object, ColumnValueFormatters::getJsonToObjectFormatter()($valueContainerAlt));

        // test array as incoming value
        $record = TestingFormatter::fromArray(['json_data1' => $data]);
        $valueContainer = $record->getValueContainer('json_data1');
        $valueContainerAlt = TestingFormatter::fromArray(['json_data1' => $json])
            ->getValueContainer('json_data1'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());
        static::assertEquals($json, $record->json_data1);
        static::assertEquals($json, $valueContainerAlt->getValue());
    }

    public function testJsonFormatters2(): void
    {
        $formatters = ColumnValueFormatters::getJsonFormatters();
        static::assertIsArray($formatters);

        static::assertSame(
            [
                ColumnValueFormatters::FORMAT_ARRAY => ColumnValueFormatters::getJsonToDecodedValueFormatter(),
                ColumnValueFormatters::FORMAT_DECODED => ColumnValueFormatters::getJsonToDecodedValueFormatter(),
                ColumnValueFormatters::FORMAT_OBJECT => ColumnValueFormatters::getJsonToObjectFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_ARRAY]);
        static::assertInstanceOf(\Closure::class, $formatters[ColumnValueFormatters::FORMAT_OBJECT]);

        $data = [
            'key1' => 1,
            'key2' => '2',
            'key3' => true,
            'key4' => null,
            'key5' => 'string',
            'key6' => [
                'subkey' => '',
            ],
            'key7' => [
                1,
                2,
                3,
            ],
        ];
        $json = json_encode($data);
        $record = TestingFormatter::fromArray(['json_data2' => $json]);
        $valueContainer = $record->getValueContainer('json_data2');
        static::assertEquals($json, $record->json_data2);
        static::assertEquals($json, $valueContainer->getValue());

        $valueContainerAlt = TestingFormatter::fromArray(['json_data2' => $json])
            ->getValueContainer('json_data2'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());

        // array formatter
        static::assertEquals($data, $record->json_data2_as_array);
        static::assertEquals($data, ColumnValueFormatters::getJsonToDecodedValueFormatter()($valueContainerAlt));
        // object formatter
        static::assertInstanceOf(TestingFormatterJsonObject::class, $record->json_data2_as_object);
        $object = new TestingFormatterJsonObject();
        $object->key1 = $data['key1'];
        $object->key2 = $data['key2'];
        $object->key3 = $data['key3'];
        $object->other = array_diff_key($data, ['key1' => '', 'key2' => '', 'key3' => '']);
        static::assertEquals($object, $record->json_data2_as_object);
        static::assertEquals($object, ColumnValueFormatters::getJsonToObjectFormatter()($valueContainerAlt));

        // test array as incoming value
        $record = TestingFormatter::fromArray(['json_data2' => $data]);
        $valueContainer = $record->getValueContainer('json_data2');
        $valueContainerAlt = TestingFormatter::fromArray(['json_data2' => $json])
            ->getValueContainer('json_data2'); //< needed to avoid formatter cache
        static::assertNotSame($valueContainer, $valueContainerAlt);
        static::assertEquals($valueContainer->getValue(), $valueContainerAlt->getValue());
        static::assertEquals($json, $record->json_data2);
        static::assertEquals($json, $valueContainerAlt->getValue());
    }

    public function testDbExprValueInValueContainer(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('It is impossible to convert PeskyORM\DbExpr instance to anoter format');
        $record = TestingFormatter::newEmptyRecord();
        $record->setCreatedAt(new DbExpr('NOW()'));
        $valueContainer = $record->getValueContainer('created_at');
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidTimestamp1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->created_at contains invalid date-time value: [invalid_date]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at');
        $valueContainer->setValue('invalid_date', 'invalid_date', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidDate1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->creation_date contains invalid date-time value: [invalid_date]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('creation_date');
        $valueContainer->setValue('invalid_date', 'invalid_date', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidTime1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->creation_time contains invalid date-time value: [invalid_time]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('creation_time');
        $valueContainer->setValue('invalid_time', 'invalid_time', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidUnixTs1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->created_at_unix contains invalid date-time value: [invalid_ts]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at_unix');
        $valueContainer->setValue('invalid_ts', 'invalid_ts', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidJson1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->json_data1 contains invalid date-time value: [invalid_json]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('json_data1');
        $valueContainer->setValue('invalid_json', 'invalid_json', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidJson2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->json_data2 contains invalid date-time value: [invalid_json]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('json_data2');
        $valueContainer->setValue('invalid_json', 'invalid_json', false);
        $formatter = ColumnValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }

    public function testInvalidJson3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [json_data1] Value must be a json-encoded string or have jsonable type.");
        TestingFormatter::newEmptyRecord()
            ->setJsonData1(json_decode(json_encode(['test' => 1]), false)); //< \stdObject
    }

    public function testInvalidCustomFormatter1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::addCustomValueFormatter(): Argument #2 ($formatter) must be of type Closure');
        $record = TestingFormatter::newEmptyRecord();
        /** @noinspection PhpParamsInspection */
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', $this);
    }

    public function testInvalidCustomFormatter2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::addCustomValueFormatter(): Argument #2 ($formatter) must be of type Closure');
        $record = TestingFormatter::newEmptyRecord();
        /** @noinspection PhpParamsInspection */
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', null);
    }

    public function testInvalidCustomFormatter3(): void
    {
        // modification of custom formatters can only be done during initial column declaration in TableStructure
        // or before Record::getColumn() or Record::hasColumn() were used
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "There is no column 'json_data1_as_test' in PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormattersTableStructure"
        );
        $record = TestingFormatter::newEmptyRecord();
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', static function () {
            });
        $record->getValue('json_data1_as_test');
    }

    public function testCustomFormatter1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('test');
        TestingFormattersTableStructure::getColumn('json_data1')
            ->addCustomValueFormatter('test', function () {
                throw new \BadMethodCallException('test');
            });
        $record = TestingFormatter::newEmptyRecord();
        $record->getValue('json_data1_as_test');
    }

    public function testCustomFormatter2(): void
    {
        TestingFormattersTableStructure::getColumn('json_data1')
            ->addCustomValueFormatter('test', function (RecordValue $valueContainer) {
                $value = $valueContainer->getRecord()->getValue($valueContainer->getColumn(), 'array');
                $value['test'] = true;
                return $value;
            });
        $record = TestingFormatter::newEmptyRecord();
        $record->setJsonData1('{}');
        static::assertEquals(['test' => true], $record->getValue('json_data1_as_test'));

        $data = ['key1' => 'value1'];
        $record = $record->setJsonData1(['key1' => 'value1']);
        $expected = $data + ['test' => true];
        static::assertEquals($expected, $record->getValue('json_data1_as_test'));
    }
}