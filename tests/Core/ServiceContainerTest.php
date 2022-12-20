<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Exception\ServiceContainerException;
use PeskyORM\Exception\ServiceNotFoundException;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmins3TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmins4TableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingServiceContainerReplace;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTableStructure;
use PeskyORM\Utils\ServiceContainer;

class ServiceContainerTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        TestingApp::resetServiceContainer();
    }

    public function testGetInstance(): void
    {
        $instance = ServiceContainer::getInstance();
        static::assertInstanceOf(ServiceContainer::class, $instance);
        // replace
        ServiceContainer::replaceContainer(new TestingServiceContainerReplace());
        $instance = ServiceContainer::getInstance();
        static::assertInstanceOf(TestingServiceContainerReplace::class, $instance);
        // reset
        ServiceContainer::replaceContainer(null);
        $instance = ServiceContainer::getInstance();
        static::assertInstanceOf(ServiceContainer::class, $instance);
    }

    public function testInvalidGet(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Cannot find definition');
        ServiceContainer::getInstance()->get(static::class);
    }

    public function testInvalidMake1(): void
    {
        $this->expectException(ServiceContainerException::class);
        $this->expectExceptionMessageMatches(
            '%Concrete class \[.*TableStructureInterface\] for abstract'
            . ' \[.*TableStructureInterface\] is not instantiable.%'
        );
        ServiceContainer::getInstance()->make(TableStructureInterface::class);
    }

    public function testInvalidMake2(): void
    {
        $this->expectException(ServiceContainerException::class);
        $this->expectExceptionMessageMatches(
            '%Concrete class \[.*TableStructure\] for abstract'
            . ' \[.*TableStructure\] is not instantiable.%'
        );
        ServiceContainer::getInstance()->make(TableStructure::class);
    }

    public function testInvalidMake3(): void
    {
        $this->expectException(ServiceContainerException::class);
        $this->expectExceptionMessageMatches(
            '%Concrete class \[not a class\] for abstract'
            . ' \[not a class\] does not exist.%'
        );
        ServiceContainer::getInstance()->make('not a class');
    }

    public function testBinding(): void
    {
        $container = ServiceContainer::getInstance();
        $container->bind(RecordInterface::class, TestingAdmin::class);
        static::assertTrue($container->has(RecordInterface::class));
        static::assertFalse($container->has(TestingAdmin::class));

        $container->bind(TestingAdminsTable::class);
        static::assertTrue($container->has(TestingAdminsTable::class));

        $container->bind(TableStructureInterface::class, function () {
            return new TestingAdminsTableStructure();
        });
        static::assertTrue($container->has(TableStructureInterface::class));
    }

    public function testMakeWhenRegistered(): void
    {
        $container = ServiceContainer::getInstance();
        // can be instantiated many times
        $container->bind(TestingAdmin::class);
        $record1 = $container->make(TestingAdmin::class);
        $record2 = $container->make(TestingAdmin::class);
        static::assertInstanceOf(TestingAdmin::class, $record1);
        static::assertInstanceOf(TestingAdmin::class, $record2);
        static::assertNotSame($record1, $record2);

        // with arguments
        $container->bind(Record::class);
        static::assertTrue($container->has(Record::class));
        $record1 = $container->make(Record::class, [TestingAdminsTable::getInstance()]);
        $record2 = $container->make(Record::class, [TestingAdminsTable::getInstance()]);
        static::assertInstanceOf(Record::class, $record1);
        static::assertInstanceOf(Record::class, $record2);
        static::assertNotSame($record1, $record2);
        static::assertInstanceOf(TestingAdminsTable::class, $record1->getTable());
        static::assertInstanceOf(TestingAdminsTable::class, $record2->getTable());

        // singleton
        $container->bind(TestingAdminsTableStructure::class, singleton: true);
        static::assertTrue($container->has(TestingAdminsTableStructure::class));
        $structure1 = $container->make(TestingAdminsTableStructure::class);
        $structure2 = $container->make(TestingAdminsTableStructure::class);
        static::assertInstanceOf(TestingAdminsTableStructure::class, $structure1);
        static::assertInstanceOf(TestingAdminsTableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);

        // instance as closure
        $container->instance(TestingAdmins3TableStructure::class, function () {
            return new TestingAdmins4TableStructure();
        });
        static::assertTrue($container->has(TestingAdmins3TableStructure::class));
        $structure1 = $container->make(TestingAdmins3TableStructure::class);
        $structure2 = $container->make(TestingAdmins3TableStructure::class);
        static::assertInstanceOf(TestingAdmins4TableStructure::class, $structure1);
        static::assertInstanceOf(TestingAdmins4TableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);

        // instance as concrete
        $container->instance(
            TestingAdmins4TableStructure::class,
            new TestingAdmins3TableStructure()
        );
        static::assertTrue($container->has(TestingAdmins4TableStructure::class));
        $structure1 = $container->make(TestingAdmins4TableStructure::class);
        $structure2 = $container->make(TestingAdmins4TableStructure::class);
        static::assertInstanceOf(TestingAdmins3TableStructure::class, $structure1);
        static::assertInstanceOf(TestingAdmins3TableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);

        // instance as class
        $container->instance(
            TestingSettingsTableStructure::class,
        );
        static::assertTrue($container->has(TestingSettingsTableStructure::class));
        $structure1 = $container->make(TestingSettingsTableStructure::class);
        $structure2 = $container->make(TestingSettingsTableStructure::class);
        static::assertInstanceOf(TestingSettingsTableStructure::class, $structure1);
        static::assertInstanceOf(TestingSettingsTableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);
    }

    public function testMakeWhenNotRegistered(): void
    {
        $container = ServiceContainer::getInstance();
        // not bound and with arguments
        static::assertFalse($container->has(Record::class));
        $record1 = $container->make(Record::class, [TestingAdminsTable::getInstance()]);
        static::assertTrue($container->has(Record::class));
        $record2 = $container->make(Record::class, [TestingAdminsTable::getInstance()]);
        static::assertInstanceOf(Record::class, $record1);
        static::assertInstanceOf(Record::class, $record2);
        static::assertNotSame($record1, $record2);
        static::assertInstanceOf(TestingAdminsTable::class, $record1->getTable());
        static::assertInstanceOf(TestingAdminsTable::class, $record2->getTable());
    }

    public function testUnbind(): void
    {
        $container = ServiceContainer::getInstance();
        static::assertFalse($container->has(RecordInterface::class));
        $container->bind(RecordInterface::class, TestingAdmin::class);
        static::assertTrue($container->has(RecordInterface::class));
        $container->make(RecordInterface::class);
        $container->unbind(RecordInterface::class);
        static::assertFalse($container->has(RecordInterface::class));
    }

    public function testAlias(): void
    {
        // not singleton
        $container = ServiceContainer::getInstance();
        static::assertFalse($container->has(RecordInterface::class));
        $container->bind(RecordInterface::class, TestingAdmin::class);
        static::assertTrue($container->has(RecordInterface::class));

        $container->alias(RecordInterface::class, 'record');
        static::assertTrue($container->has('record'));
        $record = $container->make('record');
        static::assertInstanceOf(TestingAdmin::class, $record);

        // singleton alias
        $container->bind(TestingAdminsTableStructure::class, singleton: true);
        static::assertTrue($container->has(TestingAdminsTableStructure::class));
        $structure1 = $container->make(TestingAdminsTableStructure::class);
        static::assertInstanceOf(TestingAdminsTableStructure::class, $structure1);
        $container->alias(TestingAdminsTableStructure::class, 'admins');
        static::assertTrue($container->has('admins'));
        $structure2 = $container->make('admins');
        static::assertInstanceOf(TestingAdminsTableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);

        // instance alias
        $container->instance(TestingSettingsTableStructure::class);
        static::assertTrue($container->has(TestingSettingsTableStructure::class));
        $structure1 = $container->make(TestingSettingsTableStructure::class);
        static::assertInstanceOf(TestingSettingsTableStructure::class, $structure1);
        $container->alias(TestingSettingsTableStructure::class, 'settings');
        $structure2 = $container->make('settings');
        static::assertInstanceOf(TestingSettingsTableStructure::class, $structure2);
        static::assertSame($structure1, $structure2);
    }

}