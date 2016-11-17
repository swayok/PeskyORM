<?php

use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableRelation;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;

class DbTableColumnTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public static function tearDownAfterClass() {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
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
        static::assertTrue($obj->isItExistsInDb());
        static::assertTrue($obj->isValueCanBeNull());
        static::assertFalse($obj->isItPrimaryKey());
        static::assertTrue($obj->isValueCanBeSetOrChanged());
        static::assertFalse($obj->isValueLowercasingRequired());
        static::assertFalse($obj->isValueMustBeUnique());
        static::assertFalse($obj->isValuePrivate());
        static::assertFalse($obj->isValueTrimmingRequired());
        static::assertFalse($obj->isAutoUpdatingValue());
        static::assertFalse($obj->isEmptyStringMustBeConvertedToNull());
        static::assertFalse($obj->isEnum());
        static::assertFalse($obj->isItAFile());
        static::assertFalse($obj->isItAForeignKey());
        static::assertFalse($obj->isItAnImage());
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

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Default value for column 'name' is not set
     */
    public function testInvalidDefaultValueGet1() {
        DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'name')->getDefaultValueAsIs();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet2() {
        DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'name')
            ->setDefaultValue(-1)
            ->getValidDefaultValue();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Fallback value of the default value for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet3() {
        DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'name')
            ->getValidDefaultValue(-1);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value received from validDefaultValueGetter closure for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet4() {
        DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'name')
            ->setValidDefaultValueGetter(function ($fallback) {
                return -1;
            })
            ->getValidDefaultValue(true);
    }

    public function testDefaultValues() {
        $obj = DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'name');
        static::assertFalse($obj->hasDefaultValue());
        static::assertFalse($obj->getValidDefaultValue(false));
        static::assertTrue($obj->getValidDefaultValue(function () { return true; }));

        $obj->setDefaultValue(function () {
            return false;
        });
        static::assertTrue($obj->hasDefaultValue());
        static::assertInstanceOf(Closure::class, $obj->getDefaultValueAsIs());
        static::assertFalse($obj->getValidDefaultValue(true));

        $obj->setDefaultValue(false);
        static::assertTrue($obj->hasDefaultValue());
        static::assertFalse($obj->getDefaultValueAsIs());
        static::assertFalse($obj->getValidDefaultValue(true));

        $obj->setDefaultValue(null);
        static::assertTrue($obj->hasDefaultValue());
        static::assertNull($obj->getDefaultValueAsIs());
        static::assertNull($obj->getValidDefaultValue(true));

        // default value getter
        $obj->setValidDefaultValueGetter(function ($fallbackValue, DbTableColumn $column) {
            return $fallbackValue;
        });
        $obj->setDefaultValue(true);
        static::assertTrue($obj->hasDefaultValue());
        static::assertTrue(true, $obj->getDefaultValueAsIs());
        static::assertFalse($obj->getValidDefaultValue(false));
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

}
