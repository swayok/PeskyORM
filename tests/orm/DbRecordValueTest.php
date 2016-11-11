<?php


use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\DbRecordValue;
use PeskyORM\ORM\DbTableColumn;
use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class DbRecordValueTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        \PeskyORMTest\TestingApp::init();
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public static function tearDownAfterClass() {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    /**
     * @param $columnName
     * @return DbTableColumn
     */
    protected function getClonedColumn($columnName) {
        return clone TestingAdminsTableStructure::getColumn($columnName);
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

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $key argument for custom info must be a string or number but object received (column: 'id')
     */
    public function testInvalidGetCustomInfo() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $valueObj->getCustomInfo($this);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $key argument for custom info must be a string or number but object received (column: 'id')
     */
    public function testInvalidRemoveCustomInfo() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        $valueObj->removeCustomInfo($this);
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

    public function testIsFromDb() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), TestingAdmin::_());
        static::assertFalse($valueObj->isItFromDb());
        $valueObj->setIsFromDb(true);
        static::assertTrue($valueObj->isItFromDb());
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value for column 'language' is not valid
     */
    public function testInvalidDefaultValue() {
        $col = $this->getClonedColumn('language')->setDefaultValue('invalid');
        $valueObj = DbRecordValue::create($col, TestingAdmin::_());
        $valueObj->getDefaultValue();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Fallback value of the default value for column 'parent_id' is not valid. Errors: Null value is not allowed
     */
    public function testInvalidDefaultValue2() {
        $valueObj = DbRecordValue::create($this->getClonedColumn('parent_id')->valueIsNotNullable(), TestingAdmin::_());
        $valueObj->getDefaultValue();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value for column 'parent_id' is not valid. Errors: Null value is not allowed
     */
    public function testInvalidDefaultValue3() {
        $valueObj = DbRecordValue::create(
            $this->getClonedColumn('parent_id')->valueIsNotNullable()->setDefaultValue(null),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value received from validDefaultValueGetter closure for column 'parent_id' is not valid. Errors: Null value is not allowed
     */
    public function testInvalidDefaultValue4() {
        $valueObj = DbRecordValue::create(
            $this->getClonedColumn('parent_id')
                ->valueIsNotNullable()
                ->setValidDefaultValueGetter(function () {
                    return null;
                }),
            TestingAdmin::_()
        );
        $valueObj->getDefaultValue();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $value argument must be a string, integer, float or array to be able to validate if it is within allowed values
     */
    public function testInvalidDefaultValue5() {
        $langCol = clone TestingAdminsTableStructure::getColumn('language');
        $langCol->setDefaultValue(DbExpr::create('test2'));
        $valueObj = DbRecordValue::create($langCol, TestingAdmin::_());
        static::assertTrue($valueObj->hasDefaultValue());
        static::assertTrue($valueObj->isDefaultValueCanBeSet());
        static::assertFalse($valueObj->hasValue());
        static::assertTrue($valueObj->hasValueOrDefault());
        static::assertFalse($valueObj->hasValue());
        $valueObj->getDefaultValue();
    }

    public function testDefaultValue() {
        $record = TestingAdmin::_();

        $valueObj = DbRecordValue::create( TestingAdminsTableStructure::getColumn('parent_id'), $record);
        static::assertFalse($valueObj->hasDefaultValue());
        static::assertFalse($valueObj->hasValue());
        static::assertNull($valueObj->getDefaultValueOrNull());

        $langCol = TestingAdminsTableStructure::getColumn('language');
        $valueObj = DbRecordValue::create($langCol, $record);
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

        $valueObj->setRawValue('ru', 'ru', false)->setValidValue('ru', 'ru');
        static::assertEquals('ru', $valueObj->getValue());

        $idColValueObj = DbRecordValue::create(TestingAdminsTableStructure::getPkColumn(), $record);
        static::assertTrue($idColValueObj->isDefaultValueCanBeSet());
        $langColValueObj = DbRecordValue::create($langCol, $record);
        $record->reset()->setValue('id', 1, true);
        static::assertFalse($langColValueObj->isDefaultValueCanBeSet());
        $idColValueObj->setRawValue(2, 2, false)->setValidValue(2, 2);
        static::assertFalse($idColValueObj->isDefaultValueCanBeSet());
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Value for column 'parent_id' is not set
     */
    public function testInvalidGetValue() {
        $valueObj = DbRecordValue::create(
            TestingAdminsTableStructure::getColumn('parent_id'),
            TestingAdmin::newEmptyRecord()
        );
        $valueObj->getValue();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Old value is not set
     */
    public function testInvalidGetOldValue() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->getOldValue();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Old value is not set
     */
    public function testInvalidIsOldValueWasFromDb() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->isOldValueWasFromDb();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $rawValue argument for column 'parent_id' must be same as current raw value: NULL
     */
    public function testInvalidSetValidValue1() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        $valueObj->setValidValue(1, 1);
    }

    public function testRawValueAndValidValue() {
        $record = TestingAdmin::_();
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), $record);

        $valueObj
            ->setCustomInfo(['1'])
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
        static::assertFalse($valueObj->isValidated());

    }

}
