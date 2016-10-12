<?php


use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORMTest\TestingApp;
use PeskyORMTest\TestingSettings\TestingSettingsTable;

class StaticPropertiesAndMethodsTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::init();
    }

    public function testStaticMethodsInDbTables() {
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::getInstance());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::getInstance());

        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::i());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::i());
    }

}