<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use Carbon\CarbonImmutable;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueFormatters;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormatter;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormatterJsonObject;
use PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormattersTableStructure;

class RecordValueFormattersTest extends BaseTestCase
{
    
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->callObjectMethod(TestingFormatter::newEmptyRecord(), 'resetColumnsCache');
    }
    
    public function testTimestampFormatters()
    {
        $formatters = RecordValueFormatters::getTimestampFormatters();
        static::assertIsArray($formatters);
        
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_DATE => RecordValueFormatters::getTimestampToDateFormatter(),
                RecordValueFormatters::FORMAT_TIME => RecordValueFormatters::getTimestampToTimeFormatter(),
                RecordValueFormatters::FORMAT_UNIX_TS => RecordValueFormatters::getDateTimeToUnixTsFormatter(),
                RecordValueFormatters::FORMAT_CARBON => RecordValueFormatters::getTimestampToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_DATE]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_UNIX_TS]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_CARBON]);
        
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
        static::assertEquals($date, RecordValueFormatters::getTimestampToDateFormatter()($valueContainerAlt));
        // time formatter
        $time = date('H:i:s', $ts);
        static::assertEquals($time, $record->created_at_as_time);
        static::assertEquals($time, RecordValueFormatters::getTimestampToTimeFormatter()($valueContainerAlt));
        // unix_ts formatter
        static::assertEquals($ts, $record->created_at_as_unix_ts);
        static::assertEquals($ts, RecordValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->created_at_as_carbon);
        static::assertEquals($dateTime, $record->created_at_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, RecordValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, RecordValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));
        
        // test value changing and formatted values cache cleanup
        $updatedDate = '2022-01-01';
        $updatedTime = '23:59:59';
        $updatedDateTime = $updatedDate . ' ' . $updatedTime;
        $record->setCreatedAt($updatedDateTime);
        static::assertSame($valueContainer, $record->getValueContainer('created_at'));
        static::assertEquals($updatedDateTime, $record->created_at);
        static::assertEquals($updatedDate, $record->created_at_as_date);
        static::assertEquals($updatedTime, $record->created_at_as_time);
        static::assertEquals(strtotime($updatedDateTime), $record->created_at_as_unix_ts);
        static::assertEquals($updatedDateTime, $record->created_at_as_carbon->format('Y-m-d H:i:s'));
        static::assertEquals($dateTime, $valueContainer->getOldValue());
    }
    
    public function testUnixTimestampFormatters()
    {
        $formatters = RecordValueFormatters::getUnixTimestampFormatters();
        static::assertIsArray($formatters);
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_DATE_TIME => RecordValueFormatters::getUnixTimestampToDateTimeFormatter(),
                RecordValueFormatters::FORMAT_DATE => RecordValueFormatters::getTimestampToDateFormatter(),
                RecordValueFormatters::FORMAT_TIME => RecordValueFormatters::getTimestampToTimeFormatter(),
                RecordValueFormatters::FORMAT_CARBON => RecordValueFormatters::getTimestampToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_DATE_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_DATE]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_TIME]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_CARBON]);
        
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
        static::assertEquals($dateTime, RecordValueFormatters::getUnixTimestampToDateTimeFormatter()($valueContainerAlt));
        // date formatter
        $date = date('Y-m-d', $ts);
        static::assertEquals($date, $record->created_at_unix_as_date);
        static::assertEquals($date, RecordValueFormatters::getTimestampToDateFormatter()($valueContainerAlt));
        // time formatter
        $time = date('H:i:s', $ts);
        static::assertEquals($time, $record->created_at_unix_as_time);
        static::assertEquals($time, RecordValueFormatters::getTimestampToTimeFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->created_at_unix_as_carbon);
        static::assertEquals($dateTime, $record->created_at_unix_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, RecordValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, RecordValueFormatters::getTimestampToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));
        
        // test value changing and formatted values cache cleanup
        $updatedDate = '2022-01-01';
        $updatedTime = '23:59:59';
        $updatedDateTime = $updatedDate . ' ' . $updatedTime;
        $ts = strtotime($updatedDateTime);
        $record->setCreatedAtUnix($ts);
        static::assertSame($valueContainer, $record->getValueContainer('created_at_unix'));
        static::assertEquals($ts, $record->created_at_unix);
        static::assertEquals($updatedDate, $record->created_at_unix_as_date);
        static::assertEquals($updatedTime, $record->created_at_unix_as_time);
        static::assertEquals($updatedDateTime, $record->created_at_unix_as_carbon->format('Y-m-d H:i:s'));
    }
    
    public function testDateFormatters()
    {
        $formatters = RecordValueFormatters::getDateFormatters();
        static::assertIsArray($formatters);
        
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_UNIX_TS => RecordValueFormatters::getDateTimeToUnixTsFormatter(),
                RecordValueFormatters::FORMAT_CARBON => RecordValueFormatters::getDateToCarbonFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_UNIX_TS]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_CARBON]);
        
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
        static::assertEquals($ts, RecordValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
        // carbon formatter
        static::assertInstanceOf(CarbonImmutable::class, $record->creation_date_as_carbon);
        static::assertEquals($dateTime, $record->creation_date_as_carbon->format('Y-m-d H:i:s'));
        static::assertInstanceOf(CarbonImmutable::class, RecordValueFormatters::getDateToCarbonFormatter()($valueContainerAlt));
        static::assertEquals($dateTime, RecordValueFormatters::getDateToCarbonFormatter()($valueContainerAlt)->format('Y-m-d H:i:s'));
    }
    
    public function testTimeFormatters()
    {
        $formatters = RecordValueFormatters::getTimeFormatters();
        static::assertIsArray($formatters);
        
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_UNIX_TS => RecordValueFormatters::getDateTimeToUnixTsFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_UNIX_TS]);
        
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
        static::assertEquals($ts, RecordValueFormatters::getDateTimeToUnixTsFormatter()($valueContainerAlt));
    }
    
    public function testJsonFormatters1()
    {
        $formatters = RecordValueFormatters::getJsonFormatters();
        static::assertIsArray($formatters);
        
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_ARRAY => RecordValueFormatters::getJsonToArrayFormatter(),
                RecordValueFormatters::FORMAT_OBJECT => RecordValueFormatters::getJsonToObjectFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_ARRAY]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_OBJECT]);
        
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
        static::assertEquals($data, RecordValueFormatters::getJsonToArrayFormatter()($valueContainerAlt));
        // object formatter
        static::assertInstanceOf(\stdClass::class, $record->json_data1_as_object);
        $object = (object)$data;
        $object->key6 = (object)$object->key6;
        static::assertEquals($object, $record->json_data1_as_object);
        static::assertEquals($object, RecordValueFormatters::getJsonToObjectFormatter()($valueContainerAlt));
        
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
    
    public function testJsonFormatters2()
    {
        $formatters = RecordValueFormatters::getJsonFormatters();
        static::assertIsArray($formatters);
        
        static::assertSame(
            [
                RecordValueFormatters::FORMAT_ARRAY => RecordValueFormatters::getJsonToArrayFormatter(),
                RecordValueFormatters::FORMAT_OBJECT => RecordValueFormatters::getJsonToObjectFormatter(),
            ],
            $formatters
        );
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_ARRAY]);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_OBJECT]);
        
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
        static::assertEquals($data, RecordValueFormatters::getJsonToArrayFormatter()($valueContainerAlt));
        // object formatter
        static::assertInstanceOf(TestingFormatterJsonObject::class, $record->json_data2_as_object);
        $object = new TestingFormatterJsonObject();
        $object->key1 = $data['key1'];
        $object->key2 = $data['key2'];
        $object->key3 = $data['key3'];
        $object->other = array_diff_key($data, ['key1' => '', 'key2' => '', 'key3' => '']);
        static::assertEquals($object, $record->json_data2_as_object);
        static::assertEquals($object, RecordValueFormatters::getJsonToObjectFormatter()($valueContainerAlt));
        
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
    
    public function testDbExprValueInValueContainer()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('It is impossible to convert PeskyORM\Core\DbExpr object to anoter format');
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidTimestamp1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->created_at contains invalid date-time value: [invalid_date]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at');
        $valueContainer->setRawValue('invalid_date', 'invalid_date', false);
        $valueContainer->setValidValue('invalid_date', 'invalid_date');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidDate1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->creation_date contains invalid date-time value: [invalid_date]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('creation_date');
        $valueContainer->setRawValue('invalid_date', 'invalid_date', false);
        $valueContainer->setValidValue('invalid_date', 'invalid_date');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidTime1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->creation_time contains invalid date-time value: [invalid_time]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('creation_time');
        $valueContainer->setRawValue('invalid_time', 'invalid_time', false);
        $valueContainer->setValidValue('invalid_time', 'invalid_time');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidUnixTs1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->created_at_unix contains invalid date-time value: [invalid_ts]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at_unix');
        $valueContainer->setRawValue('invalid_ts', 'invalid_ts', false);
        $valueContainer->setValidValue('invalid_ts', 'invalid_ts');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidJson1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->json_data1 contains invalid date-time value: [invalid_json]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('json_data1');
        $valueContainer->setRawValue('invalid_json', 'invalid_json', false);
        $valueContainer->setValidValue('invalid_json', 'invalid_json');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidJson2()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->json_data2 contains invalid date-time value: [invalid_json]"
        );
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('json_data2');
        $valueContainer->setRawValue('invalid_json', 'invalid_json', false);
        $valueContainer->setValidValue('invalid_json', 'invalid_json');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
    
    public function testInvalidJson3()
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [json_data1] Value must be a json-encoded string or array.");
        TestingFormatter::newEmptyRecord()
            ->setJsonData1(json_decode(json_encode(['test' => 1]), false)); //< \stdObject
    }
    
    public function testInvalidCustomFormatter1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::addCustomValueFormatter(): Argument #2 ($formatter) must be of type Closure');
        $record = TestingFormatter::newEmptyRecord();
        /** @noinspection PhpParamsInspection */
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', $this);
    }
    
    public function testInvalidCustomFormatter2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::addCustomValueFormatter(): Argument #2 ($formatter) must be of type Closure');
        $record = TestingFormatter::newEmptyRecord();
        /** @noinspection PhpParamsInspection */
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', null);
    }
    
    public function testInvalidCustomFormatter3()
    {
        // modification of custom formatters can only be done during initial column declaration in TableStructure
        // or before Record::getColumn() or Record::hasColumn() were used
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "There is no column 'json_data1_as_test' in PeskyORM\Tests\PeskyORMTest\TestingFormatters\TestingFormattersTableStructure"
        );
        $record = TestingFormatter::newEmptyRecord();
        $record::getColumn('json_data1')
            ->addCustomValueFormatter('test', function () {
            });
        $record->getValue('json_data1_as_test');
    }
    
    public function testCustomFormatter1()
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
    
    public function testCustomFormatter2()
    {
        TestingFormattersTableStructure::getColumn('json_data1')
            ->addCustomValueFormatter('test', function (RecordValue $valueContainer) {
                $value = $valueContainer->getRecord()->getValue($valueContainer->getColumn(), 'array');
                $value['test'] = true;
                return $value;
            });
        $record = TestingFormatter::newEmptyRecord();
        static::assertEquals(['test' => true], $record->getValue('json_data1_as_test'));
        
        $data = ['key1' => 'value1'];
        $record = $record->setJsonData1(['key1' => 'value1']);
        $expected = $data + ['test' => true];
        static::assertEquals($expected, $record->getValue('json_data1_as_test'));
    }
}