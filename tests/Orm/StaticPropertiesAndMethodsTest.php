<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTable;

class StaticPropertiesAndMethodsTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::getPgsqlConnection();
    }
    
    public function testStaticMethodsInDbTables(): void
    {
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::getInstance());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::getInstance());
        
        static::assertInstanceOf(TestingSettingsTable::class, TestingSettingsTable::i());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdminsTable::i());
    }
    
}