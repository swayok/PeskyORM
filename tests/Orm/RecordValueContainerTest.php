<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;

class RecordValueContainerTest extends BaseTestCase
{
    
    public function testConstructAndClone(): void
    {
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        $clone = clone $valueObj;
        
        static::assertNull($this->getObjectPropertyValue($valueObj, 'value'));
        static::assertNull($this->getObjectPropertyValue($valueObj, 'rawValue'));
        static::assertFalse($this->getObjectPropertyValue($valueObj, 'isFromDb'));
        static::assertEquals([], $this->getObjectPropertyValue($valueObj, 'payload'));
        static::assertTrue($valueObj->getColumn()->isPrimaryKey());
        static::assertInstanceOf(TestingAdmin::class, $this->getObjectPropertyValue($valueObj, 'record'));
        static::assertInstanceOf(TestingAdmin::class, $valueObj->getRecord());
        
        static::assertNull($this->getObjectPropertyValue($clone, 'value'));
        static::assertNull($this->getObjectPropertyValue($clone, 'rawValue'));
        static::assertFalse($this->getObjectPropertyValue($clone, 'isFromDb'));
        static::assertEquals([], $this->getObjectPropertyValue($clone, 'payload'));
        static::assertTrue($clone->getColumn()->isPrimaryKey());
        static::assertInstanceOf(TestingAdmin::class, $this->getObjectPropertyValue($clone, 'record'));
        static::assertInstanceOf(TestingAdmin::class, $clone->getRecord());
    }
    
    public function testInvalidGetPayload(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($key) must be of type ?string');
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->getPayload($this);
    }
    
    public function testInvalidRemovePayload(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($key) must be of type ?string');
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->removePayload($this);
    }

    public function testInvalidAddPayload(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Adding payload to RecordValue before value is provided is not allowed. Detected in:'
        );
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->addPayload('test', 'test');
    }

    public function testInvalidRememberPayload(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Adding payload to RecordValue before value is provided is not allowed. Detected in:'
        );
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->rememberPayload('test', function () {
            return 'test';
        });
    }
    
    public function testPayload(): void
    {
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        $valueObj->setValue('test', 'test', false);
        $valueObj->addPayload('test2', '2');
        $valueObj->addPayload('test', '1');
        static::assertEquals(['test2' => '2', 'test' => '1'], $valueObj->getPayload());
        static::assertEquals('2', $valueObj->getPayload('test2'));
        static::assertEquals('2', $valueObj->getPayload('test2', 'default'));
        static::assertEquals('default', $valueObj->getPayload('test3', 'default'));
        static::assertEquals(['test2' => '2', 'test' => '1'], $valueObj->getPayload());
        static::assertEquals(null, $valueObj->getPayload('test3'));
        static::assertEquals('default_remembered', $valueObj->rememberPayload('test3', function () {
            return 'default_remembered';
        }));
        static::assertEquals('default_remembered', $valueObj->getPayload('test3'));
        static::assertEquals(
            ['test2' => '2', 'test' => '1', 'test3' => 'default_remembered'],
            $valueObj->getPayload()
        );
        $valueObj->removePayload('test2');
        static::assertEquals(
            ['test' => '1', 'test3' => 'default_remembered'],
            $valueObj->getPayload()
        );
        $valueObj->removePayload();
        static::assertEquals([], $valueObj->getPayload());
    }
    
    public function testIsFromDb(): void
    {
        $record = new TestingAdmin();
        $column = $record->getTable()->getPkColumn();
        $valueObj = $column->getNewRecordValueContainer($record);
        static::assertFalse($valueObj->isItFromDb());
        $valueObj->setIsFromDb(true);
        static::assertTrue($valueObj->isItFromDb());
    }
    
    public function testInvalidGetValue(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Value for PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin(#null)->parent_id is not set");
        $record = new TestingAdmin();
        $column = $record->getTable()->getColumn('parent_id');
        $column->getNewRecordValueContainer($record)->getValue();
    }

    public function testDuplicateSetValueMethodCall(): void {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Value for .*?->parent_id aready set\. You need to create a new instance .*?%"
        );
        $record = TestingAdmin::newEmptyRecord();
        $column = $record->getTable()->getColumn('parent_id');
        $valueObj = $column->getNewRecordValueContainer($record);
        $valueObj->setValue('1', '1', true);
        $valueObj->setValue('2', '2', false);
    }

    public function testRawValue(): void
    {
        $record = TestingAdmin::newEmptyRecord();
        $column = $record->getTable()->getColumn('parent_id');

        $valueObj = $column->getNewRecordValueContainer($record);
        $valueObj->setValue('1', '1', true);
        static::assertTrue($valueObj->hasValue());
        static::assertTrue($valueObj->isItFromDb());
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());
        static::assertEquals([], $valueObj->getPayload());

        $valueObj = $column->getNewRecordValueContainer($record);
        $valueObj->setValue('2', '2', false);
        static::assertFalse($valueObj->isItFromDb());
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals('2', $valueObj->getValue());
        static::assertEquals([], $valueObj->getPayload());
    }
    
}
