<?php

namespace Tests\Orm;

use PeskyORM\ORM\RecordValueFormatters;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingFormatters\TestingFormatter;

class RecordValueFormattersTest extends BaseTestCase
{
    
    public function testTimestampFormatters()
    {
        $formatters = RecordValueFormatters::getTimestampFormatters();
        static::assertIsArray($formatters);
        static::assertArrayHasKey(RecordValueFormatters::FORMAT_DATE, $formatters);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_DATE]);
        static::assertArrayHasKey(RecordValueFormatters::FORMAT_TIME, $formatters);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_TIME]);
        static::assertArrayHasKey(RecordValueFormatters::FORMAT_UNIX_TS, $formatters);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_UNIX_TS]);
        static::assertArrayHasKey(RecordValueFormatters::FORMAT_CARBON, $formatters);
        static::assertInstanceOf(\Closure::class, $formatters[RecordValueFormatters::FORMAT_CARBON]);
        
        $record = TestingFormatter::fromArray(['created_at' => date('Y-m-d H:i:s')]);
        $record->getValueContainer('');
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
        $this->expectExceptionMessage("Tests\PeskyORMTest\TestingFormatters\TestingFormatter(no PK value)->created_at contains invalid date-time value: [invalid_date]");
        $record = TestingFormatter::newEmptyRecord();
        $valueContainer = $record->getValueContainer('created_at');
        $valueContainer->setRawValue('invalid_date', 'invalid_date', false);
        $valueContainer->setValidValue('invalid_date', 'invalid_date');
        $formatter = RecordValueFormatters::getTimestampToDateFormatter();
        static::assertInstanceOf(\Closure::class, $formatter);
        $formatter($valueContainer);
    }
}