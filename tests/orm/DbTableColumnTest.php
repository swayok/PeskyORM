<?php

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableRelation;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;

class DbTableColumnTest extends \PHPUnit_Framework_TestCase {

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
        static::assertTrue($obj->hasName());
        static::assertInstanceOf(\Closure::class, $obj->getValueGetter());
        static::assertInstanceOf(\Closure::class, $obj->getValueExistenceChecker());
        static::assertInstanceOf(\Closure::class, $obj->getValueSetter());
        static::assertInstanceOf(\Closure::class, $obj->getValueValidator());
        static::assertInstanceOf(\Closure::class, $obj->getValueIsAllowedValidator());
        static::assertInstanceOf(\Closure::class, $obj->getValueValidatorExtender());
        static::assertInstanceOf(\Closure::class, $obj->getValueNormalizer());
        static::assertInstanceOf(\Closure::class, $obj->getValuePreprocessor());
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
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet4() {
        DbTableColumn::create(DbTableColumn::TYPE_BLOB, 'two words');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet5() {
        DbTableColumn::create(DbTableColumn::TYPE_DATE, 'camelCase');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet6() {
        DbTableColumn::create(DbTableColumn::TYPE_EMAIL, 'UpperCase');
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Column name alteration is forbidden
     */
    public function testDoubleNameSetter() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM)->setName('test');
        $obj->setName('test');
    }

    public function testFileTypes() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_FILE);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_FILE);
        static::assertTrue($obj->isItAFile());
        static::assertFalse($obj->hasValueFormatter());
        $obj = DbTableColumn::create(DbTableColumn::TYPE_IMAGE);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_IMAGE);
        static::assertTrue($obj->isItAFile());
        static::assertTrue($obj->isItAnImage());
        static::assertFalse($obj->hasValueFormatter());
    }

    public function testEnumType() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_ENUM);
        static::assertTrue($obj->isEnum());
    }

    public function testFormattersDetectedByType() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_TIMESTAMP);
        static::assertEquals($obj->getType(), DbTableColumn::TYPE_TIMESTAMP);
        static::assertTrue($obj->hasValueFormatter());
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        static::assertTrue(count($obj->getValueFormats()) > 0);
        static::assertNotEmpty($this->getObjectPropertyValue($obj, 'valueFormatterFormats'));
    }

    public function testDefaultValues() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL);
        static::assertFalse($obj->hasDefaultValue());
        static::assertEquals(-1, $obj->getDefaultValue(-1));
        static::assertInstanceOf(Closure::class, $obj->getDefaultValue(function () { return -1; }));
        $obj->setDefaultValue(false);
        static::assertTrue($obj->hasDefaultValue());
        static::assertEquals(false, $obj->getDefaultValue(-1));
        $obj->setDefaultValue(null);
        static::assertTrue($obj->hasDefaultValue());
        static::assertEquals(null, $obj->getDefaultValue(-1));
        $obj->setDefaultValue(-1);
        static::assertTrue($obj->hasDefaultValue());
        static::assertEquals(-1, $obj->getDefaultValue(-2));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Allowed values closure must return a not-empty array
     */
    public function testInvalidGetAllowedValues() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Allowed values closure must return a not-empty array
     */
    public function testInvalidSetAllowedValues1() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Allowed values closure must return a not-empty array
     */
    public function testInvalidSetAllowedValues2() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return [];
            });
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues3() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(-1);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues4() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(false);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues5() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues([]);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues6() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->setAllowedValues(null);
        $obj->getAllowedValues();
    }

    public function testAllwedValues() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM);
        static::assertEquals([], $obj->getAllowedValues());
        $obj->setAllowedValues(['test']);
        static::assertEquals(['test'], $obj->getAllowedValues());
        $obj->setAllowedValues(function () {
            return ['test2'];
        });
        static::assertEquals(['test2'], $obj->getAllowedValues());
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidAddRelation1() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM);
        $obj->addRelation(new DbTableRelation('a', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id'));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Relation name is not provided
     */
    public function testInvalidAddRelation2() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM)->setName('id');
        $obj->addRelation(new DbTableRelation('a', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' is not connected to column 'id'
     */
    public function testInvalidAddRelation3() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM)->setName('id');
        $obj->addRelation(
            (new DbTableRelation('a', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test')
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' already defined for column 'id'
     */
    public function testInvalidAddRelation4() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM)->setName('id');
        $rel = (new DbTableRelation('id', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test');
        $obj->addRelation($rel);
        $obj->addRelation($rel);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' does not exist
     */
    public function testInvalidGetRelation() {
        DbTableColumn::create(DbTableColumn::TYPE_ENUM)->getRelation('Test');
    }

    public function testRelations() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_ENUM)->setName('id');
        static::assertFalse($obj->hasRelation('Test'));
        static::assertFalse($obj->isItAForeignKey());
        $rel = (new DbTableRelation('id', DbTableRelation::HAS_ONE, TestingAdminsTable::class, 'id'))->setName('Test');
        $obj->addRelation($rel);
        static::assertTrue($obj->hasRelation('Test'));
        static::assertFalse($obj->isItAForeignKey());
        $rel2 = (new DbTableRelation('id', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test2');
        $obj->addRelation($rel2);
        static::assertTrue($obj->hasRelation('Test2'));
        static::assertTrue($obj->isItAForeignKey());
    }

    // todo: test default closures

}
