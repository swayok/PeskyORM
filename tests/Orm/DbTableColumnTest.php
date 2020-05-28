<?php

namespace Tests\Orm;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use Swayok\Utils\NormalizeValue;

class DbTableColumnTest extends \PHPUnit\Framework\TestCase {

    public static function setUpBeforeClass(): void {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
        \PeskyORMTest\TestingApp::getPgsqlConnection();
    }

    public static function tearDownAfterClass(): void {
        \PeskyORMTest\TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getObjectPropertyValue($object, $propertyName) {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor1() {
        Column::create(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor2() {
        Column::create([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor3() {
        Column::create($this);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor4() {
        Column::create(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $type argument must be a string, integer or float
     */
    public function testInvalidConstructor5() {
        Column::create(false);
    }

    public function testConstructor() {
        $obj = Column::create(Column::TYPE_BOOL);
        static::assertInstanceOf(Column::class, $obj);
        static::assertEquals($obj->getType(), Column::TYPE_BOOL);
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
        Column::create(Column::TYPE_STRING, null)->getName();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidName2() {
        Column::create(Column::TYPE_STRING, false)->getName();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage DB column name is not provided
     */
    public function testInvalidName3() {
        Column::create(Column::TYPE_STRING, [])->getName();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet1() {
        Column::create(Column::TYPE_INT, ['arr']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet2() {
        Column::create(Column::TYPE_FLOAT, $this);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $name argument must be a string
     */
    public function testInvalidNameSet3() {
        Column::create(Column::TYPE_IPV4_ADDRESS, true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet4() {
        Column::create(Column::TYPE_BLOB, 'two words');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet5() {
        Column::create(Column::TYPE_DATE, 'camelCase');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp %\$name argument contains invalid value: .*?\. Pattern: .*?\. Example: snake_case1%
     */
    public function testInvalidNameSet6() {
        Column::create(Column::TYPE_EMAIL, 'UpperCase');
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Column name alteration is forbidden
     */
    public function testDoubleNameSetter() {
        $obj = Column::create(Column::TYPE_ENUM)->setName('test');
        $obj->setName('test');
    }

    public function testFileTypes() {
        $obj = Column::create(Column::TYPE_FILE);
        static::assertEquals($obj->getType(), Column::TYPE_FILE);
        static::assertTrue($obj->isItAFile());
        $obj = Column::create(Column::TYPE_IMAGE);
        static::assertEquals($obj->getType(), Column::TYPE_IMAGE);
        static::assertTrue($obj->isItAFile());
        static::assertTrue($obj->isItAnImage());
    }

    public function testEnumType() {
        $obj = Column::create(Column::TYPE_ENUM);
        static::assertEquals($obj->getType(), Column::TYPE_ENUM);
        static::assertTrue($obj->isEnum());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Value format 'time' is not supported for column 'login'. Supported formats: none
     */
    public function testInvalidValueFormat() {
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin::newEmptyRecord();
        /** @var \PeskyORM\ORM\RecordValue $value */
        $value = $this->getObjectPropertyValue($rec, 'values')['login'];
        static::assertEquals('11:00:00', call_user_func($value->getColumn()->getValueFormatter(), $value, 'time'));
    }

    public function testFormattersDetectedByType() {
        $obj = Column::create(Column::TYPE_TIMESTAMP);
        static::assertEquals($obj->getType(), Column::TYPE_TIMESTAMP);
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin::fromArray(['created_at' => '2016-11-21 11:00:00']);
        /** @var \PeskyORM\ORM\RecordValue $value */
        $value = $this->getObjectPropertyValue($rec, 'values')['created_at'];
        static::assertEquals('11:00:00', call_user_func($value->getColumn()->getValueFormatter(), $value, 'time'));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Default value for column 'name' is not set
     */
    public function testInvalidDefaultValueGet1() {
        Column::create(Column::TYPE_BOOL, 'name')->getDefaultValueAsIs();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet2() {
        Column::create(Column::TYPE_BOOL, 'name')
            ->setDefaultValue(-1)
            ->getValidDefaultValue();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Fallback value of the default value for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet3() {
        Column::create(Column::TYPE_BOOL, 'name')
            ->getValidDefaultValue(-1);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Default value received from validDefaultValueGetter closure for column 'name' is not valid. Errors: Value must be of a boolean data type
     */
    public function testInvalidDefaultValueGet4() {
        Column::create(Column::TYPE_BOOL, 'name')
            ->setValidDefaultValueGetter(function ($fallback) {
                return -1;
            })
            ->getValidDefaultValue(true);
    }

    public function testDefaultValues() {
        $obj = Column::create(Column::TYPE_BOOL, 'name');
        static::assertFalse($obj->hasDefaultValue());
        static::assertFalse($obj->getValidDefaultValue(false));
        static::assertTrue($obj->getValidDefaultValue(function () { return true; }));

        $obj->setDefaultValue(function () {
            return false;
        });
        static::assertTrue($obj->hasDefaultValue());
        static::assertInstanceOf(\Closure::class, $obj->getDefaultValueAsIs());
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
        $obj->setValidDefaultValueGetter(function ($fallbackValue, Column $column) {
            return $fallbackValue;
        });
        $obj->setDefaultValue(true);
        static::assertTrue($obj->hasDefaultValue());
        static::assertTrue(true, $obj->getDefaultValueAsIs());
        static::assertFalse($obj->getValidDefaultValue(false));
        
        // default value that needs normalization
        $nowTs = time();
        $obj = Column::create(Column::TYPE_TIMESTAMP, 'name')
            ->setDefaultValue($nowTs);
        static::assertTrue($obj->hasDefaultValue());
        $defaultValue = $obj->getValidDefaultValue();
        static::assertNotEquals($nowTs, $defaultValue);
        static::assertEquals(date(NormalizeValue::DATETIME_FORMAT, $nowTs), $defaultValue);
    
        $obj->setDefaultValue(function () use ($nowTs) {
            return $nowTs;
        });
        static::assertEquals(date(NormalizeValue::DATETIME_FORMAT, $nowTs), $defaultValue);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Allowed values closure must return a not-empty array
     */
    public function testInvalidGetAllowedValues() {
        $obj = Column::create(Column::TYPE_BOOL)
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
        $obj = Column::create(Column::TYPE_BOOL)
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
        $obj = Column::create(Column::TYPE_BOOL)
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
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(-1);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues4() {
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(false);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues5() {
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues([]);
        $obj->getAllowedValues();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $allowedValues argument cannot be empty
     */
    public function testInvalidSetAllowedValues6() {
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(null);
        $obj->getAllowedValues();
    }

    public function testAllwedValues() {
        $obj = Column::create(Column::TYPE_ENUM);
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
        $obj = Column::create(Column::TYPE_ENUM);
        $obj->addRelation(new Relation('a', Relation::BELONGS_TO, TestingAdminsTable::class, 'id'));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Relation name is not provided
     */
    public function testInvalidAddRelation2() {
        $obj = Column::create(Column::TYPE_ENUM)->setName('id');
        $obj->addRelation(new Relation('a', Relation::BELONGS_TO, TestingAdminsTable::class, 'id'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' is not connected to column 'id'
     */
    public function testInvalidAddRelation3() {
        $obj = Column::create(Column::TYPE_ENUM)->setName('id');
        $obj->addRelation(
            (new Relation('a', Relation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test')
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' already defined for column 'id'
     */
    public function testInvalidAddRelation4() {
        $obj = Column::create(Column::TYPE_ENUM)->setName('id');
        $rel = (new Relation('id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test');
        $obj->addRelation($rel);
        $obj->addRelation($rel);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Relation 'Test' does not exist
     */
    public function testInvalidGetRelation() {
        Column::create(Column::TYPE_ENUM)->getRelation('Test');
    }

    public function testRelations() {
        $obj = Column::create(Column::TYPE_ENUM)->setName('id');
        static::assertFalse($obj->hasRelation('Test'));
        static::assertFalse($obj->isItAForeignKey());
        $rel = (new Relation('id', Relation::HAS_ONE, TestingAdminsTable::class, 'id'))->setName('Test');
        $obj->addRelation($rel);
        static::assertTrue($obj->hasRelation('Test'));
        static::assertFalse($obj->isItAForeignKey());
        $rel2 = (new Relation('id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id'))->setName('Test2');
        $obj->addRelation($rel2);
        static::assertTrue($obj->hasRelation('Test2'));
        static::assertTrue($obj->isItAForeignKey());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $class argument must be a string and contain a full name of a calss that implements ColumnClosuresInterface
     */
    public function testInvalidSetClosuresClass1() {
        Column::create(Column::TYPE_STRING)->setClosuresClass(\PeskyORMTest\TestingAdmins\TestingAdmin::class);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $class argument must be a string and contain a full name of a calss that implements ColumnClosuresInterface
     */
    public function testInvalidSetClosuresClass2() {
        Column::create(Column::TYPE_STRING)->setClosuresClass(new \PeskyORM\ORM\DefaultColumnClosures);
    }

    public function testSetClosuresClass() {
        $obj = Column::create(Column::TYPE_STRING)->setClosuresClass(\PeskyORM\ORM\DefaultColumnClosures::class);
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
        static::assertEquals([], call_user_func($obj->getValueValidatorExtender(), '1', false, $obj));
    }

}
