<?php

namespace Tests\Orm;

use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use Tests\PeskyORMTest\TestingApp;
use Tests\PeskyORMTest\TestingSettings\TestingSettingsTable;

class StaticPropertiesAndMethodsTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::getPgsqlConnection();
    }
    
    public function testStaticMethodsInDbTables()
    {
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::getInstance());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::getInstance());
        
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::i());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::i());
    }
    
}