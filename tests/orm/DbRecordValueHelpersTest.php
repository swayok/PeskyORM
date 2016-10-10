<?php


use PeskyORM\ORM\DbRecordValue;
use PeskyORM\ORM\DbRecordValueHelpers;
use PeskyORM\ORM\DbTableColumn;
use PeskyORMTest\TestingSettings\TestingSetting;
use Swayok\Utils\NormalizeValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DbRecordValueHelpersTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        date_default_timezone_set('UTC');
        parent::setUpBeforeClass();
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return DbRecordValue
     */
    private function createDbRecordValue($type, $value) {
        $obj = DbRecordValue::create(DbTableColumn::create($type, 'test'), TestingSetting::_());
        $obj->setRawValue($value, $value, true)
            ->setValidValue($value, $value);
        return $obj;
    }

    public function testGetErrorMessage() {
        static::assertEquals('test', DbRecordValueHelpers::getErrorMessage([], 'test'));
        static::assertEquals('not-a-test', DbRecordValueHelpers::getErrorMessage(['test' => 'not-a-test'], 'test'));
    }

    public function testPreprocessValue() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test');
        static::assertFalse($column->isValueTrimmingRequired());
        static::assertFalse($column->isEmptyStringMustBeConvertedToNull());
        static::assertFalse($column->isValueLowercasingRequired());
        static::assertEquals(' ', DbRecordValueHelpers::preprocessColumnValue($column, ' '));
        $column->mustTrimValue();
        static::assertTrue($column->isValueTrimmingRequired());
        static::assertEquals('', DbRecordValueHelpers::preprocessColumnValue($column, ' '));
        static::assertEquals('A', DbRecordValueHelpers::preprocessColumnValue($column, ' A '));
        $column->convertsEmptyStringToNull();
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        static::assertEquals(null, DbRecordValueHelpers::preprocessColumnValue($column, null));
        static::assertEquals(null, DbRecordValueHelpers::preprocessColumnValue($column, ' '));
        static::assertEquals('A', DbRecordValueHelpers::preprocessColumnValue($column, ' A '));
        $column->mustLowercaseValue();
        static::assertTrue($column->isValueLowercasingRequired());
        static::assertEquals('upper', DbRecordValueHelpers::preprocessColumnValue($column, 'UPPER'));
    }

    public function testNormalizeBoolValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue('1', DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue(true, DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue('true', DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue('false', DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue(1, DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue(2, DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue([], DbTableColumn::TYPE_BOOL));
        static::assertTrue(DbRecordValueHelpers::normalizeValue('', DbTableColumn::TYPE_BOOL));
        static::assertFalse(DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_BOOL));
        static::assertFalse(DbRecordValueHelpers::normalizeValue('0', DbTableColumn::TYPE_BOOL));
        static::assertFalse(DbRecordValueHelpers::normalizeValue(false, DbTableColumn::TYPE_BOOL));
    }

    public function testNormalizeIntAndUnixTimestampValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_INT));
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(1, DbRecordValueHelpers::normalizeValue('1', DbTableColumn::TYPE_INT));
        static::assertEquals(2, DbRecordValueHelpers::normalizeValue('2', DbTableColumn::TYPE_INT));
        static::assertEquals(1, DbRecordValueHelpers::normalizeValue(1, DbTableColumn::TYPE_INT));
        static::assertEquals(0, DbRecordValueHelpers::normalizeValue('aaa', DbTableColumn::TYPE_INT));
        static::assertEquals(1, DbRecordValueHelpers::normalizeValue('1a', DbTableColumn::TYPE_INT));
        static::assertEquals(0, DbRecordValueHelpers::normalizeValue('s1', DbTableColumn::TYPE_INT));
        static::assertEquals(2, DbRecordValueHelpers::normalizeValue('2.25', DbTableColumn::TYPE_INT));
        static::assertEquals(2, DbRecordValueHelpers::normalizeValue(2.25, DbTableColumn::TYPE_INT));
        static::assertEquals(1, DbRecordValueHelpers::normalizeValue('1.99', DbTableColumn::TYPE_INT));
        static::assertEquals(1, DbRecordValueHelpers::normalizeValue(1.99, DbTableColumn::TYPE_INT));
        static::assertEquals(time(), DbRecordValueHelpers::normalizeValue(time(), DbTableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(time(), DbRecordValueHelpers::normalizeValue(time() . '', DbTableColumn::TYPE_UNIX_TIMESTAMP));
        static::assertEquals(0, DbRecordValueHelpers::normalizeValue('sasd', DbTableColumn::TYPE_UNIX_TIMESTAMP));
    }

    public function testNormalizeFloatValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, DbRecordValueHelpers::normalizeValue('1', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(2.00, DbRecordValueHelpers::normalizeValue('2', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(2.1, DbRecordValueHelpers::normalizeValue('2.1', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(3.1999999, DbRecordValueHelpers::normalizeValue('3.1999999', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, DbRecordValueHelpers::normalizeValue(1.00, DbTableColumn::TYPE_FLOAT));
        static::assertEquals(0.00, DbRecordValueHelpers::normalizeValue('aaa', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(0, DbRecordValueHelpers::normalizeValue('aaa', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(1.00, DbRecordValueHelpers::normalizeValue('1a', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(1.1, DbRecordValueHelpers::normalizeValue('1.1a9', DbTableColumn::TYPE_FLOAT));
        static::assertEquals(0, DbRecordValueHelpers::normalizeValue('s1', DbTableColumn::TYPE_FLOAT));
    }

    public function testNormalizeDateValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_DATE));
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time()),
            DbRecordValueHelpers::normalizeValue(time(), DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 3600),
            DbRecordValueHelpers::normalizeValue('+1 hour', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, time() + 86400),
            DbRecordValueHelpers::normalizeValue('+1 day', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            date(NormalizeValue::DATE_FORMAT, 0),
            DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            DbRecordValueHelpers::normalizeValue('2016-09-01', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            DbRecordValueHelpers::normalizeValue('01-09-2016', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:00', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-01',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:59', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '2016-09-02',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:60', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', DbTableColumn::TYPE_DATE)
        );
        static::assertEquals(
            '1970-01-01',
            DbRecordValueHelpers::normalizeValue('qqq', DbTableColumn::TYPE_DATE)
        );
    }

    public function testNormalizeTimeValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_TIME));
        $now = time();
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, $now),
            DbRecordValueHelpers::normalizeValue($now, DbTableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:00', DbTableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '00:00:00',
            DbRecordValueHelpers::normalizeValue('qqq', DbTableColumn::TYPE_TIME)
        );
        static::assertEquals(
            date(NormalizeValue::TIME_FORMAT, time() + 3600),
            DbRecordValueHelpers::normalizeValue('+1 hour', DbTableColumn::TYPE_TIME)
        );
        static::assertEquals(
            '01:56:00',
            DbRecordValueHelpers::normalizeValue('01:56', DbTableColumn::TYPE_TIME)
        );
    }

    public function testNormalizeDateTimeValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time()),
            DbRecordValueHelpers::normalizeValue(time(), DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 3600),
            DbRecordValueHelpers::normalizeValue('+1 hour', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, time() + 86400),
            DbRecordValueHelpers::normalizeValue('+1 day', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_FORMAT, 0),
            DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            DbRecordValueHelpers::normalizeValue('2016-09-01', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 00:00:01',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:01', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-01 23:59:59',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:59', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '2016-09-02 00:00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:60', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', DbTableColumn::TYPE_TIMESTAMP)
        );
        static::assertEquals(
            '1970-01-01 00:00:00',
            DbRecordValueHelpers::normalizeValue('qqq', DbTableColumn::TYPE_TIMESTAMP)
        );
    }

    public function testNormalizeDateTimeWithTzValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time()),
            DbRecordValueHelpers::normalizeValue(time(), DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 3600),
            DbRecordValueHelpers::normalizeValue('+1 hour', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, time() + 86400),
            DbRecordValueHelpers::normalizeValue('+1 day', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            date(NormalizeValue::DATETIME_WITH_TZ_FORMAT, 0),
            DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            DbRecordValueHelpers::normalizeValue('2016-09-01', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:00 +00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 00:00:01 +00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:01', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-01 23:59:59 +00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:59', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '2016-09-02 00:00:00 +00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:60', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 00:00:-1', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        static::assertEquals(
            '1970-01-01 00:00:00 +00:00',
            DbRecordValueHelpers::normalizeValue('qqq', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('Europe/Moscow');
        static::assertEquals(
            '2016-09-01 23:59:59 +03:00',
            DbRecordValueHelpers::normalizeValue('01-09-2016 23:59:59', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ)
        );
        date_default_timezone_set('UTC');
    }

    public function testNormalizeJsonValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_JSON));
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_JSONB));
        static::assertEquals('[]', DbRecordValueHelpers::normalizeValue([], DbTableColumn::TYPE_JSON));
        static::assertEquals('["a"]', DbRecordValueHelpers::normalizeValue(['a'], DbTableColumn::TYPE_JSON));
        static::assertEquals('[]', DbRecordValueHelpers::normalizeValue('[]', DbTableColumn::TYPE_JSON));
        static::assertEquals('{"a":"b"}', DbRecordValueHelpers::normalizeValue(['a' => 'b'], DbTableColumn::TYPE_JSONB));
        static::assertEquals('{"a":"b"}', DbRecordValueHelpers::normalizeValue('{"a":"b"}', DbTableColumn::TYPE_JSONB));
        static::assertEquals('"string"', DbRecordValueHelpers::normalizeValue('string', DbTableColumn::TYPE_JSON));
        static::assertEquals('string', json_decode('"string"', true));
        static::assertEquals('true', DbRecordValueHelpers::normalizeValue(true, DbTableColumn::TYPE_JSON));
        static::assertEquals(true, json_decode('true', true));
        static::assertEquals('false', DbRecordValueHelpers::normalizeValue(false, DbTableColumn::TYPE_JSON));
        static::assertEquals(false, json_decode('false', true));
        static::assertEquals('1', DbRecordValueHelpers::normalizeValue(1, DbTableColumn::TYPE_JSON));
        static::assertEquals(1, json_decode('1', true));
        static::assertEquals('"10"', DbRecordValueHelpers::normalizeValue('10', DbTableColumn::TYPE_JSON));
        static::assertEquals('10', json_decode('"10"', true));
        static::assertEquals('"10.1"', DbRecordValueHelpers::normalizeValue('10.1', DbTableColumn::TYPE_JSON));
        static::assertEquals('10.1', json_decode('"10.1"', true));
        static::assertEquals('10.1', DbRecordValueHelpers::normalizeValue(10.1, DbTableColumn::TYPE_JSON));
        static::assertEquals(10.1, json_decode('10.1', true));
    }

    public function testNormalizeFileAndImageValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_FILE));
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_IMAGE));
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $normalized = DbRecordValueHelpers::normalizeValue($file, DbTableColumn::TYPE_FILE);
        static::assertInstanceOf(UploadedFile::class, $normalized);
        static::assertEquals($file['tmp_name'], $normalized->getPathname());
        static::assertEquals($file['name'], $normalized->getClientOriginalName());
        static::assertEquals('jpg', $normalized->getClientOriginalExtension());
        static::assertEquals('image/jpeg', $normalized->getMimeType());
        static::assertEquals($file['size'], $normalized->getSize());
        static::assertEquals($file['error'], $normalized->getError());

        $normalized2 = DbRecordValueHelpers::normalizeValue($file, DbTableColumn::TYPE_IMAGE);
        static::assertInstanceOf(UploadedFile::class, $normalized2);
        static::assertEquals('image/jpeg', $normalized->getMimeType());

        $normalized3 = DbRecordValueHelpers::normalizeValue($normalized, DbTableColumn::TYPE_IMAGE);
        static::assertEquals($normalized, $normalized3);
    }

    public function testNormalizeStringValue() {
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertNull(DbRecordValueHelpers::normalizeValue(null, DbTableColumn::TYPE_STRING));
        static::assertEquals('string', DbRecordValueHelpers::normalizeValue('string', DbTableColumn::TYPE_STRING));
        static::assertEquals('1111', DbRecordValueHelpers::normalizeValue(1111, DbTableColumn::TYPE_STRING));
        static::assertEquals('', DbRecordValueHelpers::normalizeValue(false, DbTableColumn::TYPE_STRING));
        static::assertEquals('0', DbRecordValueHelpers::normalizeValue(0, DbTableColumn::TYPE_STRING));
        static::assertEquals('1', DbRecordValueHelpers::normalizeValue(true, DbTableColumn::TYPE_STRING));
        static::assertEquals('string', DbRecordValueHelpers::normalizeValue('string', DbTableColumn::TYPE_IPV4_ADDRESS));
    }

    public function testGetValueFormatterAndFormatsByTypeForTimestamps() {
        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_UNIX_TIMESTAMP);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);

        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_TIMESTAMP);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);

        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_TIMESTAMP_WITH_TZ);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['date', 'time', 'unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }

    public function testGetValueFormatterAndFormatsByTypeForDateAndTime() {
        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_DATE);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);

        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_TIME);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['unix_ts'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }

    public function testGetValueFormatterAndFormatsByTypeForJson() {
        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_JSON);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['array', 'object'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);

        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_JSONB);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals(['array', 'object'], $ret[1]);
        static::assertInstanceOf(\Closure::class, $ret[0]);
    }

    public function testGetValueFormatterAndFormatsByTypeForOthers() {
        $ret = DbRecordValueHelpers::getValueFormatterAndFormatsByType(DbTableColumn::TYPE_STRING);
        static::assertNotEmpty($ret);
        static::assertTrue(is_array($ret));
        static::assertCount(2, $ret);
        static::assertEquals([], $ret[1]);
        static::assertNull($ret[0]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument must be a string
     */
    public function testInvalidFormatTimestamp1() {
        DbRecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(DbTableColumn::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            []
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument must be a string
     */
    public function testInvalidFormatTimestamp2() {
        DbRecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(DbTableColumn::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            null
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument must be a string
     */
    public function testInvalidFormatTimestamp3() {
        DbRecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(DbTableColumn::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            true
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Requested value format 'not_existing_format' is not implemented
     */
    public function testInvalidFormatTimestamp4() {
        DbRecordValueHelpers::formatTimestamp(
            $this->createDbRecordValue(DbTableColumn::TYPE_TIMESTAMP, '2016-09-01 01:02:03'),
            'not_existing_format'
        );
    }

    public function testFormatTimestamp() {
        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_TIMESTAMP, '2016-09-01 01:02:03');
        static::assertEquals('2016-09-01', DbRecordValueHelpers::formatTimestamp($valueObj, 'date'));
        static::assertEquals('01:02:03', DbRecordValueHelpers::formatTimestamp($valueObj, 'time'));
        static::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatTimestamp($valueObj, 'unix_ts'));
    }

    public function testFormatDateOrTime() {
        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_DATE, '2016-09-01');
        static::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatDateOrTime($valueObj, 'unix_ts'));

        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_TIME, '12:34:56');
        static::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatDateOrTime($valueObj, 'unix_ts'));
    }

    public function testFormatJson() {
        $value = ['test' => 'value', 'val'];
        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_JSON, json_encode($value));
        static::assertEquals($value, DbRecordValueHelpers::formatJson($valueObj, 'array'));
        static::assertEquals(json_decode(json_encode($value)), DbRecordValueHelpers::formatJson($valueObj, 'object'));

        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_JSONB, '"invalidjson');
        static::assertEquals(false, DbRecordValueHelpers::formatJson($valueObj, 'array'));
        static::assertEquals(false, DbRecordValueHelpers::formatJson($valueObj, 'object'));
    }

    public function testIsValueFitsDataTypeBool() {
        $message = ['value_must_be_boolean'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_BOOL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_BOOL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_BOOL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_BOOL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_BOOL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_BOOL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_BOOL));
        static::assertEquals(['bool'], DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_BOOL, ['value_must_be_boolean' => 'bool']));
    }

    public function testIsValueFitsDataTypeInt() {
        $message = ['value_must_be_integer'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11, DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11, DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11', DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11', DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0000, DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_INT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.0000', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.1, DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.1', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('str', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('a1', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1a', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_INT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_INT));
        static::assertEquals(['int'], DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_INT, ['value_must_be_integer' => 'int']));
    }

    public function testIsValueFitsDataTypeFloat() {
        $message = ['value_must_be_float'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11.2, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11.2', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11.3, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11.3', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.01, DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.01', DbTableColumn::TYPE_FLOAT));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.001', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('str', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('a1', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1a', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_FLOAT));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_FLOAT));
        static::assertEquals(['float'], DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_FLOAT, ['value_must_be_float' => 'float']));
    }

    public function testIsValueFitsDataTypeDate() {
        $message = ['value_must_be_date'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(time(), DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+1 day', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('now', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('2016-09-01', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_DATE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01092016', DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_DATE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_DATE));
        static::assertEquals(['date'], DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_DATE, ['value_must_be_date' => 'date']));
    }

    public function testIsValueFitsDataTypeTime() {
        $message = ['value_must_be_time'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(time(), DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+1 hour', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('now', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('2016-09-01', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22:33', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIME));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01092016', DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIME));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_TIME));
        static::assertEquals(['time'], DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIME, ['value_must_be_time' => 'time']));
    }

    public function testIsValueFitsDataTypeTimestamp() {
        $message = ['value_must_be_timestamp'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(time(), DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+1 hour', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('now', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('2016-09-01', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22:33', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01092016', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIMESTAMP));
        static::assertEquals(['timestamp'], DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIMESTAMP, ['value_must_be_timestamp' => 'timestamp']));
    }

    public function testIsValueFitsDataTypeTimestampWithTz() {
        $message = ['value_must_be_timestamp_with_tz'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+1 hour', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('now', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('2016-09-01', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22:33', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33 +03:00', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(time(), DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('01092016', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_TIMESTAMP_WITH_TZ));
        static::assertEquals(
            ['wtz'],
            DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIMESTAMP_WITH_TZ, ['value_must_be_timestamp_with_tz' => 'wtz'])
        );
    }

    public function testIsValueFitsDataTypeTimezoneOffset() {
        $message = ['value_must_be_timezone_offset'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22:33', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11:22', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+18:00', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(44200, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('44200', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-18:00', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-44200, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-44200', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-1', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(90000, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('+1 hour', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('now', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('2016-09-01', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('01-09-2016 11:22:33', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIMEZONE_OFFSET));
        static::assertEquals(
            ['offset'],
            DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_TIMEZONE_OFFSET, ['value_must_be_timezone_offset' => 'offset'])
        );
    }

    public function testIsValueFitsDataTypeIpV4Address() {
        $message = ['value_must_be_ipv4_address'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('192.168.0.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0.0.0.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('255.255.255.255', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.1.1.1/24', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('-1.0.0.1', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.-1.0.1', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0.-1.1', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0.0.-1', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('255.255.255.256', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('255.255.256.255', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('255.256.255.255', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('256.255.255.255', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('*.*.*.*', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('a.0.0.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.a.0.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0.a.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0.0.a', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_IPV4_ADDRESS));
        static::assertEquals(['ip'], DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_IPV4_ADDRESS, ['value_must_be_ipv4_address' => 'ip']));
    }

    public function testIsValueFitsDataTypeJson() {
        $message = ['value_must_be_json'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(['a' => 'b'], DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.11, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-1.11, DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-1', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.11', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-1.11', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('"-1.11"', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('"1.11"', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('[]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('["a"]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('[1]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('["a","b"]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('["a", "b"]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('["a", "b" ]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('[ "a", "b" ]', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{}', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{"a":1.11}', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{ "a":1.11}', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{ "a":1.11 }', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{ "a" :1.11 }', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('{ "a" : 1.11 }', DbTableColumn::TYPE_JSON));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('{1:1.11}', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('{"a":}', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('{"a":"b",}', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('{:"a"}', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('[a]', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('["a",]', DbTableColumn::TYPE_JSON));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('["a":"b"]', DbTableColumn::TYPE_JSON));
        static::assertEquals(['json'], DbRecordValueHelpers::isValueFitsDataType('["a":]', DbTableColumn::TYPE_JSON, ['value_must_be_json' => 'json']));
    }

    public function testIsValueFitsDataTypeEmail() {
        $message = ['value_must_be_email'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('test.test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('+test.test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-test.test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('`test.test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('.test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('[test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(']test@email.ru', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_EMAIL));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_EMAIL));
        static::assertEquals(['email'], DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_EMAIL, ['value_must_be_email' => 'email']));
    }

    public function testIsValueFitsDataTypeString() {
        $message = ['value_must_be_string'];
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('str', DbTableColumn::TYPE_STRING));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_STRING));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_STRING));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_STRING));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_STRING));
        static::assertEquals(['string'], DbRecordValueHelpers::isValueFitsDataType(-1.25, DbTableColumn::TYPE_STRING, ['value_must_be_string' => 'string']));
    }

    public function testIsValueFitsDataTypeUploadedFile() {
        $message = ['value_must_be_file'];
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_FILE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($fileObj, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('', DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType([], DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_FILE));
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1.0, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        unset($badFile['tmp_name']);
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        unset($badFile['name']);
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        unset($badFile['type']);
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        unset($badFile['size']);
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        unset($badFile['error']);
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        $badFile['size'] = 0;
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        $badFile['size'] = -1;
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));

        $badFile = $file;
        $badFile['error'] = 1;
        static::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType($badFile, DbTableColumn::TYPE_FILE));
        $badFileObj = new UploadedFile($badFile['tmp_name'] . 'asd', $badFile['name'], $badFile['type'], $badFile['size'], $badFile['error']);
        static::assertEquals(['file'], DbRecordValueHelpers::isValueFitsDataType($badFileObj, DbTableColumn::TYPE_FILE, ['value_must_be_file' => 'file']));
    }

    public function testIsValueFitsDataTypeUploadedImage() {
        $file = [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ];
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_FILE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE));
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($fileObj, DbTableColumn::TYPE_IMAGE));
        $file['name'] = 'image_jpg';
        $file['type'] = 'image/jpeg';
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE));
        $file['type'] = 'image/png';
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE));
        $file['type'] = 'image/gif';
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE));
        $file['type'] = 'image/svg';
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE));

        $file['type'] = 'text/plain';
        $file['tmp_name'] = __DIR__ . '/files/test_file_jpg';
        static::assertEquals(['image'], DbRecordValueHelpers::isValueFitsDataType($file, DbTableColumn::TYPE_IMAGE, ['value_must_be_image' => 'image']));
        $fileObj = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
        static::assertEquals([], DbRecordValueHelpers::isValueFitsDataType($fileObj, DbTableColumn::TYPE_IMAGE));
    }

    public function testIsValidDbColumnValue() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test')
            ->valueIsNotNullable()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals([], DbRecordValueHelpers::isValidDbColumnValue($column, 'test'));
        static::assertEquals([], DbRecordValueHelpers::isValidDbColumnValue($column, ''));
        static::assertEquals(
            ['not null'],
            DbRecordValueHelpers::isValidDbColumnValue($column, null, ['value_cannot_be_null' => 'not null'])
        );
        $column->convertsEmptyStringToNull();
        static::assertEquals(['value_cannot_be_null'], DbRecordValueHelpers::isValidDbColumnValue($column, ''));
        $column->valueIsNullable();
        static::assertEquals([], DbRecordValueHelpers::isValidDbColumnValue($column, null));
        static::assertEquals([], DbRecordValueHelpers::isValidDbColumnValue($column, ''));
        // invalid valie
        $column = DbTableColumn::create(DbTableColumn::TYPE_INT, 'test')
            ->valueIsNotNullable()
            ->setAllowedValues(['abrakadabra']);
        static::assertEquals(['value_must_be_integer'], DbRecordValueHelpers::isValidDbColumnValue($column, 'not_int'));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Enum column [test] is required to have a list of allowed values
     */
    public function testInvalidColumnAllowedValuesForEnum() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test');
        DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $value argument must be a string, integer, float or array to be able to validate if it is within allowed values
     */
    public function testInvalidValueForAllowedValues() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['test']);
        DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $value argument must be a string, integer, float or array to be able to validate if it is within allowed values
     */
    public function testInvalidValueForAllowedValues2() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test')
            ->setAllowedValues(['test']);
        DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, $column);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $value argument must be a string, integer, float or array to be able to validate if it is within allowed values
     */
    public function testInvalidValueForAllowedValues3() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['test'])
            ->valueIsNotNullable();
        DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $value argument must be a string, integer, float or array to be able to validate if it is within allowed values
     */
    public function testInvalidValueForAllowedValues4() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test')
            ->valueIsNotNullable()
            ->setAllowedValues(['test']);
        DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null);
    }

    public function testIsValueWithinTheAllowedValuesOfTheEnumColumn() {
        $message1 = ['value_is_not_allowed'];
        $message2 = ['one_of_values_is_not_allowed'];
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test')
            ->valueIsNullable()
            ->setAllowedValues(['test' , 'test2']);
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test'));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2'));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null));
        static::assertEquals($message1, DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups'));
        static::assertEquals($message1, DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ''));
        static::assertEquals($message2, DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups']));
        static::assertEquals($message2, DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'ups']));
    }

    public function testIsValueWithinTheAllowedValuesOfTheNotEnumColumn() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test')
            ->valueIsNullable()
            ->setAllowedValues(['test' , 'test2']);
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test'));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'test2'));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test2']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test2']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['test', 'test']));
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, null));
        static::assertEquals(
            ['value_is_not_allowed'],
            DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, 'ups')
        );
        static::assertEquals(
            ['one_of_values_is_not_allowed'],
            DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ['ups'])
        );
        static::assertEquals(
            ['bad value'],
            DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn(
                $column,
                ['test', 'ups'],
                ['one_of_values_is_not_allowed' => 'bad value']
            )
        );
        static::assertEquals(
            ['no-no!'],
            DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, '', ['value_is_not_allowed' => 'no-no!'])
        );
        $column->convertsEmptyStringToNull();
        static::assertEquals([], DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn($column, ''));
    }

}