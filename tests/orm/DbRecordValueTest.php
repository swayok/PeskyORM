<?php


use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\DbRecordValue;
use PeskyORM\ORM\DbTableColumn;
use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class DbRecordValueTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        \PeskyORMTest\TestingApp::init();
    }

    /**
     * @param DbRecordValue $object
     * @param string $propertyName
     * @return mixed
     */
    private function getObjectPropertyValue($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    public function testConstructAndClone() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
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
        static::assertInstanceOf(DbTableColumn::class, $this->getObjectPropertyValue($valueObj, 'column'));
        static::assertInstanceOf(DbTableColumn::class, $valueObj->getColumn());
        static::assertTrue($this->getObjectPropertyValue($valueObj, 'column')->isItPrimaryKey());
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
        static::assertInstanceOf(DbTableColumn::class, $this->getObjectPropertyValue($clone, 'column'));
        static::assertInstanceOf(DbTableColumn::class, $clone->getColumn());
        static::assertTrue($this->getObjectPropertyValue($clone, 'column')->isItPrimaryKey());
        static::assertInstanceOf(TestingAdmin::class, $this->getObjectPropertyValue($clone, 'record'));
        static::assertInstanceOf(TestingAdmin::class, $clone->getRecord());

        $valueObj->setCustomInfo(['test' => 'i']);
        static::assertEquals(['test' => 'i'], $valueObj->getCustomInfo());
        static::assertNotEquals($clone->getCustomInfo(), $valueObj->getCustomInfo());
        static::assertEquals($clone->getRecord(), $valueObj->getRecord());
    }

    public function testCustomInfo() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $valueObj->setCustomInfo(['test'=> 'i']);
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

    public function testValidationErrors() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertTrue($valueObj->isValid());
        $valueObj->setValidationErrors(['fail!!!']);
        static::assertEquals(['fail!!!'], $valueObj->getValidationErrors());
        static::assertFalse($valueObj->isValid());
        $valueObj->setValidationErrors([]);
        static::assertEquals([], $valueObj->getValidationErrors());
        static::assertTrue($valueObj->isValid());
    }

    public function testIsFromDb() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertFalse($valueObj->isItFromDb());
        $valueObj->setIsFromDb(true);
        static::assertTrue($valueObj->isItFromDb());
    }

    public function testDefaultValue() {
        $record = TestingAdmin::_();
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), $record);
        static::assertFalse($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());

        $langCol = TestingAdminsTableStructure::getColumn('language');
        $valueObj = DbRecordValue::create($langCol, $record);
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertTrue($valueObj->hasValue());
        static::assertEquals($langCol->getDefaultValue(), $valueObj->getDefaultValue());
        static::assertEquals($langCol->getDefaultValue(), $valueObj->getValue());

        $langCol->setDefaultValue(function () {
            return 'test';
        });
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertTrue($valueObj->hasValue());
        static::assertEquals('test', $valueObj->getDefaultValue());
        static::assertEquals('test', $valueObj->getValue());

        $langCol->setDefaultValue(DbExpr::create('test2'));
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertTrue($valueObj->hasValue());
        static::assertInstanceOf(DbExpr::class, $valueObj->getDefaultValue());
        static::assertInstanceOf(DbExpr::class, $valueObj->getValue());

        $record->setColumnValue($langCol->getName(), 'ru', false);
        static::assertEquals('ru', $valueObj->getValue());

        $idColValueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), $record);
        $langColValueObj = DbRecordValue::create($langCol, $record);
        $record->reset()->setColumnValue('id', 1, true);
        static::assertTrue($idColValueObj->isDefaultValueCanBeSet());
        static::assertFalse($langColValueObj->isDefaultValueCanBeSet());

        // todo: finish this (note: $record->setColumnValue() does not affect $idColValueObj)
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default column value is not valid
     */
    public function testInvalidDefaultValue() {
        $col = TestingAdminsTableStructure::getColumn('language')->setDefaultValue('invalid');
        $valueObj = DbRecordValue::create($col, TestingAdmin::_());
        $valueObj->getDefaultValue();
    }

    // todo: test old/new/raw value set/get
}
