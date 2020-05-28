<?php

namespace Tests\Orm;

use PHPUnit\Framework\TestCase;
use Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use Tests\PeskyORMTest\TestingApp;
use Tests\PeskyORMTest\TestingSettings\TestingSettingsTable;

class StaticPropertiesAndMethodsTest extends TestCase {

    public static function setUpBeforeClass(): void {
        TestingApp::getPgsqlConnection();
    }

    public function testStaticMethodsInDbTables() {
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::getInstance());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::getInstance());

        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::i());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::i());
    }

}