<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Utils\ValueTypeValidators;

class ValueTypeValidatorsTest extends BaseTestCase
{

    public function testIsInteger(): void
    {
        $positive = [
            0,
            '0',
            '-0',
            1,
            -1,
            '1',
            '-1',
            65535,
            100000,
            -65535,
            -100000,
            '65535',
            '-65535',
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isInteger($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            [1],
            '+1',
            '+0',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isInteger($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsBoolean(): void
    {
        $positive = [
            true,
            false,
            0,
            1,
            '0',
            '1',
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isBoolean($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1.0,
            0.0,
            'true',
            'false',
            't',
            'f',
            ' 1',
            '1 ',
            '0 ',
            ' 0',
            ' ',
            $this,
            [],
            [1],
            '+1',
            '-0',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isBoolean($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsTimezoneOffset(): void
    {
        $positive = [
            '+00:00',
            '+01:00',
            '+12:00',
            '-12:00',
            '-01:00',
            '+14:00',
            new \DateTimeZone('-12:00'),
            new \DateTimeZone('+14:00'),
            new \DateTimeZone('GMT+04:45'), //< converted to +04:45
            new CarbonTimeZone(),
            new CarbonTimeZone('Europe/Amsterdam'),
            new CarbonTimeZone('CEST'),
            new CarbonTimeZone('UTC'),
            new CarbonTimeZone('-12:00'),
            new CarbonTimeZone('+14:00'),
            new CarbonTimeZone('GMT+04:45'),
            0,
            '0',
            60,
            '60',
            -60,
            '-60',
            50400, //< +14:00
            '50400',
            50400 / 2,
            '-43200',
            -43200, //> -12:00
            -43200 / 2,
        ];
        foreach ($positive as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertTrue(
                ValueTypeValidators::isTimezoneOffset($value),
                "\$positive[{$index}]: " . $json
            );
        }

        $negative = [
            null,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            '-12:01',
            '+14:01',
            '+1:01',
            '+01:1',
            '01:00',
            50460, //< +14:01
            '50460', //< +14:01
            -43260, //> -12:01
            '-43260', //> -12:01
            // not a minutes (value % 60 !== 0)
            -1,
            '-1',
            -119,
            '-119',
            1,
            '1',
            119,
            '119',
            // out of range
            new \DateTimeZone('-12:01'),
            new \DateTimeZone('+14:01'),
            new CarbonTimeZone('-12:01'),
            new CarbonTimeZone('+14:01'),
            // timezone name
            new \DateTimeZone('Europe/Amsterdam'),
            new \DateTimeZone('CEST'),
            new \DateTimeZone('UTC'),
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isTimezoneOffset($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsTimezoneName(): void
    {
        date_default_timezone_set('America/New_York');
        $positive = [
            'Europe/Amsterdam',
            'Europe/Andorra',
            'UTC',
            'Africa/Tripoli',
            new \DateTimeZone('Europe/Amsterdam'),
            new CarbonTimeZone('Europe/Andorra'),
            new \DateTimeZone('UTC'),
            new \DateTimeZone('Africa/Tripoli'),
            new CarbonTimeZone(),
            new CarbonTimeZone('Pacific/Kwajalein'),
            new CarbonTimeZone('America/Los_Angeles'),
            new CarbonTimeZone('UTC'),
            new CarbonTimeZone('Asia/Tokyo'),
        ];
        foreach ($positive as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertTrue(
                ValueTypeValidators::isTimezoneName($value),
                "\$positive[{$index}]: " . $json
            );
        }

        $negative = [
            null,
            1.1,
            0,
            -1,
            1,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            '-12:01',
            '+14:01',
            '+1:01',
            '+01:1',
            '01:00',
            '+00:00',
            '+01:00',
            '+12:00',
            '-12:00',
            '-01:00',
            '+14:00',
            'Invalid/Invalid',
            'Europe',
            'Europe/',
            'Europe/Invalid',
            new \DateTimeZone('-12:00'),
            new \DateTimeZone('+14:00'),
            new \DateTimeZone('GMT+04:45'),
            new CarbonTimeZone('-12:00'),
            new CarbonTimeZone('+14:00'),
            new CarbonTimeZone('GMT+04:45'),
            // not present in DateTimeZone::listIdentifiers()
            'CEST',
            'GMT',
            new \DateTimeZone('CEST'),
            new \DateTimeZone('GMT'),
            new CarbonTimeZone('CEST'),
            new CarbonTimeZone('GMT'),
            // shortcuts are not allowed too
            'Israel',
            'Japan',
            new \DateTimeZone('Israel'),
            new CarbonTimeZone('Japan'),
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isTimezoneName($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsTimezone(): void
    {
        $positive = [
            '+00:00',
            '+01:00',
            '+12:00',
            '-12:00',
            '-01:00',
            '+14:00',
            new \DateTimeZone('-12:00'),
            new \DateTimeZone('+14:00'),
            new \DateTimeZone('GMT+04:45'), //< converted to +04:45
            new CarbonTimeZone(),
            new CarbonTimeZone('-12:00'),
            new CarbonTimeZone('+14:00'),
            new CarbonTimeZone('GMT+04:45'),
            0,
            '0',
            -60,
            '-60',
            60,
            '60',
            50400, //< +14:00
            '50400', //< +14:00
            50400 / 2,
            -43200, //> -12:00
            '-43200', //> -12:00
            -43200 / 2,
            // names
            'Europe/Amsterdam',
            'Europe/Andorra',
            'UTC',
            'Africa/Tripoli',
            new \DateTimeZone('Europe/Amsterdam'),
            new CarbonTimeZone('Europe/Andorra'),
            new \DateTimeZone('UTC'),
            new \DateTimeZone('Africa/Tripoli'),
            new CarbonTimeZone(),
            new CarbonTimeZone('Pacific/Kwajalein'),
            new CarbonTimeZone('America/Los_Angeles'),
            new CarbonTimeZone('UTC'),
            new CarbonTimeZone('Asia/Tokyo'),
            //< Carbon converts these to offset
            new CarbonTimeZone('CEST'),
            new CarbonTimeZone('GMT'),
            new CarbonTimeZone('Japan'),
        ];
        foreach ($positive as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertTrue(
                ValueTypeValidators::isTimezone($value),
                "\$positive[{$index}]: " . $json
            );
        }

        $negative = [
            null,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            // invalid format
            '+1:01',
            '+01:1',
            '01:00',
            // not a minutes
            -1,
            '-1',
            -119,
            '-119',
            1,
            '1',
            119,
            '119',
            // out of range
            '-12:01',
            '+14:01',
            50460, //< +14:01
            -43260, //> -12:01
            new CarbonTimeZone('+14:01'),
            // invalid names
            'Europe/Invalid',
            'Invalid/Invalid',
            'Invalid/',
            'Invalid',
            // not present in DateTimeZone::listIdentifiers()
            'CEST',
            'GMT',
            new \DateTimeZone('CEST'),
            new \DateTimeZone('GMT'),
            // shortcuts are not allowed too
            'Israel',
            'Japan',
            new \DateTimeZone('Israel'),
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isTimezone($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsTimestamp(): void
    {
        $positive = [
            '2022-12-07 15:00:00',
            '2022-12-07',
            '2022-12-31 23:59:59',
            '2022-12-01 00:00:00',
            '2022-12-1 00:00:00',
            '2022-12-1 01:00:00 AM',
            '2022-12-1',
            '22-12-01 00:00:00',
            '22-12-01 01:00:00 AM',
            '22-12-01',
            '22-2-1 00:00:00',
            '22-2-1 0:0:0',
            '22-2-1',
            '02/21/2018',
            '02/21/2018 12:00:00',
            '02/21/18 12:00:00',
            '02/21/18 12:00:00 AM',
            '2/21/18',
            '2/21/18 12:00:00',
            '2/21/18 12:00:00 AM',
            '2/2/2018 1:0:0',
            'December 07, 2022',
            'December 7, 2022',
            'December 7, 2022, 12:00:00',
            'December 7, 2022, 12:00:00 AM',
            '12:00:00, December 7, 2022',
            'Dec 07, 2022',
            'Dec 7, 2022',
            'Dec 7, 2022, 12:00:00',
            'Dec 7, 2022, 12:00:00 AM',
            '11:00:00, Dec 7, 2022',
            '12:00:00',
            '12:00:00.865',
            '12:00:00 AM',
            '12:00:00 PM',
            '4.10 PM',
            '2009-06-15T13:45:30',
            '2009-06-15T13:45:30Z',
            '2009-06-15T13:45:30GMT',
            '2009-06-15T13:45:30GMT+3',
            '2009-06-15T13:45:30UTC+3',
            '20090615+3',
            '2009-06-15+3',
            '2009-06-15+03:00',
            '2009-06-15-03:00',
            '2009-06-15T14:00:00-03:00',
            '2018-12-25 23:50:55.999',
            '2018-12-25 23:50:55.999 +0530',
            '2018-12-25 23:50:55.999 GMT+05:30',
            '+1 day',
            'now',
            1,
            '1',
            1.1,
            '1.1',
            time(),
            Carbon::now(),
            new \DateTime(),
        ];
        foreach ($positive as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertTrue(
                ValueTypeValidators::isTimestamp($value),
                "\$positive[{$index}]: " . $json
            );
        }

        $negative = [
            null,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            0,
            '0',
            -1,
            '-1',
            -0.1,
            '-0.1',
            '21/02/2018',
            '21/2/2018',
            '21/02/18',
            '21/2/18',
            '02-21-2018 12:00:00',
            '2-21-2018 12:00:00',
            '02-21-18 12:00:00',
            '2-21-18 12:00:00',
            '2018-21-02 12:00:00',
            '18-21-02 12:00:00',
            '11:00:00 AM, Dec 7, 2022',
            '2022-12-1 00:00:00 AM',
            '2022-12-31 23:59:59 AM',
            '22-2-1 1:0:0 AM',
            '2/2/2018 1:0:0 AM',
            '10:15:35.889 AM',
            '10:15:35.889 PM',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isTimestamp($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsIpV4Address(): void
    {
        $positive = [
            '0.0.0.0',
            '0.0.0.1',
            '255.255.255.255',
            '200.200.200.200',
            '100.100.100.100',
            '127.0.0.0',
        ];
        foreach ($positive as $index => $value) {
            $json = is_object($value)
                ? get_class($value) . '(' . json_encode($value) . ')'
                : json_encode($value);
            static::assertTrue(
                ValueTypeValidators::isIpV4Address($value),
                "\$positive[{$index}]: " . $json
            );
        }

        $negative = [
            null,
            1,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            '0.11.11',
            '0.11.11.11.11',
            '256.0.0.0',
            '0.256.0.0',
            '0.0.256.0',
            '0.0.0.256',
            '192.168.1.1/24',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isIpV4Address($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsEmail(): void
    {
        $positive = [
            'test@test.ru',
            'test@test.com.qq',
            'test@test-test.com.qq',
            'test@test.com.qqqqqqqqqqqqqqq',
            '111@test.com',
            '1.1_1@test.com',
            '_@test.com', //< we can't really validate everything with 1 regexp
            'qq-qq@test.com',
            'qq$qq@test.com', //< yep, some special symbols are allowed but not widely used
            'q@1.com',
            'qqq@a1.com',
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isEmail($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            [''],
            '',
            '-12:01',
            'test@test',
            'test@',
            '@test',
            'test@test_test.com.qq',
            'test@test@test.com.qq',
            'test test@test.com.qq',
            'testtest@ test.com.qq',
            'testtest@te st.com.qq',
            'testtest @test.com.qq',
            '.@test.com',
            'q.@test.com',
            'q@_.com',
            'qqq@a1.com ', //< trimming expected outside
            ' qqq@a1.com', //< trimming expected outside
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isEmail($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsJson(): void
    {
        $positive = [
            'null',
            'true',
            'false',
            '1',
            '2.2',
            '""',
            '"str"',
            '[]',
            '["qqq","www"]',
            '{}',
            '{"key":"value"}',
            '{"key"}', //< contents are not validated
            '{key}', //< contents are not validated
            '[key]', //< contents are not validated
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isJsonEncodedString($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1.1,
            true,
            false,
            'str',
            $this,
            [],
            ['qq'],
            ['qq' => 'ww'],
            '',     // json_decode('') => null (failed to decode)
            'str',  // json_decode('str') => null (failed to decode)
            '[,]',
            '["qq",]',
            '{,}',
            '{"key":"value",}',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isJsonEncodedString($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsJsonable(): void
    {
        $positive = [
            null,
            true,
            false,
            '',
            1,
            2.2,
            'str',
            [],
            ['qq'],
            ['qq' => 'ww'],
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isJsonable($value, false),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }
        static::assertTrue(
            ValueTypeValidators::isJsonable($this, true),
            'Objects allowed'
        );

        $negative = [
            $this,
            // already encoded:
            '"str"',
            '""',
            'null',
            'true',
            'false',
            '1',
            '2.2',
            '[]',
            '["aaa"]',
            '[aaa]',    //< contents are not validated
            '["aaa",]', //< contents are not validated
            '{}',
            '{"aaa":"aa"}',
            '{aaa}',       //< contents are not validated
            '{"aaa":"",}', //< contents are not validated
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isJsonable($value, false),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsJsonArray(): void
    {
        $positive = [
            '[]',
            '["aaa"]',
            '[aaa]',    //< contents are not validated
            [],
            ['qq'],
            ['qq', '22'],
            [2 => 'qq', '22'],
            ['2' => 'qq', '22'], //< numeric keys converted to int
            [$this],    //< contents are not validated
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isJsonArray($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            true,
            false,
            '',
            1,
            2.2,
            'str',
            '"str"',
            '""',
            'null',
            'true',
            'false',
            '1',
            '2.2',
            '["aaa",]',
            '{}',
            '{"aaa":"aa"}',
            '{"aaa":"",}',
            '{aaa}',
            ['qq' => 'ww'],
            ['2' => 'qq', '22', 'a' => '33'],
            [2 => 'qq', '22', 'a' => '33'],
            $this,
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isJsonArray($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsJsonObject(): void
    {
        $positive = [
            '{}',
            '{"aaa":"aa"}',
            '{aaa}',       //< contents are not validated
            [],
            ['qq' => 'ww'],
            ['2' => 'qq', '22', 'a' => '33'],
            [2 => 'qq', '22', 'a' => '33'],
            [2 => $this, '22', 'a' => '33'], //< contents are not validated
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isJsonObject($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            true,
            false,
            '',
            1,
            2.2,
            'str',
            ['qq'],
            ['qq', '22'],
            [2 => 'qq', '22'],
            ['2' => 'qq', '22'], //< numeric keys converted to int
            $this,
            '"str"',
            '""',
            'null',
            'true',
            'false',
            '1',
            '2.2',
            '[]',
            '["aaa"]',
            '[aaa]',
            '["aaa",]',
            '{"aaa":"",}',
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isJsonObject($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsIndexedArray(): void
    {
        $positive = [
            [],
            [''],
            [[]],
            [1, 5, 8],
            [0 => 1, 1 => 5, 2 => 8],
            [0 => 1, 2 => 5, 4 => 8],
            ['0' => 1, 2 => 5, '4' => 8],
            [2 => 1, 5, 8],
            ['2' => 1, 5, 8],
            ['a', 'b', 'c', 1],
            [2 => 'a', 'b', 'c', 1 => []],
            ['2' => 'a', 'b', 'c', 1, []],
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isIndexedArray($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1,
            1.1,
            true,
            false,
            'str',
            $this,
            ['1a' => 1, 5],
            ['a1' => 1, 5],
            ['' => 1],
            ['' => 1, 5],
            ['1 ' => 1, 5],
            [' 1' => 1, 5],
            [' 1 ' => 1, 5],
            ['00001' => 1],
            ['00001' => 1, 2],
            ['2' => 1, 'b' => 5, 'a' => 8, 10],
            [1, 5, 'a' => 8],
            [0 => 1, 1 => 5, 'a' => 8],
            [0 => 1, 2 => 5, 'a' => 8],
            [2 => 1, 4 => 5, 'a' => 8],
            [2 => 1, 'b' => 5, 'a' => 8],
            ['2' => 1, 'b' => 5, 'a' => 8],
            ['2' => 1, 'b' => 5, 'a' => 8, 5 => 10],
            [2 => 1, 'b' => 5, 'a' => 8, 5 => 10],
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isIndexedArray($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

    public function testIsAssociativeArray(): void
    {
        $positive = [
            [],
            ['1a' => 1, 5],
            ['a1' => 1, 5],
            ['' => 1],
            ['' => 1, 5],
            ['1 ' => 1, 5],
            [' 1' => 1, 5],
            [' 1 ' => 1, 5],
            ['00001' => 1],
            ['00001' => 1, 2],
            ['2' => 1, 'b' => 5, 'a' => 8, 10],
            [1, 5, 'a' => 8],
            [0 => 1, 1 => 5, 'a' => 8],
            [0 => 1, 2 => 5, 'a' => 8],
            [2 => 1, 4 => 5, 'a' => 8],
            [2 => 1, 'b' => 5, 'a' => 8],
            ['2' => 1, 'b' => 5, 'a' => 8],
            ['2' => 1, 'b' => 5, 'a' => 8, 5 => 10],
            [2 => 1, 'b' => 5, 'a' => 8, 5 => 10],
        ];
        foreach ($positive as $index => $value) {
            static::assertTrue(
                ValueTypeValidators::isAssociativeArray($value),
                "\$positive[{$index}]: " . json_encode($value)
            );
        }

        $negative = [
            null,
            1,
            1.1,
            true,
            false,
            'str',
            $this,
            [''],
            [[]],
            [1, 5, 8],
            [0 => 1, 1 => 5, 2 => 8],
            [0 => 1, 2 => 5, 4 => 8],
            ['0' => 1, 2 => 5, '4' => 8],
            [2 => 1, 5, 8],
            ['2' => 1, 5, 8],
            ['a', 'b', 'c', 1],
            [2 => 'a', 'b', 'c', 1 => []],
            ['2' => 'a', 'b', 'c', 1, []],
        ];
        foreach ($negative as $index => $value) {
            $json = is_object($value) ? get_class($value) : json_encode($value);
            static::assertFalse(
                ValueTypeValidators::isAssociativeArray($value),
                "\$negative[{$index}]: " . $json
            );
        }
    }

}