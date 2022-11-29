<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class RecordValueTest extends BaseTestCase
{
    
    protected function getClonedColumn(string $columnName): TableColumnInterface
    {
        return clone TestingAdminsTableStructure::getColumn($columnName);
    }
    
    public function testConstructAndClone(): void
    {
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
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
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->getPayload($this);
    }
    
    public function testInvalidRemovePayload(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($key) must be of type ?string');
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
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
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
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
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->rememberPayload('test', function () {
            return 'test';
        });
    }
    
    public function testPayload(): void
    {
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
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
        $valueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertFalse($valueObj->isItFromDb());
        $valueObj->setIsFromDb(true);
        static::assertTrue($valueObj->isItFromDb());
    }
    
    public function testInvalidDefaultValue1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'parent_id'.*? is not set.%"
        );
        $valueObj = new RecordValue(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues(),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'parent_id'.*? is not valid\. Errors: Null value is not allowed\.%"
        );
        $valueObj = new RecordValue(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues()
                ->setDefaultValue(null),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches(
            "%Default value received from validDefaultValueGetter Closure for column .*?'parent_id'.*? is not valid\. Errors: Null value is not allowed\.%"
        );
        $valueObj = new RecordValue(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues()
                ->setValidDefaultValueGetter(function () {
                    return null;
                }),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'language'.*? is not valid\. Errors: Value must be a string\.%"
        );
        $langCol = clone TestingAdminsTableStructure::getColumn('language');
        $langCol->setDefaultValue(new \stdClass());
        $valueObj = new RecordValue($langCol, TestingAdmin::_());
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        $valueObj->getDefaultValue();
    }
    
    public function testDefaultValue(): void
    {
        $record = TestingAdmin::_();
        
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('parent_id'), $record);
        static::assertFalse($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertNull($valueObj->getDefaultValueOrNull());
        
        $langCol = TestingAdminsTableStructure::getColumn('language');
        $valueObj = new RecordValue($langCol, $record);
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertEquals($langCol->getValidDefaultValue(), $valueObj->getDefaultValue());
        static::assertEquals($langCol->getValidDefaultValue(), $valueObj->getValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        
        $langCol->setDefaultValue(function () {
            return 'de';
        });
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        static::assertEquals('de', $valueObj->getDefaultValue());
        static::assertEquals('de', $valueObj->getValueOrDefault());
    
        $langCol->setDefaultValue(DbExpr::create('de'));
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        static::assertInstanceOf(DbExpr::class, $valueObj->getDefaultValue());
        static::assertEquals('(de)', $valueObj->getDefaultValue()->get());
        static::assertInstanceOf(DbExpr::class, $valueObj->getValueOrDefault());
        static::assertEquals('(de)', $valueObj->getDefaultValue()->get());
        
        $valueObj->setValue('ru', 'ru', false);
        static::assertEquals('ru', $valueObj->getValue());
        
        $idColValueObj = new RecordValue(TestingAdminsTableStructure::getPkColumn(), $record);
        static::assertTrue($idColValueObj->hasValueOrDefault());
        $langColValueObj = new RecordValue($langCol, $record);
        $record->reset()
            ->updateValue('id', 1, true);
        static::assertFalse($langColValueObj->hasValueOrDefault());
        $idColValueObj->setValue(2, 2, false);
        static::assertTrue($idColValueObj->hasValueOrDefault());
    }
    
    public function testInvalidGetValue(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Value for PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin(#null)->parent_id is not set");
        $valueObj = new RecordValue(
            TestingAdminsTableStructure::getColumn('parent_id'),
            TestingAdmin::newEmptyRecord()
        );
        $valueObj->getValue();
    }

    public function testRawValue(): void
    {
        $record = TestingAdmin::_();
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('parent_id'), $record);
        
        $valueObj->setValue('1', '1', true);
        static::assertTrue($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertTrue($valueObj->isItFromDb());
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());
        static::assertEquals([], $valueObj->getPayload());

        $valueObj->setValue('2', '2', false);
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->isItFromDb());
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals('2', $valueObj->getValue());
        static::assertEquals([], $valueObj->getPayload());
    }
    
}
