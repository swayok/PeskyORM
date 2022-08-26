<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordValue;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class RecordValueTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::getPgsqlConnection();
        TestingApp::cleanInstancesOfDbTablesAndRecordsAndStructures();
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::cleanInstancesOfDbTablesAndRecordsAndStructures();
    }
    
    /**
     * @param $columnName
     * @return Column
     */
    protected function getClonedColumn($columnName)
    {
        return clone TestingAdminsTableStructure::getColumn($columnName);
    }
    
    public function testConstructAndClone()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $clone = clone $valueObj;
        
        static::assertNull($this->getObjectPropertyValue($valueObj, 'value'));
        static::assertNull($this->getObjectPropertyValue($valueObj, 'rawValue'));
        static::assertNull($this->getObjectPropertyValue($valueObj, 'oldValue'));
        static::assertFalse($this->getObjectPropertyValue($valueObj, 'isFromDb'));
        static::assertFalse($this->getObjectPropertyValue($valueObj, 'hasValue'));
        static::assertFalse($this->getObjectPropertyValue($valueObj, 'hasOldValue'));
        static::assertFalse($this->getObjectPropertyValue($valueObj, 'isValidated'));
        static::assertEquals([], $this->getObjectPropertyValue($valueObj, 'validationErrors'));
        static::assertEquals([], $this->getObjectPropertyValue($valueObj, 'customInfo'));
        static::assertInstanceOf(Column::class, $this->getObjectPropertyValue($valueObj, 'column'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(Column::class, $valueObj->getColumn());
        static::assertTrue(
            $this->getObjectPropertyValue($valueObj, 'column')
                ->isItPrimaryKey()
        );
        static::assertInstanceOf(TestingAdmin::class, $this->getObjectPropertyValue($valueObj, 'record'));
        static::assertInstanceOf(TestingAdmin::class, $valueObj->getRecord());
        
        static::assertNull($this->getObjectPropertyValue($clone, 'value'));
        static::assertNull($this->getObjectPropertyValue($clone, 'rawValue'));
        static::assertNull($this->getObjectPropertyValue($clone, 'oldValue'));
        static::assertFalse($this->getObjectPropertyValue($clone, 'isFromDb'));
        static::assertFalse($this->getObjectPropertyValue($clone, 'hasValue'));
        static::assertFalse($this->getObjectPropertyValue($clone, 'hasOldValue'));
        static::assertFalse($this->getObjectPropertyValue($clone, 'isValidated'));
        static::assertEquals([], $this->getObjectPropertyValue($clone, 'validationErrors'));
        static::assertEquals([], $this->getObjectPropertyValue($clone, 'customInfo'));
        static::assertInstanceOf(Column::class, $this->getObjectPropertyValue($clone, 'column'));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(Column::class, $clone->getColumn());
        static::assertTrue(
            $this->getObjectPropertyValue($clone, 'column')
                ->isItPrimaryKey()
        );
        static::assertInstanceOf(TestingAdmin::class, $this->getObjectPropertyValue($clone, 'record'));
        static::assertInstanceOf(TestingAdmin::class, $clone->getRecord());
        
        $valueObj->setCustomInfo(['test' => 'i']);
        static::assertEquals(['test' => 'i'], $valueObj->getCustomInfo());
        static::assertNotEquals($clone->getCustomInfo(), $valueObj->getCustomInfo());
        static::assertEquals($clone->getRecord(), $valueObj->getRecord());
    }
    
    public function testInvalidGetCustomInfo()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$key) must be of type ?string");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->getCustomInfo($this);
    }
    
    public function testInvalidRemoveCustomInfo()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #1 (\$key) must be of type ?string");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        $valueObj->removeCustomInfo($this);
    }
    
    public function testCustomInfo()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $valueObj->setCustomInfo(['test' => 'i']);
        static::assertEquals(['test' => 'i'], $valueObj->getCustomInfo());
        $valueObj->setCustomInfo(['test2' => '2']);
        static::assertEquals(['test2' => '2'], $valueObj->getCustomInfo());
        $valueObj->addCustomInfo('test', '1');
        static::assertEquals(['test2' => '2', 'test' => '1'], $valueObj->getCustomInfo());
        static::assertEquals('2', $valueObj->getCustomInfo('test2'));
        static::assertEquals('2', $valueObj->getCustomInfo('test2', 'default', true));
        static::assertEquals('default', $valueObj->getCustomInfo('test3', 'default', false));
        static::assertEquals(['test2' => '2', 'test' => '1'], $valueObj->getCustomInfo());
        static::assertEquals(null, $valueObj->getCustomInfo('test3'));
        static::assertEquals('default', $valueObj->getCustomInfo('test3', 'default', true));
        static::assertEquals('default', $valueObj->getCustomInfo('test3'));
        static::assertEquals(['test2' => '2', 'test' => '1', 'test3' => 'default'], $valueObj->getCustomInfo());
        $valueObj->removeCustomInfo('test2');
        static::assertEquals(['test' => '1', 'test3' => 'default'], $valueObj->getCustomInfo());
        $valueObj->removeCustomInfo();
        static::assertEquals([], $valueObj->getCustomInfo());
    }
    
    public function testDataForSavingExtender()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $valueObj->setDataForSavingExtender(['test' => 'i']);
        static::assertEquals(['test' => 'i'], $valueObj->pullDataForSavingExtender());
        static::assertNull($valueObj->pullDataForSavingExtender());
    }
    
    public function testValidationErrors()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertFalse($valueObj->isValidated());
        $valueObj->setValidationErrors(['fail!!!']);
        static::assertEquals(['fail!!!'], $valueObj->getValidationErrors());
        static::assertTrue($valueObj->isValidated());
        static::assertFalse($valueObj->isValid());
        $valueObj->setValidationErrors([]);
        static::assertEquals([], $valueObj->getValidationErrors());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isValidated());
    }
    
    public function testIsFromDb()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertFalse($valueObj->isItFromDb());
        $valueObj->setIsFromDb(true);
        static::assertTrue($valueObj->isItFromDb());
    }
    
    public function testInvalidDefaultValue()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->language is not valid");
        $col = $this->getClonedColumn('language')
            ->setDefaultValue('invalid');
        $valueObj = RecordValue::create($col, TestingAdmin::_());
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue2()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Fallback value of the default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->parent_id is not valid. Errors: Null value is not allowed");
        $valueObj = RecordValue::create(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues(),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue3()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->parent_id is not valid. Errors: Null value is not allowed");
        $valueObj = RecordValue::create(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues()
                ->setDefaultValue(null),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue4()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Default value received from validDefaultValueGetter Closure for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->parent_id is not valid. Errors: Null value is not allowed."
        );
        $valueObj = RecordValue::create(
            $this->getClonedColumn('parent_id')
                ->disallowsNullValues()
                ->setValidDefaultValueGetter(function () {
                    return null;
                }),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }
    
    public function testInvalidDefaultValue5()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->language is not valid. Errors: Value must be a string or a number."
        );
        $langCol = clone TestingAdminsTableStructure::getColumn('language');
        $langCol->setDefaultValue(new \stdClass());
        $valueObj = RecordValue::create($langCol, TestingAdmin::_());
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        $valueObj->getDefaultValue();
    }
    
    public function testDefaultValue()
    {
        $record = TestingAdmin::_();
        
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), $record);
        static::assertFalse($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertNull($valueObj->getDefaultValueOrNull());
        
        $langCol = TestingAdminsTableStructure::getColumn('language');
        $valueObj = RecordValue::create($langCol, $record);
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertEquals($langCol->getValidDefaultValue(), $valueObj->getDefaultValue());
        static::assertEquals($langCol->getValidDefaultValue(), $valueObj->getValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        
        $langCol->setDefaultValue(function () {
            return 'de';
        });
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        static::assertEquals('de', $valueObj->getDefaultValue());
        static::assertEquals('de', $valueObj->getValueOrDefault());
    
        $langCol->setDefaultValue(DbExpr::create('de'));
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        static::assertInstanceOf(DbExpr::class, $valueObj->getDefaultValue());
        static::assertEquals('(de)', $valueObj->getDefaultValue()->get());
        static::assertInstanceOf(DbExpr::class, $valueObj->getValueOrDefault());
        static::assertEquals('(de)', $valueObj->getDefaultValue()->get());
        
        $valueObj->setRawValue('ru', 'ru', false)
            ->setValidValue('ru', 'ru');
        static::assertEquals('ru', $valueObj->getValue());
        
        $idColValueObj = RecordValue::create(TestingAdminsTableStructure::getPkColumn(), $record);
        static::assertTrue($idColValueObj->isDefaultValueCanBeSet());
        $langColValueObj = RecordValue::create($langCol, $record);
        $record->reset()
            ->updateValue('id', 1, true);
        static::assertFalse($langColValueObj->isDefaultValueCanBeSet());
        $idColValueObj->setRawValue(2, 2, false)
            ->setValidValue(2, 2);
        static::assertFalse($idColValueObj->isDefaultValueCanBeSet());
    }
    
    public function testInvalidGetValue()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Value for PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin(#null)->parent_id is not set");
        $valueObj = RecordValue::create(
            TestingAdminsTableStructure::getColumn('parent_id'),
            TestingAdmin::newEmptyRecord()
        );
        $valueObj->getValue();
    }
    
    public function testInvalidGetOldValue()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Old value is not set");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->getOldValue();
    }
    
    public function testInvalidIsOldValueWasFromDb()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Old value is not set");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->isOldValueWasFromDb();
    }
    
    /**
     * @covers RecordValue::setOldValue()
     * @covers RecordValue::hasOldValue()
     * @covers RecordValue::getOldValue()
     */
    public function testSetOldValue()
    {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->setRawValue(2, 2, false)
            ->setValidValue(2, 2);
        static::assertFalse($valueObj->hasOldValue());
        $valueObj->setOldValue($valueObj);
        static::assertTrue($valueObj->hasOldValue());
        static::assertFalse($valueObj->isOldValueWasFromDb());
        static::assertEquals(2, $valueObj->getOldValue());
        $valueObj->setIsFromDb(true);
        $valueObj->setOldValue($valueObj);
        static::assertTrue($valueObj->hasOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertEquals(2, $valueObj->getOldValue());
        
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->setRawValue(2, 2, false)
            ->setValidValue(2, 2);
        static::assertFalse($valueObj->hasOldValue());
        $valueObj->setRawValue(3, 3, true);
        static::assertTrue($valueObj->hasOldValue());
        static::assertFalse($valueObj->isOldValueWasFromDb());
        static::assertEquals(2, $valueObj->getOldValue());
        $valueObj->setRawValue(4, 4, false);
        static::assertTrue($valueObj->hasOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertEquals(3, $valueObj->getOldValue());
    }
    
    public function testInvalidSetValidValue1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$rawValue argument for PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin(#null)->parent_id must be same as current raw value: NULL");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->setValidValue(1, 1);
    }
    
    public function testRawValueAndValidValue()
    {
        $record = TestingAdmin::_();
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), $record);
        
        $valueObj
            ->setCustomInfo(['1'])
            ->setDataForSavingExtender(['1'])
            ->setValidationErrors(['1']);
        static::assertEquals(['1'], $valueObj->getCustomInfo());
        static::assertEquals(['1'], $valueObj->getValidationErrors());
        static::assertFalse($valueObj->isValid());
        static::assertTrue($valueObj->isValidated());
        
        $valueObj->setRawValue('1', '1', true);
        static::assertTrue($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertTrue($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());
        static::assertEquals([], $valueObj->getCustomInfo());
        static::assertEquals([], $valueObj->getValidationErrors());
        static::assertEquals(['1'], $valueObj->pullDataForSavingExtender());
        static::assertFalse($valueObj->isValidated());
        
        $valueObj->setValidationErrors(['test']);
        static::assertTrue($valueObj->isValidated());
        static::assertFalse($valueObj->isValid());
        static::assertEquals(['test'], $valueObj->getValidationErrors());
        
        $valueObj->setValidValue(1, '1');
        static::assertTrue($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasOldValue());
        static::assertTrue($valueObj->isItFromDb());
        static::assertEquals(1, $valueObj->getValue());
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals([], $valueObj->getValidationErrors());
        static::assertNull($valueObj->pullDataForSavingExtender());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        
        $valueObj->setRawValue('2', '2', false);
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->isItFromDb());
        static::assertTrue($valueObj->hasOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertEquals(1, $valueObj->getOldValue());
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals('2', $valueObj->getValue());
        static::assertEquals([], $valueObj->getCustomInfo());
        static::assertEquals([], $valueObj->getValidationErrors());
        static::assertNull($valueObj->pullDataForSavingExtender());
        static::assertFalse($valueObj->isValidated());
    }
    
}
