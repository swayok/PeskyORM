<?php


use PeskyORM\ORM\DbRecordValueHelpers;

class DbRecordValueHelpersTest extends \PHPUnit_Framework_TestCase {

    public function testGetErrorMessage() {
        static::assertEquals('test', DbRecordValueHelpers::getErrorMessage([], 'test'));
        static::assertEquals('not-a-test', DbRecordValueHelpers::getErrorMessage(['test' => 'not-a-test'], 'test'));
    }

    public function testNormalizeValue() {

    }

    public function testGetValueFormatterAndFormatsByType() {

    }

    public function testFormatTimestamp() {

    }

    public function testFormatDateOrTime() {

    }

    public function testFormatJson() {

    }

    public function testIsValidDbColumnValue() {

    }

}