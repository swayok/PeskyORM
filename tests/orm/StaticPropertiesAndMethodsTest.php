<?php


use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;
use PeskyORMTest\TestingSettings\TestingSetting;
use PeskyORMTest\TestingSettings\TestingSettingsTable;
use PeskyORMTest\TestingSettings\TestingSettingsTableStructure;

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

    public function testStaticMethodsInDbRecords() {
        static::assertInstanceOf(TestingSetting::class, TestingSetting::_());
        static::assertInstanceOf(TestingSetting::class, TestingSetting::newEmptyRecord());
        static::assertInstanceOf(TestingSettingsTable::class, TestingSetting::getTable());
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSetting::getTableStructure());

        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::_());
        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::newEmptyRecord());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdmin::getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdmin::getTableStructure());
    }
}