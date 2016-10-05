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
     * @param object $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    private function callObjectMethod($object, $methodName, array $args = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
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
        self::assertEquals('2016-09-01', DbRecordValueHelpers::formatTimestamp($valueObj, 'date'));
        self::assertEquals('01:02:03', DbRecordValueHelpers::formatTimestamp($valueObj, 'time'));
        self::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatTimestamp($valueObj, 'unix_ts'));
    }

    public function testFormatDateOrTime() {
        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_DATE, '2016-09-01');
        self::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatDateOrTime($valueObj, 'unix_ts'));

        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_TIME, '12:34:56');
        self::assertEquals(strtotime($valueObj->getValue()), DbRecordValueHelpers::formatDateOrTime($valueObj, 'unix_ts'));
    }

    public function testFormatJson() {
        $value = ['test' => 'value', 'val'];
        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_JSON, json_encode($value));
        self::assertEquals($value, DbRecordValueHelpers::formatJson($valueObj, 'array'));
        self::assertEquals(json_decode(json_encode($value)), DbRecordValueHelpers::formatJson($valueObj, 'object'));

        $valueObj = $this->createDbRecordValue(DbTableColumn::TYPE_JSONB, '"invalidjson');
        self::assertEquals(false, DbRecordValueHelpers::formatJson($valueObj, 'array'));
        self::assertEquals(false, DbRecordValueHelpers::formatJson($valueObj, 'object'));
    }

    public function testIsValueFitsDataTypeBool() {
        $message = ['value_must_be_boolean'];
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_BOOL));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_BOOL));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_BOOL));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_BOOL));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_BOOL));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_BOOL));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_BOOL));
    }

    public function testIsValueFitsDataTypeInt() {
        $message = ['value_must_be_integer'];
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11, DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11, DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11', DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11', DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0000, DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_INT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.0000', DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.1, DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('str', DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('a1', DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1a', DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_INT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_INT));
    }

    public function testIsValueFitsDataTypeFloat() {
        $message = ['value_must_be_float'];
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(11.2, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('11.2', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(-11.3, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('-11.3', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1.01, DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.01', DbTableColumn::TYPE_FLOAT));
        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1.001', DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('str', DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('a1', DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1a', DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_FLOAT));
        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_FLOAT));
    }

//    public function testIsValueFitsDataTypeDate() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_DATE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_DATE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_DATE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_DATE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_DATE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_DATE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_DATE));
//    }
//
//    public function testIsValueFitsDataTypeTime() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_TIME));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_TIME));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIME));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIME));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIME));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_TIME));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_TIME));
//    }
//
//    public function testIsValueFitsDataTypeTimestamp() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_TIMESTAMP));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_TIMESTAMP));
//    }
//
//    public function testIsValueFitsDataTypeTimezoneOffset() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_TIMEZONE_OFFSET));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_TIMEZONE_OFFSET));
//    }
//
//    public function testIsValueFitsDataTypeIpV4Address() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_IPV4_ADDRESS));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_IPV4_ADDRESS));
//    }
//
//    public function testIsValueFitsDataTypeJson() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_JSON));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_JSON));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_JSON));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_JSON));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_JSON));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_JSON));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_JSON));
//    }
//
//    public function testIsValueFitsDataTypeEmail() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_FILE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_FILE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_FILE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_FILE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_FILE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_FILE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_FILE));
//    }
//
//    public function testIsValueFitsDataTypeString() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_IMAGE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_IMAGE));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_IMAGE));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_IMAGE));
//    }
//
//    public function testIsValueFitsDataTypeUploadedFile() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_EMAIL));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_EMAIL));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_EMAIL));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_EMAIL));
//    }
//
//    public function testIsValueFitsDataTypeUploadedImage() {
//        $message = ['value_must_be_boolean'];
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(true, DbTableColumn::TYPE_STRING));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(false, DbTableColumn::TYPE_STRING));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(1, DbTableColumn::TYPE_STRING));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('1', DbTableColumn::TYPE_STRING));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType('0', DbTableColumn::TYPE_STRING));
//        self::assertEquals([], DbRecordValueHelpers::isValueFitsDataType(0, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('true', DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('false', DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(null, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(-1, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.25, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(1.0, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('1.0', DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType(0.0, DbTableColumn::TYPE_STRING));
//        self::assertEquals($message, DbRecordValueHelpers::isValueFitsDataType('0.0', DbTableColumn::TYPE_STRING));
//    }

    public function testIsValueWithinTheAllowedValuesOfTheColumn() {

    }

    public function testIsValidDbColumnValue() {

    }

}