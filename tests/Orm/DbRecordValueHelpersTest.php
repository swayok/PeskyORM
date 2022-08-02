<?php

declare(strict_types=1);

namespace Tests\Orm;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use SplFileInfo;
use Swayok\Utils\NormalizeValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingSettings\TestingSetting;

class DbRecordValueHelpersTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        date_default_timezone_set('UTC');
        parent::setUpBeforeClass();
    }
    
    /**
     * @param string $type
     * @param mixed $value
     * @return RecordValue
     */
    private function createDbRecordValue($type, $value)
    {
        $obj = RecordValue::create(Column::create($type, 'test'), TestingSetting::_());
        $obj->setRawValue($value, $value, true)
            ->setValidValue($value, $value);
        return $obj;
    }
    
    public function testGetErrorMessage()
    {
        static::assertEquals('test', RecordValueHelpers::getErrorMessage([], 'test'));
        static::assertEquals('not-a-test', RecordValueHelpers::getErrorMessage(['test' => 'not-a-test'], 'test'));
    }
    
    public function testPreprocessValue()
    {
        $column = Column::create(Column::TYPE_STRING, 'test');
        static::assertFalse($column->isValueTrimmingRequired());
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        static::assertFalse($column->isValueLowercasingRequired());
        static::assertEquals(' ', RecordValueHelpers::preprocessColumnValue($column, ' ', false, false));
        $column->trimsValue();
        static::assertTrue($column->isValueTrimmingRequired());
        static::assertEquals('', RecordValueHelpers::preprocessColumnValue($column, ' ', false, false));
        static::assertEquals('A', RecordValueHelpers::preprocessColumnValue($column, ' A ', false, false));
        $column->convertsEmptyStringToNull();
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        static::assertEquals(null, RecordValueHelpers::preprocessColumnValue($column, null, false, false));
        static::assertEquals(null, RecordValueHelpers::preprocessColumnValue($column, ' ', false, false));
        static::assertEquals('A', RecordValueHelpers::preprocessColumnValue($column, ' A ', false, false));
        $column->lowercasesValue();
        static::assertTrue($column->isValueLowercasingRequired());
        static::assertEquals('upper', RecordValueHelpers::preprocessColumnValue($column, 'UPPER', false, false));
    }
    
    public function testNormalizeBoolValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue('1', Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue(true, Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue('true', Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue('false', Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue(1, Column::TYPE_BOOL));
        static::assertTrue(RecordValueHelpers::normalizeValue(2, Column::TYPE_BOOL));
        static::assertFalse(RecordValueHelpers::normalizeValue([], Column::TYPE_BOOL));
        static::assertFalse(RecordValueHelpers::normalizeValue('', Column::TYPE_BOOL));
        static::assertFalse(RecordValueHelpers::normalizeValue(0, Column::TYPE_BOOL));
        static::assertFalse(RecordValueHelpers::normalizeValue('0', Column::TYPE_BOOL));
        static::assertFalse(RecordValueHelpers::normalizeValue(false, Column::TYPE_BOOL));
    }
    
    public function testNormalizeIntAndUnixTimestampValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_INT));
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(1, RecordValueHelpers::normalizeValue('1', Column::TYPE_INT));
        static::assertEquals(2, RecordValueHelpers::normalizeValue('2', Column::TYPE_INT));
        static::assertEquals(1, RecordValueHelpers::normalizeValue(1, Column::TYPE_INT));
        static::assertEquals(0, RecordValueHelpers::normalizeValue('aaa', Column::TYPE_INT));
        static::assertEquals(1, RecordValueHelpers::normalizeValue('1a', Column::TYPE_INT));
        static::assertEquals(0, RecordValueHelpers::normalizeValue('s1', Column::TYPE_INT));
        static::assertEquals(2, RecordValueHelpers::normalizeValue('2.25', Column::TYPE_INT));
        static::assertEquals(2, RecordValueHelpers::normalizeValue(2.25, Column::TYPE_INT));
        static::assertEquals(1, RecordValueHelpers::normalizeValue('1.99', Column::TYPE_INT));
        static::assertEquals(1, RecordValueHelpers::normalizeValue(1.99, Column::TYPE_INT));
        static::assertEquals(time(), RecordValueHelpers::normalizeValue(time(), Column::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(time(), RecordValueHelpers::normalizeValue(time() . '', Column::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(0, RecordValueHelpers::normalizeValue('sasd', Column::TYPE_UNIX_TIMESTAMP));
    }
    
    public function testNormalizeFloatValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_FLOAT));
        static::assertEquals(1.00, RecordValueHelpers::normalizeValue('1', Column::TYPE_FLOAT));
        static::assertEquals(2.00, RecordValueHelpers::normalizeValue('2', Column::TYPE_FLOAT));
        static::assertEquals(2.1, RecordValueHelpers::normalizeValue('2.1', Column::TYPE_FLOAT));
        static::assertEquals(3.1999999, RecordValueHelpers::normalizeValue('3.1999999', Column::TYPE_FLOAT));
        static::assertEquals(1.00, RecordValueHelpers::normalizeValue(1.00, Column::TYPE_FLOAT));
        static::assertEquals(0.00, RecordValueHelpers::normalizeValue('aaa', Column::TYPE_FLOAT));
        static::assertEquals(0, RecordValueHelpers::normalizeValue('aaa', Column::TYPE_FLOAT));
        static::assertEquals(1.00, RecordValueHelpers::normalizeValue('1a', Column::TYPE_FLOAT));
        static::assertEquals(1.1, RecordValueHelpers::normalizeValue('1.1a9', Column::TYPE_FLOAT));
        static::assertEquals(0, RecordValueHelpers::normalizeValue('s1', Column::TYPE_FLOAT));
    }
    
    public function testNormalizeDateValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_DATE));
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT),
            RecordValueHelpers::normalizeValue(time(), Column::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 3600),
            RecordValueHelpers::normalizeValue('+1 hour', Column::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 86400),
            RecordValueHelpers::normalizeValue('+1 day', Column::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, 0),
            RecordValueHelpers::normalizeValue(0, Column::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            RecordValueHelpers::normalizeValue('2016-09-01', Column::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            RecordValueHelpers::normalizeValue('01-09-2016', Column::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:00', Column::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:59', Column::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-02',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:60', Column::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', Column::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            RecordValueHelpers::normalizeValue('qqq', Column::TYPE_DATE)
        );
    }
    
    public function testNormalizeTimeValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_TIME));
        $now = time();
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, $now),
            RecordValueHelpers::normalizeValue($now, Column::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:00', Column::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            RecordValueHelpers::normalizeValue(0, Column::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            RecordValueHelpers::normalizeValue('qqq', Column::TYPE_TIME)
        );
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, time() + 3600),
            RecordValueHelpers::normalizeValue('+1 hour', Column::TYPE_TIME)
        );
        static::assertEquals(
            '01:56:00',
            RecordValueHelpers::normalizeValue('01:56', Column::TYPE_TIME)
        );
    }
    
    public function testNormalizeDateTimeValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_TIMESTAMP));
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT),
            RecordValueHelpers::normalizeValue(time(), Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 3600),
            RecordValueHelpers::normalizeValue('+1 hour', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 86400),
            RecordValueHelpers::normalizeValue('+1 day', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, 0),
            RecordValueHelpers::normalizeValue(0, Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            RecordValueHelpers::normalizeValue('2016-09-01', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            RecordValueHelpers::normalizeValue('01-09-2016', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:01',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:01', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 23:59:59',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:59', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-02 00:00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:60', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', Column::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            RecordValueHelpers::normalizeValue('qqq', Column::TYPE_TIMESTAMP)
        );
    }
    
    public function testNormalizeDateTimeWithTzValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT),
            RecordValueHelpers::normalizeValue(time(), Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 3600),
            RecordValueHelpers::normalizeValue('+1 hour', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 86400),
            RecordValueHelpers::normalizeValue('+1 day', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, 0),
            RecordValueHelpers::normalizeValue(0, Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            RecordValueHelpers::normalizeValue('2016-09-01', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            RecordValueHelpers::normalizeValue('01-09-2016', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:01 +00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:01', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 23:59:59 +00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:59', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-02 00:00:00 +00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:60', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            RecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            RecordValueHelpers::normalizeValue('qqq', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('Europe/Moscow');
        static::assertEquals(
            '2016-09-01 23:59:59 +03:00',
            RecordValueHelpers::normalizeValue('01-09-2016 23:59:59', Column::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('UTC');
    }
    
    public function testNormalizeJsonValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_JSON));
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_JSONB));
        static::assertEquals('[]', RecordValueHelpers::normalizeValue([], Column::TYPE_JSON));
        static::assertEquals('["a"]', RecordValueHelpers::normalizeValue(['a'], Column::TYPE_JSON));
        static::assertEquals('[]', RecordValueHelpers::normalizeValue('[]', Column::TYPE_JSON));
        static::assertEquals('{"a":"b"}', RecordValueHelpers::normalizeValue(['a' => 'b'], Column::TYPE_JSONB));
        static::assertEquals('{"a":"b"}', RecordValueHelpers::normalizeValue('{"a":"b"}', Column::TYPE_JSONB));
        static::assertEquals('"string"', RecordValueHelpers::normalizeValue('string', Column::TYPE_JSON));
        static::assertEquals('string', json_decode('"string"', true));
        static::assertEquals('true', RecordValueHelpers::normalizeValue(true, Column::TYPE_JSON));
        static::assertEquals(true, json_decode('true', true));
        static::assertEquals('false', RecordValueHelpers::normalizeValue(false, Column::TYPE_JSON));
        static::assertEquals(false, json_decode('false', true));
        static::assertEquals('1', RecordValueHelpers::normalizeValue(1, Column::TYPE_JSON));
        static::assertEquals(1, json_decode('1', true));
        static::assertEquals('"10"', RecordValueHelpers::normalizeValue('10', Column::TYPE_JSON));
        static::assertEquals('10', json_decode('"10"', true));
        static::assertEquals('"10.1"', RecordValueHelpers::normalizeValue('10.1', Column::TYPE_JSON));
        static::assertEquals('10.1', json_decode('"10.1"', true));
        static::assertEquals('10.1', RecordValueHelpers::normalizeValue(10.1, Column::TYPE_JSON));
        static::assertEquals(10.1, json_decode('10.1', true));
    }
    
    public function testNormalizeFileAndImageValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_FILE));
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_IMAGE));
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $normalized = RecordValueHelpers::normalizeValue($file, Column::TYPE_FILE);
        static::assertInstanceOf(UploadedFile::class, $normalized);
        static::assertEquals($file['tmp_name'], $normalized->getPathname());
        static::assertEquals($file['name'], $normalized->getClientOriginalName());
        static::assertEquals('jpg', $normalized->getClientOriginalExtension());
        static::assertEquals('image/jpeg', $normalized->getMimeType());
        static::assertEquals($file['size'], $normalized->getSize());
        static::assertEquals($file['error'], $normalized->getError());
        
        $normalized2 = RecordValueHelpers::normalizeValue($file, Column::TYPE_IMAGE);
        static::assertInstanceOf(UploadedFile::class, $normalized2);
        static::assertEquals('image/jpeg', $normalized->getMimeType());
        
        $normalized3 = RecordValueHelpers::normalizeValue($normalized, Column::TYPE_IMAGE);
        static::assertEquals($normalized, $normalized3);
    }
    
    public function testNormalizeStringValue()
    {
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_IPV4_ADDRESS));
        static::assertNull(RecordValueHelpers::normalizeValue(null, Column::TYPE_STRING));
        static::assertEquals('string', RecordValueHelpers::normalizeValue('string', Column::TYPE_STRING));
        static::assertEquals('1111', RecordValueHelpers::normalizeValue(1111, Column::TYPE_STRING));
        static::assertEquals('', RecordValueHelpers::normalizeValue(false, Column::TYPE_STRING));
        static::assertEquals('0', RecordValueHelpers::normalizeValue(0, Column::TYPE_STRING));
        static::assertEquals('1', RecordValueHelpers::normalizeValue(true, Column::TYPE_STRING));
        static::assertEquals('string', RecordValueHelpers::normalizeValue('string', Column::TYPE_IPV4_ADDRESS));
    }
    
    public function testGetValueFormatterAndFormatsByTypeForTimestamps()
    {
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_UNIX_TIMESTAMP);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts', 'carbon'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
        
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_TIMESTAMP);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts', 'carbon'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
        
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_TIMESTAMP_WITH_TZ);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts', 'carbon'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }
    
    public function testGetValueFormatterAndFormatsByTypeForDateAndTime()
    {
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_DATE);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['unix_ts', 'carbon'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
        
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_TIME);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }
    
    public function testGetValueFormatterAndFormatsByTypeForJson()
    {
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_JSON);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['array', 'object'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
        
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_JSONB);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals(['array', 'object'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }
    
    public function testGetValueFormatterAndFormatsByTypeForOthers()
    {
        $ret = RecordValueHelpers::getValueFormatterAndFormatsByType(Column::TYPE_STRING);
        static::assertNotEmpty($ret);
        static::assertIsArray($ret);
        static::assertCount(2, $ret);
        static::assertEquals([], $ret[1]);
        static::assertNull($ret[0]);
    }
    
    public function testInvalidFormatTimestamp1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$format) must be of type string");
        /** @noinspection PhpStrictTypeCheckingInspection */
        RecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(Column::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            []
        );
    }
    
    public function testInvalidFormatTimestamp2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$format) must be of type string");
        /** @noinspection PhpStrictTypeCheckingInspection */
        RecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(Column::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            null
        );
    }
    
    public function testInvalidFormatTimestamp3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$format) must be of type string");
        /** @noinspection PhpStrictTypeCheckingInspection */
        RecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(Column::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            true
        );
    }
    
    public function testInvalidFormatTimestamp4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Requested value format 'not_existing_format' is not implemented");
        RecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(Column::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            'not_existing_format'
        );
    }
    
    public function testFormatTimestamp()
    {
        $valueObj = $this->createDbRecordValue(Column::TYPE_TIMESTAMP, '2016-09-01 01:02:03');
        static::assertEquals('2016-09-01', RecordValueHelpers::formatTimestamp($valueObj, 'date'));
        static::assertEquals('01:02:03', RecordValueHelpers::formatTimestamp($valueObj, 'time'));
        static::assertEquals(strtotime($valueObj->getValue()), RecordValueHelpers::formatTimestamp($valueObj, 'unix_ts'));
    }
    
    public function testFormatDateOrTime()
    {
        $valueObj = $this->createDbRecordValue(Column::TYPE_DATE, '2016-09-01');
        static::assertEquals(strtotime($valueObj->getValue()), RecordValueHelpers::formatDate($valueObj, 'unix_ts'));
        
        $valueObj = $this->createDbRecordValue(Column::TYPE_TIME, '12:34:56');
        static::assertEquals(strtotime($valueObj->getValue()), RecordValueHelpers::formatDate($valueObj, 'unix_ts'));
    }
    
    public function testFormatJson()
    {
        $value = ['test' => 'value', 'val'];
        $valueObj = $this->createDbRecordValue(Column::TYPE_JSON, json_encode($value));
        static::assertEquals($value, RecordValueHelpers::formatJson($valueObj, 'array'));
        static::assertEquals(json_decode(json_encode($value)), RecordValueHelpers::formatJson($valueObj, 'object'));
        
        $valueObj = $this->createDbRecordValue(Column::TYPE_JSONB, '"invalidjson');
        static::assertEquals(false, RecordValueHelpers::formatJson($valueObj, 'array'));
        static::assertEquals(false, RecordValueHelpers::formatJson($valueObj, 'object'));
    }
    
    public function testIsValueFitsDataTypeBool()
    {
        $message = ['value_must_be_boolean'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_BOOL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_BOOL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_BOOL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_BOOL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_BOOL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.0, Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1.0', Column::TYPE_BOOL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0.0, Column::TYPE_BOOL, false));
        static::assertEquals(['bool'], RecordValueHelpers::isValueFitsDataType('0.0', Column::TYPE_BOOL, false, ['value_must_be_boolean' => 'bool']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_BOOL, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_BOOL, true));
    }
    
    public function testIsValueFitsDataTypeInt()
    {
        $message = ['value_must_be_integer'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(11, Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-11, Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11', Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-11', Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.0, Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.0000, Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.0', Column::TYPE_INT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.0000', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0.1, Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.1', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('a1', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1a', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_INT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_INT, false));
        static::assertEquals(['int'], RecordValueHelpers::isValueFitsDataType([], Column::TYPE_INT, false, ['value_must_be_integer' => 'int']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(11, Column::TYPE_INT, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11', Column::TYPE_INT, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_INT, true));
    }
    
    public function testIsValueFitsDataTypeFloat()
    {
        $message = ['value_must_be_float'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(11, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-11, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-11', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(11.2, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11.2', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-11.3, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-11.3', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.0, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.01, Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.01', Column::TYPE_FLOAT, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.001', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('a1', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1a', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_FLOAT, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_FLOAT, false));
        static::assertEquals(['float'], RecordValueHelpers::isValueFitsDataType([], Column::TYPE_FLOAT, false, ['value_must_be_float' => 'float']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11.2', Column::TYPE_FLOAT, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(11.2, Column::TYPE_FLOAT, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_FLOAT, true));
    }
    
    public function testIsValueFitsDataTypeDate()
    {
        $message = ['value_must_be_date'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(time(), Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+1 day', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_DATE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01092016', Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_DATE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_DATE, false));
        static::assertEquals(['date'], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_DATE, false, ['value_must_be_date' => 'date']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_DATE, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_DATE, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_DATE, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_DATE, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_DATE, true));
    }
    
    public function testIsValueFitsDataTypeTime()
    {
        $message = ['value_must_be_time'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(time(), Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+1 hour', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_TIME, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01092016', Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_TIME, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_TIME, false));
        static::assertEquals(['time'], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_TIME, false, ['value_must_be_time' => 'time']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIME, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIME, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIME, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIME, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIME, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIME, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIME, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIME, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIME, true));
    }
    
    public function testIsValueFitsDataTypeTimestamp()
    {
        $message = ['value_must_be_timestamp'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(time(), Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+1 hour', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_TIMESTAMP, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01092016', Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_TIMESTAMP, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_TIMESTAMP, false));
        static::assertEquals(['timestamp'],
            RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_TIMESTAMP, false, ['value_must_be_timestamp' => 'timestamp']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIMESTAMP, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMESTAMP, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIMESTAMP, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIMESTAMP, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMESTAMP, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIMESTAMP, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIMESTAMP, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIMESTAMP, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIMESTAMP, true));
    }
    
    public function testIsValueFitsDataTypeTimestampWithTz()
    {
        $message = ['value_must_be_timestamp_with_tz'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+1 hour', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(time(), Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('01092016', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals(
            ['wtz'],
            RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_TIMESTAMP_WITH_TZ, false, ['value_must_be_timestamp_with_tz' => 'wtz'])
        );
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(time(), Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('01092016', Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIMESTAMP_WITH_TZ, true));
    }
    
    public function testIsValueFitsDataTypeTimezoneOffset()
    {
        $message = ['value_must_be_timezone_offset'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22:33', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('11:22', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+18:00', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(44200, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('44200', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-18:00', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-44200, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-44200', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(0, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-1', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(90000, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('+1 hour', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals(
            ['offset'],
            RecordValueHelpers::isValueFitsDataType([], Column::TYPE_TIMEZONE_OFFSET, false, ['value_must_be_timezone_offset' => 'offset'])
        );
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(44200, Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('44200', Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-18:00', Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-44200, Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-44200', Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('now', Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('2016-09-01', Column::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', Column::TYPE_TIMEZONE_OFFSET, true));
    }
    
    public function testIsValueFitsDataTypeIpV4Address()
    {
        $message = ['value_must_be_ipv4_address'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('192.168.0.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0.0.0.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('255.255.255.255', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1.1.1.1/24', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('-1.0.0.1', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.-1.0.1', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.0.-1.1', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.0.0.-1', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('255.255.255.256', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('255.255.256.255', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('255.256.255.255', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('256.255.255.255', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('*.*.*.*', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('a.0.0.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.a.0.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.0.a.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('0.0.0.a', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.0, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1.0', Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0.0, Column::TYPE_IPV4_ADDRESS, false));
        static::assertEquals(['ip'],
            RecordValueHelpers::isValueFitsDataType('0.0', Column::TYPE_IPV4_ADDRESS, false, ['value_must_be_ipv4_address' => 'ip']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('192.168.0.0', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0.0.0.0', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('255.255.255.255', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.1.1.1/24', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-1.0.0.1', Column::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0.-1.0.1', Column::TYPE_IPV4_ADDRESS, true));
    }
    
    public function testIsValueFitsDataTypeJson()
    {
        $message = ['value_must_be_json'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType([], Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(['a' => 'b'], Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.11, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1.11, Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-1', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1.11', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-1.11', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('"-1.11"', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('"1.11"', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('[]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('["a"]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('[1]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('["a","b"]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('["a", "b"]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('["a", "b" ]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('[ "a", "b" ]', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{}', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{"a":1.11}', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{ "a":1.11}', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{ "a":1.11 }', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{ "a" :1.11 }', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{ "a" : 1.11 }', Column::TYPE_JSON, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('{1:1.11}', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('{"a":}', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('{"a":"b",}', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('{:"a"}', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('[a]', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('["a",]', Column::TYPE_JSON, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('["a":"b"]', Column::TYPE_JSON, false));
        static::assertEquals(['json'], RecordValueHelpers::isValueFitsDataType('["a":]', Column::TYPE_JSON, false, ['value_must_be_json' => 'json']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-1.11', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('"-1.11"', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('"1.11"', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('[]', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('["a"]', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{1:1.11}', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{"a":}', Column::TYPE_JSON, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('{"a":"b",}', Column::TYPE_JSON, true));
    }
    
    public function testIsValueFitsDataTypeEmail()
    {
        $message = ['value_must_be_email'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('test.test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+test.test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('-test.test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('`test.test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('.test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('[test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(']test@email.ru', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('1.0', Column::TYPE_EMAIL, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(0.0, Column::TYPE_EMAIL, false));
        static::assertEquals(['email'],
            RecordValueHelpers::isValueFitsDataType('0.0', Column::TYPE_EMAIL, false, ['value_must_be_email' => 'email']));
        // for conditions
        $altMessage = ['value_must_be_string'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('test.test@email.ru', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('+test.test@email.ru', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('[test@email.ru', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('.test@email.ru', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('email.ru', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('test', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_EMAIL, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0.0', Column::TYPE_EMAIL, true));
        static::assertEquals($altMessage, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_EMAIL, true));
        static::assertEquals($altMessage, RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_EMAIL, true));
        static::assertEquals($altMessage, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_EMAIL, true));
        static::assertEquals($altMessage, RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_EMAIL, true));
        static::assertEquals($altMessage, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_EMAIL, true));
    }
    
    public function testIsValueFitsDataTypeString()
    {
        $message = ['value_must_be_string'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('', Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_STRING, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_STRING, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_STRING, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_STRING, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_STRING, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1.25, Column::TYPE_STRING, false));
        static::assertEquals(['string'], RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_STRING, false, ['value_must_be_string' => 'string']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('', Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_STRING, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_STRING, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_STRING, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_STRING, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_STRING, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_STRING, true));
        static::assertEquals(['string'], RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_STRING, true, ['value_must_be_string' => 'string']));
    }
    
    public function testIsValueFitsDataTypeEnum()
    {
        $message = ['value_must_be_string_or_numeric'];
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('', Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_ENUM, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1.25, Column::TYPE_ENUM, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_ENUM, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_ENUM, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_ENUM, false));
        static::assertEquals(['enum'],
            RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_ENUM, false, ['value_must_be_string_or_numeric' => 'enum']));
        // for conditions
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('str', Column::TYPE_ENUM, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('', Column::TYPE_ENUM, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('1', Column::TYPE_ENUM, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType('0', Column::TYPE_ENUM, true));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_ENUM, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_ENUM, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_ENUM, true));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_ENUM, true));
    }
    
    public function testIsValueFitsDataTypeUploadedFile()
    {
        $message = ['value_must_be_file'];
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $validUploadedFileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']); //< is_uploaded_file() will fail
        $validFileObj = new SplFileInfo($file['tmp_name']);
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_FILE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($validFileObj, Column::TYPE_FILE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($validUploadedFileObj, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(['not_a_file'], Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(new \stdClass(), Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('', Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('true', Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType('false', Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(true, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(false, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(null, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType([], Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(-1, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(1.25, Column::TYPE_FILE, false));
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType(-1.0, Column::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['tmp_name']);
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['name']);
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['type']);
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['size']);
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['error']);
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['size'] = 0;
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['size'] = -1;
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['error'] = 1;
        static::assertEquals($message, RecordValueHelpers::isValueFitsDataType($badFile, Column::TYPE_FILE, false));
        $badFileObj = new UploadedFile($badFile['tmp_name'] . 'asd', $badFile['name'], $badFile['type'], $badFile['error']);
        static::assertEquals(['file'],
            RecordValueHelpers::isValueFitsDataType($badFileObj, Column::TYPE_FILE, false, ['value_must_be_file' => 'file']));
    }
    
    public function testIsValueFitsDataTypeUploadedImage()
    {
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $validUploadedFileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']); //< is_uploaded_file() will fail
        $validFileObj = new SplFileInfo($file['tmp_name']);
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_FILE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($validFileObj, Column::TYPE_IMAGE, false));
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($validUploadedFileObj, Column::TYPE_IMAGE, false));
        static::assertEquals(['value_must_be_image'], RecordValueHelpers::isValueFitsDataType(['not_a_file'], Column::TYPE_IMAGE, false));
        static::assertEquals(['value_must_be_image'], RecordValueHelpers::isValueFitsDataType(new \stdClass(), Column::TYPE_IMAGE, false));
        $file['name'] = 'image_jpg';
        $file['type'] = 'image/jpeg';
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        $file['type'] = 'image/png';
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        $file['type'] = 'image/gif';
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        $file['type'] = 'image/svg';
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        
        $file['type'] = 'text/plain';
        $file['tmp_name'] = __DIR__ . '/files/test_file_jpg'; //< mime autodetection will solve this
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false));
        
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
        static::assertEquals([], RecordValueHelpers::isValueFitsDataType($fileObj, Column::TYPE_IMAGE, false));
    
        $file['tmp_name'] = __DIR__ . '/files/test_file.docx';
        static::assertEquals(['image'], RecordValueHelpers::isValueFitsDataType($file, Column::TYPE_IMAGE, false, ['value_must_be_image' => 'image']));
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
        static::assertEquals(['image'], RecordValueHelpers::isValueFitsDataType($fileObj, Column::TYPE_IMAGE, false, ['value_must_be_image' => 'image']));
    }
    
    public function testIsValidDbColumnValue()
    {
        $column = Column::create(Column::TYPE_STRING, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, 'test', false, false));
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, '', false, false));
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, \PeskyORM\Core\DbExpr::create('test'), false, false));
        static::assertEquals(
            ['not null'],
            RecordValueHelpers::isValidDbColumnValue($column, null, false, false, ['value_cannot_be_null' => 'not null'])
        );
        $column->convertsEmptyStringToNull();
        static::assertEquals(['value_cannot_be_null'], RecordValueHelpers::isValidDbColumnValue($column, '', false, false));
        $column->allowsNullValues();
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, null, false, false));
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, '', false, false));
        static::assertEquals([], RecordValueHelpers::isValidDbColumnValue($column, \PeskyORM\Core\DbExpr::create('test'), false, false));
        // invalid valie
        $column = Column::create(Column::TYPE_INT, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals(['value_must_be_integer'], RecordValueHelpers::isValidDbColumnValue($column, 'not_int', false, false));
    }
    
    public function testInvalidColumnAllowedValuesForEnum()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Enum column [test] is required to have a list of allowed values");
        $column = Column::create(Column::TYPE_ENUM, 'test');
        RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false);
    }
    
    public function testInvalidValueForAllowedValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = Column::create(Column::TYPE_ENUM, 'test')
            ->setAllowedValues(['test']);
        /** @noinspection PhpParamsInspection */
        RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column, false);
    }
    
    public function testInvalidValueForAllowedValues2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = Column::create(Column::TYPE_STRING, 'test')
            ->setAllowedValues(['test']);
        /** @noinspection PhpParamsInspection */
        RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column, false);
    }
    
    public function testInvalidValueForAllowedValues3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = Column::create(Column::TYPE_ENUM, 'test')
            ->setAllowedValues(['test'])
            ->disallowsNullValues();
        RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false);
    }
    
    public function testInvalidValueForAllowedValues4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = Column::create(Column::TYPE_STRING, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['test']);
        RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false);
    }
    
    public function testIsValueWithinTheAllowedValuesOfTheEnumColumn()
    {
        $message1 = ['value_is_not_allowed'];
        $message2 = ['one_of_values_is_not_allowed'];
        $column = Column::create(Column::TYPE_ENUM, 'test')
            ->allowsNullValues()
            ->setAllowedValues(['test', 'test2']);
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2', false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false));
        static::assertEquals($message1, RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups', false));
        static::assertEquals($message2, RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups'], false));
        static::assertEquals($message2, RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'ups'], false));
        // column is nullable so it converts empty string to null -> there should not be any errors with empty string
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
        $column->setConvertEmptyStringToNull(false);
        // and now it will fail
        static::assertEquals($message1, RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
    }
    
    public function testIsValueWithinTheAllowedValuesOfTheNotEnumColumn()
    {
        $column = Column::create(Column::TYPE_STRING, 'test')
            ->allowsNullValues()
            ->setAllowedValues(['test', 'test2']);
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2', false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test'], false));
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false));
        static::assertEquals(
            ['value_is_not_allowed'],
            RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups', false)
        );
        static::assertEquals(
            ['one_of_values_is_not_allowed'],
            RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups'], false)
        );
        static::assertEquals(
            ['bad value'],
            RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn(
                $column,
                ['test', 'ups'],
                false,
                ['one_of_values_is_not_allowed' => 'bad value']
            )
        );
        // column is nullable so it converts empty string to null -> there should not be any errors with empty string
        static::assertEquals([], RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
        $column->setConvertEmptyStringToNull(false);
        // and now it will fail
        static::assertEquals(
            ['no-no!'],
            RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false, ['value_is_not_allowed' => 'no-no!'])
        );
    }
    
}