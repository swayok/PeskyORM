<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use SplFileInfo;
use Swayok\Utils\NormalizeValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RecordValueHelpersTest extends BaseTestCase
{
    
    public function testGetErrorMessage(): void
    {
        static::assertEquals('test', ColumnValueProcessingHelpers::getErrorMessage([], 'test'));
        static::assertEquals('not-a-test', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::getErrorMessage(['test' => 'not-a-test'], 'test'));
    }
    
    public function testPreprocessValue(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test');
        static::assertFalse($column->isValueTrimmingRequired());
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        static::assertFalse($column->isValueLowercasingRequired());
        static::assertEquals(' ', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, ' ', false, false));
        $column->trimsValue();
        static::assertTrue($column->isValueTrimmingRequired());
        static::assertEquals('', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, ' ', false, false));
        static::assertEquals('A', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, ' A ', false, false));
        $column->convertsEmptyStringToNull();
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        static::assertEquals(null, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, null, false, false));
        static::assertEquals(null, ColumnValueProcessingHelpers::preprocessColumnValue($column, ' ', false, false));
        static::assertEquals('A', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, ' A ', false, false));
        $column->lowercasesValue();
        static::assertTrue($column->isValueLowercasingRequired());
        static::assertEquals('upper', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::preprocessColumnValue($column, 'UPPER', false, false));
    }
    
    public function testNormalizeBoolValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL));
        static::assertTrue(ColumnValueProcessingHelpers::normalizeValue('1', TableColumn::TYPE_BOOL));
        static::assertTrue(ColumnValueProcessingHelpers::normalizeValue(true, TableColumn::TYPE_BOOL));
        static::assertTrue(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('true', TableColumn::TYPE_BOOL));
        static::assertTrue(ColumnValueProcessingHelpers::normalizeValue('false', TableColumn::TYPE_BOOL));
        static::assertTrue(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL));
        static::assertTrue(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(2, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL));
        static::assertFalse(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue([], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL));
        static::assertFalse(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('', TableColumn::TYPE_BOOL));
        static::assertFalse(ColumnValueProcessingHelpers::normalizeValue(0, TableColumn::TYPE_BOOL));
        static::assertFalse(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('0', TableColumn::TYPE_BOOL));
        static::assertFalse(ColumnValueProcessingHelpers::normalizeValue(false, TableColumn::TYPE_BOOL));
    }
    
    public function testNormalizeIntAndUnixTimestampValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_INT));
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(1, ColumnValueProcessingHelpers::normalizeValue('1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT));
        static::assertEquals(2, ColumnValueProcessingHelpers::normalizeValue('2', TableColumn::TYPE_INT));
        static::assertEquals(1, ColumnValueProcessingHelpers::normalizeValue(1, TableColumn::TYPE_INT));
        static::assertEquals(0, ColumnValueProcessingHelpers::normalizeValue('aaa', TableColumn::TYPE_INT));
        static::assertEquals(1, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('1a', TableColumn::TYPE_INT));
        static::assertEquals(0, ColumnValueProcessingHelpers::normalizeValue('s1', TableColumn::TYPE_INT));
        static::assertEquals(2, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('2.25', TableColumn::TYPE_INT));
        static::assertEquals(2, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(2.25, TableColumn::TYPE_INT));
        static::assertEquals(1, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('1.99', TableColumn::TYPE_INT));
        static::assertEquals(1, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(1.99, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT));
        static::assertEquals(time(), \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(time(), \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(time(), ColumnValueProcessingHelpers::normalizeValue(time() . '', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(0, ColumnValueProcessingHelpers::normalizeValue('sasd', TableColumn::TYPE_UNIX_TIMESTAMP));
    }
    
    public function testNormalizeFloatValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('1', TableColumn::TYPE_FLOAT));
        static::assertEquals(2.00, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('2', TableColumn::TYPE_FLOAT));
        static::assertEquals(2.1, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('2.1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT));
        static::assertEquals(3.1999999, ColumnValueProcessingHelpers::normalizeValue('3.1999999', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(1.00, TableColumn::TYPE_FLOAT));
        static::assertEquals(0.00, ColumnValueProcessingHelpers::normalizeValue('aaa', TableColumn::TYPE_FLOAT));
        static::assertEquals(0, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('aaa', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('1a', TableColumn::TYPE_FLOAT));
        static::assertEquals(1.1, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('1.1a9', TableColumn::TYPE_FLOAT));
        static::assertEquals(0, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('s1', TableColumn::TYPE_FLOAT));
    }
    
    public function testNormalizeDateValue(): void
    {
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_DATE));
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(time(), TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 3600),
            ColumnValueProcessingHelpers::normalizeValue('+1 hour', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 86400),
            ColumnValueProcessingHelpers::normalizeValue('+1 day', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, 0),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(0, TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            ColumnValueProcessingHelpers::normalizeValue('2016-09-01', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:00', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:59', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-02',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:60', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:-1', TableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('qqq', TableColumn::TYPE_DATE)
        );
    }
    
    public function testNormalizeTimeValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME));
        $now = time();
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, $now),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue($now, TableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(0, TableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            ColumnValueProcessingHelpers::normalizeValue('qqq', TableColumn::TYPE_TIME)
        );
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, time() + 3600),
            ColumnValueProcessingHelpers::normalizeValue('+1 hour', TableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '01:56:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01:56', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME)
        );
    }
    
    public function testNormalizeDateTimeValue(): void
    {
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_TIMESTAMP));
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(time(), TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 3600),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('+1 hour', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 86400),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('+1 day', TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, 0),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(0, TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('2016-09-01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:01',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 23:59:59',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:59', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-02 00:00:00',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:60', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:-1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            ColumnValueProcessingHelpers::normalizeValue('qqq', TableColumn::TYPE_TIMESTAMP)
        );
    }
    
    public function testNormalizeDateTimeWithTzValue(): void
    {
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT),
            ColumnValueProcessingHelpers::normalizeValue(time(), \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 3600),
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('+1 hour', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 86400),
            ColumnValueProcessingHelpers::normalizeValue('+1 day', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, 0),
            ColumnValueProcessingHelpers::normalizeValue(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('2016-09-01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:01 +00:00',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:01', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 23:59:59 +00:00',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:59', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-02 00:00:00 +00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:60', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            ColumnValueProcessingHelpers::normalizeValue('01-09-2016 00:00:-1', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('qqq', TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('Europe/Moscow');
        static::assertEquals(
            '2016-09-01 23:59:59 +03:00',
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('01-09-2016 23:59:59', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('UTC');
    }
    
    public function testNormalizeJsonValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_JSON));
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSONB));
        static::assertEquals('[]', ColumnValueProcessingHelpers::normalizeValue([], TableColumn::TYPE_JSON));
        static::assertEquals('["a"]', ColumnValueProcessingHelpers::normalizeValue(['a'], TableColumn::TYPE_JSON));
        static::assertEquals('[]', ColumnValueProcessingHelpers::normalizeValue('[]', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals('{"a":"b"}', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(['a' => 'b'], TableColumn::TYPE_JSONB));
        static::assertEquals('{"a":"b"}', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('{"a":"b"}', TableColumn::TYPE_JSONB));
        static::assertEquals('"string"', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('string', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals('string', json_decode('"string"', true));
        static::assertEquals('true', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(true, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals(true, json_decode('true', true));
        static::assertEquals('false', ColumnValueProcessingHelpers::normalizeValue(false, TableColumn::TYPE_JSON));
        static::assertEquals(false, json_decode('false', true));
        static::assertEquals('1', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(1, TableColumn::TYPE_JSON));
        static::assertEquals(1, json_decode('1', true));
        static::assertEquals('"10"', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('10', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals('10', json_decode('"10"', true));
        static::assertEquals('"10.1"', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('10.1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals('10.1', json_decode('"10.1"', true));
        static::assertEquals('10.1', ColumnValueProcessingHelpers::normalizeValue(10.1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON));
        static::assertEquals(10.1, json_decode('10.1', true));
    }
    
    public function testNormalizeFileAndImageValue(): void
    {
        static::assertNull(\PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_FILE));
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, TableColumn::TYPE_IMAGE));
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $normalized = ColumnValueProcessingHelpers::normalizeValue($file, TableColumn::TYPE_FILE);
        static::assertInstanceOf(UploadedFile::class, $normalized);
        static::assertEquals($file['tmp_name'], $normalized->getPathname());
        static::assertEquals($file['name'], $normalized->getClientOriginalName());
        static::assertEquals('jpg', $normalized->getClientOriginalExtension());
        static::assertEquals('image/jpeg', $normalized->getMimeType());
        static::assertEquals($file['size'], $normalized->getSize());
        static::assertEquals($file['error'], $normalized->getError());
        
        $normalized2 = \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue($file, TableColumn::TYPE_IMAGE);
        static::assertInstanceOf(UploadedFile::class, $normalized2);
        static::assertEquals('image/jpeg', $normalized->getMimeType());
        
        $normalized3 = ColumnValueProcessingHelpers::normalizeValue($normalized, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IMAGE);
        static::assertEquals($normalized, $normalized3);
    }
    
    public function testNormalizeStringValue(): void
    {
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS));
        static::assertNull(ColumnValueProcessingHelpers::normalizeValue(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING));
        static::assertEquals('string', ColumnValueProcessingHelpers::normalizeValue('string', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING));
        static::assertEquals('1111', ColumnValueProcessingHelpers::normalizeValue(1111, TableColumn::TYPE_STRING));
        static::assertEquals('', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(false, TableColumn::TYPE_STRING));
        static::assertEquals('0', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(0, TableColumn::TYPE_STRING));
        static::assertEquals('1', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue(true, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING));
        static::assertEquals('string', \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::normalizeValue('string', TableColumn::TYPE_IPV4_ADDRESS));
    }
    
    public function testIsValueFitsDataTypeBool(): void
    {
        $message = ['value_must_be_boolean'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_BOOL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_BOOL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.0, TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1.0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0.0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, false));
        static::assertEquals(['bool'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.0', TableColumn::TYPE_BOOL, false, ['value_must_be_boolean' => 'bool']));
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_BOOL, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_BOOL, true));
    }
    
    public function testIsValueFitsDataTypeInt(): void
    {
        $message = ['value_must_be_integer'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(11, TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-11, TableColumn::TYPE_INT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-11', TableColumn::TYPE_INT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.0, TableColumn::TYPE_INT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1.0000, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1.0', TableColumn::TYPE_INT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1.0000', TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0.1, TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.1', TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('str', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('a1', TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('1a', TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(true, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_INT, false));
        static::assertEquals(['int'], ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_INT, false, ['value_must_be_integer' => 'int']));
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(11, TableColumn::TYPE_INT, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11', TableColumn::TYPE_INT, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_INT, true));
    }
    
    public function testIsValueFitsDataTypeFloat(): void
    {
        $message = ['value_must_be_float'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(11, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-11, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-11', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(11.2, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11.2', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(-11.3, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-11.3', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(0, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1.0, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1.01, TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1.01', TableColumn::TYPE_FLOAT, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1.001', TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('str', TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('a1', TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1a', TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(true, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FLOAT, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_FLOAT, false));
        static::assertEquals(['float'], ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_FLOAT, false, ['value_must_be_float' => 'float']));
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11.2', TableColumn::TYPE_FLOAT, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(11.2, TableColumn::TYPE_FLOAT, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('str', TableColumn::TYPE_FLOAT, true));
    }
    
    public function testIsValueFitsDataTypeDate(): void
    {
        $message = ['value_must_be_date'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(time(), \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('+1 day', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('now', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_DATE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22', TableColumn::TYPE_DATE, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_DATE, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_DATE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01092016', TableColumn::TYPE_DATE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_DATE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, TableColumn::TYPE_DATE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_DATE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_DATE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_DATE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_DATE, false));
        static::assertEquals(['date'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, false, ['value_must_be_date' => 'date']));
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_DATE, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_DATE, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_DATE, true));
    }
    
    public function testIsValueFitsDataTypeTime(): void
    {
        $message = ['value_must_be_time'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(time(), TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('+1 hour', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('now', TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', TableColumn::TYPE_TIME, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_TIME, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01092016', TableColumn::TYPE_TIME, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_TIME, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, false));
        static::assertEquals(['time'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_TIME, false, ['value_must_be_time' => 'time']));
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIME, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', TableColumn::TYPE_TIME, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22', TableColumn::TYPE_TIME, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIME, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIME, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_TIME, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_TIME, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_TIME, true));
    }
    
    public function testIsValueFitsDataTypeTimestamp(): void
    {
        $message = ['value_must_be_timestamp'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(time(), TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('+1 hour', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('now', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01092016', TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false));
        static::assertEquals(['timestamp'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, false, ['value_must_be_timestamp' => 'timestamp']));
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('now', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_TIMESTAMP, true));
    }
    
    public function testIsValueFitsDataTypeTimestampWithTz(): void
    {
        $message = ['value_must_be_timestamp_with_tz'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('+1 hour', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('now', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11:22', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(time(), TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('01092016', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_TIMESTAMP_WITH_TZ, false));
        static::assertEquals(
            ['wtz'],
            ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_TIMESTAMP_WITH_TZ, false, ['value_must_be_timestamp_with_tz' => 'wtz'])
        );
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(time(), TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01092016', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0, TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_TIMESTAMP_WITH_TZ, true));
    }
    
    public function testIsValueFitsDataTypeTimezoneOffset(): void
    {
        $message = ['value_must_be_timezone_offset'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('11:22:33', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('11:22', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('+18:00', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(44200, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('44200', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-18:00', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(-44200, TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-44200', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(90000, TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('+1 hour', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('now', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_TIMEZONE_OFFSET, false));
        static::assertEquals(
            ['offset'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_TIMEZONE_OFFSET, false, ['value_must_be_timezone_offset' => 'offset'])
        );
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(44200, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('44200', TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-18:00', TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-44200, TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-44200', TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('now', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('2016-09-01', TableColumn::TYPE_TIMEZONE_OFFSET, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('01-09-2016 11:22:33', TableColumn::TYPE_TIMEZONE_OFFSET, true));
    }
    
    public function testIsValueFitsDataTypeIpV4Address(): void
    {
        $message = ['value_must_be_ipv4_address'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('192.168.0.0', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0.0.0.0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('255.255.255.255', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('1.1.1.1/24', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-1.0.0.1', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0.-1.0.1', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.0.-1.1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.0.0.-1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('255.255.255.256', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('255.255.256.255', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('255.256.255.255', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('256.255.255.255', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('*.*.*.*', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('a.0.0.0', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.a.0.0', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.0.a.0', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('0.0.0.a', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(true, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(1.25, TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.0, TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('1.0', TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(0.0, TableColumn::TYPE_IPV4_ADDRESS, false));
        static::assertEquals(['ip'],
            ColumnValueProcessingHelpers::isValueFitsDataType('0.0', TableColumn::TYPE_IPV4_ADDRESS, false, ['value_must_be_ipv4_address' => 'ip']));
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('192.168.0.0', TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0.0.0.0', TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('255.255.255.255', TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1.1.1.1/24', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('-1.0.0.1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IPV4_ADDRESS, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.-1.0.1', TableColumn::TYPE_IPV4_ADDRESS, true));
    }
    
    public function testIsValueFitsDataTypeJson(): void
    {
        $message = ['value_must_be_json'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(['a' => 'b'], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1.11, TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(-1.11, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1.11', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('-1.11', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('"-1.11"', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('"1.11"', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('[]', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('["a"]', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('[1]', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('["a","b"]', TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('["a", "b"]', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('["a", "b" ]', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('[ "a", "b" ]', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{}', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{"a":1.11}', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{ "a":1.11}', TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{ "a":1.11 }', TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('{ "a" :1.11 }', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{ "a" : 1.11 }', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{1:1.11}', TableColumn::TYPE_JSON, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{"a":}', TableColumn::TYPE_JSON, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{"a":"b",}', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('{:"a"}', TableColumn::TYPE_JSON, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('[a]', TableColumn::TYPE_JSON, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('["a",]', TableColumn::TYPE_JSON, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('["a":"b"]', TableColumn::TYPE_JSON, false));
        static::assertEquals(['json'], ColumnValueProcessingHelpers::isValueFitsDataType('["a":]', TableColumn::TYPE_JSON, false, ['value_must_be_json' => 'json']));
        // for conditions
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('-1.11', TableColumn::TYPE_JSON, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('"-1.11"', TableColumn::TYPE_JSON, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('"1.11"', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('[]', TableColumn::TYPE_JSON, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('["a"]', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_JSON, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('{1:1.11}', TableColumn::TYPE_JSON, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{"a":}', TableColumn::TYPE_JSON, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('{"a":"b",}', TableColumn::TYPE_JSON, true));
    }
    
    public function testIsValueFitsDataTypeEmail(): void
    {
        $message = ['value_must_be_email'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('test.test@email.ru', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('+test.test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('-test.test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('`test.test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('.test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('[test@email.ru', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(']test@email.ru', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(1.25, TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('1.0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(0.0, TableColumn::TYPE_EMAIL, false));
        static::assertEquals(['email'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0.0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, false, ['value_must_be_email' => 'email']));
        // for conditions
        $altMessage = ['value_must_be_string'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('test.test@email.ru', TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('+test.test@email.ru', TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('[test@email.ru', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('.test@email.ru', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('email.ru', TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('test', TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('false', TableColumn::TYPE_EMAIL, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0.0', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, true));
        static::assertEquals($altMessage, ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_EMAIL, true));
        static::assertEquals($altMessage, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, true));
        static::assertEquals($altMessage, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_EMAIL, true));
        static::assertEquals($altMessage, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_EMAIL, true));
        static::assertEquals($altMessage, ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_EMAIL, true));
    }
    
    public function testIsValueFitsDataTypeString(): void
    {
        $message = ['value_must_be_string'];
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('str', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_STRING, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_STRING, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_STRING, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_STRING, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_STRING, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_STRING, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_STRING, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1, TableColumn::TYPE_STRING, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1.25, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, false));
        static::assertEquals(['string'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_STRING, false, ['value_must_be_string' => 'string']));
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('str', TableColumn::TYPE_STRING, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', TableColumn::TYPE_STRING, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_STRING, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_STRING, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_STRING, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_STRING, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, TableColumn::TYPE_STRING, true));
        static::assertEquals(['string'], ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, true, ['value_must_be_string' => 'string']));
    }
    
    public function testIsValueFitsDataTypeEnum(): void
    {
        $message = ['value_must_be_string_or_numeric'];
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('str', TableColumn::TYPE_ENUM, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_ENUM, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, TableColumn::TYPE_ENUM, false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType(-1.25, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_ENUM, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_ENUM, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, false));
        static::assertEquals(['enum'],
            ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_ENUM, false, ['value_must_be_string_or_numeric' => 'enum']));
        // for conditions
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('str', TableColumn::TYPE_ENUM, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('1', TableColumn::TYPE_ENUM, true));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType('0', TableColumn::TYPE_ENUM, true));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_ENUM, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType([], TableColumn::TYPE_ENUM, true));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(null, TableColumn::TYPE_ENUM, true));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_ENUM, true));
    }
    
    public function testIsValueFitsDataTypeUploadedFile(): void
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
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_FILE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($validFileObj, TableColumn::TYPE_FILE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($validUploadedFileObj, TableColumn::TYPE_FILE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType(['not_a_file'], TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(new \stdClass(), TableColumn::TYPE_FILE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType('', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('true', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType('false', \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(true, TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(false, TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(null, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType([], \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1, TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(1.25, TableColumn::TYPE_FILE, false));
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType(-1.0, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['tmp_name']);
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['name']);
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType($badFile, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['type']);
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($badFile, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['size']);
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        unset($badFile['error']);
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['size'] = 0;
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['size'] = -1;
        static::assertEquals($message, ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        
        $badFile = $file;
        $badFile['error'] = 1;
        static::assertEquals($message, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($badFile, TableColumn::TYPE_FILE, false));
        $badFileObj = new UploadedFile($badFile['tmp_name'] . 'asd', $badFile['name'], $badFile['type'], $badFile['error']);
        static::assertEquals(['file'],
            ColumnValueProcessingHelpers::isValueFitsDataType($badFileObj, TableColumn::TYPE_FILE, false, ['value_must_be_file' => 'file']));
    }
    
    public function testIsValueFitsDataTypeUploadedImage(): void
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
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($file, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_FILE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($validFileObj, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IMAGE, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($validUploadedFileObj, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IMAGE, false));
        static::assertEquals(['value_must_be_image'], ColumnValueProcessingHelpers::isValueFitsDataType(['not_a_file'], TableColumn::TYPE_IMAGE, false));
        static::assertEquals(['value_must_be_image'], ColumnValueProcessingHelpers::isValueFitsDataType(new \stdClass(), TableColumn::TYPE_IMAGE, false));
        $file['name'] = 'image_jpg';
        $file['type'] = 'image/jpeg';
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType($file, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IMAGE, false));
        $file['type'] = 'image/png';
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false));
        $file['type'] = 'image/gif';
        static::assertEquals([], ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false));
        $file['type'] = 'image/svg';
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false));
        
        $file['type'] = 'text/plain';
        $file['tmp_name'] = __DIR__ . '/files/test_file_jpg'; //< mime autodetection will solve this
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false));
        
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($fileObj, \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_IMAGE, false));
    
        $file['tmp_name'] = __DIR__ . '/files/test_file.docx';
        static::assertEquals(['image'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($file, TableColumn::TYPE_IMAGE, false, ['value_must_be_image' => 'image']));
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
        static::assertEquals(['image'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueFitsDataType($fileObj, TableColumn::TYPE_IMAGE, false, ['value_must_be_image' => 'image']));
    }
    
    public function testIsValidDbColumnValue(): void
    {
        $column = TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, 'test', false, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, '', false, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, DbExpr::create('test'), false, false));
        static::assertEquals(
            ['not null'],
            ColumnValueProcessingHelpers::isValidDbColumnValue($column, null, false, false, ['value_cannot_be_null' => 'not null'])
        );
        $column->convertsEmptyStringToNull();
        static::assertEquals(['value_cannot_be_null'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, '', false, false));
        $column->allowsNullValues();
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, null, false, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, '', false, false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, DbExpr::create('test'), false, false));
        // invalid valie
        $column = TableColumn::create(TableColumn::TYPE_INT, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals(['value_must_be_integer'], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValidDbColumnValue($column, 'not_int', false, false));
    }
    
    public function testInvalidColumnAllowedValuesForEnum(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Enum column [test] is required to have a list of allowed values");
        $column = \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, 'test');
        ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false);
    }
    
    public function testInvalidValueForAllowedValues(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($value) must be of type array|string|int|float|bool|null');
        $column = TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['test']);
        /** @noinspection PhpParamsInspection */
        \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column, false);
    }
    
    public function testInvalidValueForAllowedValues2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($value) must be of type array|string|int|float|bool|null');
        $column = \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::create(TableColumn::TYPE_STRING, 'test')
            ->setAllowedValues(['test']);
        /** @noinspection PhpParamsInspection */
        ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column, false);
    }
    
    public function testInvalidValueForAllowedValues3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['test'])
            ->disallowsNullValues();
        ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false);
    }
    
    public function testInvalidValueForAllowedValues4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$value argument must be a string, integer, float or array to be able to validate if it is within allowed values"
        );
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test')
            ->disallowsNullValues()
            ->setAllowedValues(['test']);
        \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false);
    }
    
    public function testIsValueWithinTheAllowedValuesOfTheEnumColumn(): void
    {
        $message1 = ['value_is_not_allowed'];
        $message2 = ['one_of_values_is_not_allowed'];
        $column = TableColumn::create(TableColumn::TYPE_ENUM, 'test')
            ->allowsNullValues()
            ->setAllowedValues(['test', 'test2']);
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2', false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false));
        static::assertEquals($message1, ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups', false));
        static::assertEquals($message2, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups'], false));
        static::assertEquals($message2, \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'ups'], false));
        // column is nullable, so it converts empty string to null -> there should not be any errors with empty string
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
        $column->setConvertEmptyStringToNull(false);
        // and now it will fail
        static::assertEquals($message1, ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
    }
    
    public function testIsValueWithinTheAllowedValuesOfTheNotEnumColumn(): void
    {
        $column = \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_STRING, 'test')
            ->allowsNullValues()
            ->setAllowedValues(['test', 'test2']);
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test', false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2', false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2'], false));
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2'], false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test'], false));
        static::assertEquals([], \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null, false));
        static::assertEquals(
            ['value_is_not_allowed'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups', false)
        );
        static::assertEquals(
            ['one_of_values_is_not_allowed'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups'], false)
        );
        static::assertEquals(
            ['bad value'],
            ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn(
                $column,
                ['test', 'ups'],
                false,
                ['one_of_values_is_not_allowed' => 'bad value']
            )
        );
        // column is nullable, so it converts empty string to null -> there should not be any errors with empty string
        static::assertEquals([], ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false));
        $column->setConvertEmptyStringToNull(false);
        // and now it will fail
        static::assertEquals(
            ['no-no!'],
            \PeskyORM\ORM\TableStructure\TableColumn\ColumnValueProcessingHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', false, ['value_is_not_allowed' => 'no-no!'])
        );
    }
    
}