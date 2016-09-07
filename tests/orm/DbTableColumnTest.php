<?php

use PeskyORM\ORM\DbTableColumn;

class DbTableColumnTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        //\PeskyORMTest\TestingApp::init();
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getObjectPropertyValue($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @param object $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    private function callObjectMethod($object, $methodName, array $args = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor1() {
        DbTableColumn::create(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor2() {
        DbTableColumn::create([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor3() {
        DbTableColumn::create($this);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor4() {
        DbTableColumn::create(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor5() {
        DbTableColumn::create(false);
    }

    public function testConstructor() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL);
        static::assertInstanceOf(DbTableColumn::class, $obj);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_BOOL);
        static::assertFalse($obj->hasName());
        static::assertEquals('test', $obj->setName('test')->getName());
        static::assertTrue($obj->setName('test')->hasName());
        static::assertInstanceOf(\Closure::class, $obj->getValueGetter());
        static::assertInstanceOf(\Closure::class, $obj->getValueExistenceChecker());
        static::assertInstanceOf(\Closure::class, $obj->getValueSetter());
        static::assertInstanceOf(\Closure::class, $obj->getValueValidator());
        static::assertInstanceOf(\Closure::class, $this->callObjectMethod($obj, 'getValueValidatorExtender'));
        static::assertInstanceOf(\Closure::class, $this->callObjectMethod($obj, 'getValueNormalizer'));
        static::assertInstanceOf(\Closure::class, $this->callObjectMethod($obj, 'getValuePreprocessor'));
        static::assertInstanceOf(\Closure::class, $obj->getValueSavingExtender());
        static::assertInstanceOf(\Closure::class, $obj->getValueDeleteExtender());
        static::assertFalse($obj->hasValueFormatter());
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidName1() {
        DbTableColumn::create(DbTableColumn::TYPE_STRING, null)->getName();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidName2() {
        DbTableColumn::create(DbTableColumn::TYPE_STRING, false)->getName();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidName3() {
        DbTableColumn::create(DbTableColumn::TYPE_STRING, [])->getName();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet1() {
        DbTableColumn::create(DbTableColumn::TYPE_INT, ['arr']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet2() {
        DbTableColumn::create(DbTableColumn::TYPE_FLOAT, $this);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet3() {
        DbTableColumn::create(DbTableColumn::TYPE_IPV4_ADDRESS, true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp $name argument contains invalid value: .*?. Pattern: .*?. Example: snake_case1
     */
    public function testInvalidNameSet4() {
        DbTableColumn::create(DbTableColumn::TYPE_BLOB, 'two words');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp $name argument contains invalid value: .*?. Pattern: .*?. Example: snake_case1
     */
    public function testInvalidNameSet5() {
        DbTableColumn::create(DbTableColumn::TYPE_DATE, 'camelCase');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp $name argument contains invalid value: .*?. Pattern: .*?. Example: snake_case1
     */
    public function testInvalidNameSet6() {
        DbTableColumn::create(DbTableColumn::TYPE_EMAIL, 'UpperCase');
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Column name alteration is forbidden
     */
    public function testDoubleNameSetter() {
        DbTableColumn::create(false)->setName('test');
        DbTableColumn::create(false)->setName('test');
    }

    public function testFileTypes() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_FILE);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_FILE);
        static::assertTrue($obj->isItAFile());
        $obj = DbTableColumn::create(DbTableColumn::TYPE_IMAGE);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_IMAGE);
        static::assertTrue($obj->isItAFile());
        static::assertTrue($obj->isItAnImage());
    }

    public function testFormattersDetectedByType() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_TIMESTAMP);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_TIMESTAMP);
        static::assertTrue($obj->hasValueFormatter());
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        static::assertNotEmpty($this->getObjectPropertyValue($obj, 'valueFormatterFormats'));
    }

}
