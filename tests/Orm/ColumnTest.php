<?php

declare(strict_types=1);

namespace Tests\Orm;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use Swayok\Utils\NormalizeValue;
use Tests\PeskyORMTest\BaseTestCase;
use Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use Tests\PeskyORMTest\TestingApp;
use Tests\PeskyORMTest\TestingValueToObjectConverter;

class ColumnTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::cleanInstancesOfDbTablesAndRecordsAndStructures();
        TestingApp::getPgsqlConnection();
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::cleanInstancesOfDbTablesAndRecordsAndStructures();
    }
    
    public function testInvalidConstructor1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(null);
    }
    
    public function testInvalidConstructor2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create([]);
    }
    
    public function testInvalidConstructor3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create($this);
    }
    
    public function testInvalidConstructor4()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(true);
    }
    
    public function testInvalidConstructor5()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(false);
    }
    
    public function testConstructor()
    {
        $obj = Column::create(Column::TYPE_BOOL);
        static::assertInstanceOf(Column::class, $obj);
        static::assertEquals(Column::TYPE_BOOL, $obj->getType());
        static::assertFalse($obj->hasName());
        static::assertEquals('id', $obj->setName('id')->getName());
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
        static::assertFalse($obj->isItPrimaryKey());
        static::assertTrue($obj->isValueCanBeSetOrChanged());
        static::assertFalse($obj->isValueLowercasingRequired());
        static::assertFalse($obj->isValueMustBeUnique());
        static::assertFalse($obj->isValuePrivate());
        static::assertFalse($obj->isValueTrimmingRequired());
        static::assertFalse($obj->isAutoUpdatingValue());
        static::assertFalse($obj->isEnum());
        static::assertFalse($obj->isItAFile());
        static::assertFalse($obj->isItAnImage());
        static::assertTrue($obj->isValueCanBeNull());
        static::assertTrue($obj->isEmptyStringMustBeConvertedToNull());
        $obj->disallowsNullValues();
        static::assertFalse($obj->isValueCanBeNull());
        static::assertFalse($obj->isEmptyStringMustBeConvertedToNull());
    
        $obj->setTableStructure(TestingAdminsTableStructure::getInstance());
        static::assertFalse($obj->isItAForeignKey());
        $obj->primaryKey();
        static::assertTrue($obj->isItPrimaryKey());
    
        $obj = Column::create(Column::TYPE_BOOL, 'parent_id');
        $obj->setTableStructure(TestingAdminsTableStructure::getInstance());
        static::assertTrue($obj->isItAForeignKey());
    }
    
    public function testTableStructureNotSet1()
    {
        $obj = Column::create(Column::TYPE_BOOL);
        static::assertInstanceOf(Column::class, $obj);
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::getTableStructure(): Return value must be of type PeskyORM\ORM\TableStructure, null returned');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $obj->getTableStructure();
    }
    
    public function testInvalidName1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("DB column name is not provided");
        Column::create(Column::TYPE_STRING, null)
            ->getName();
    }
    
    public function testInvalidName2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(Column::TYPE_STRING, false)
            ->getName();
    }
    
    public function testInvalidName3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        Column::create(Column::TYPE_STRING, [])
            ->getName();
    }
    
    public function testInvalidNameSet1()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        Column::create(Column::TYPE_INT, ['arr']);
    }
    
    public function testInvalidNameSet2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        Column::create(Column::TYPE_FLOAT, $this);
    }
    
    public function testInvalidNameSet3()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(Column::TYPE_IPV4_ADDRESS, true);
    }
    
    public function testInvalidNameSet4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$name argument contains invalid value: 'two words'. Pattern: %^[a-z][a-z0-9_]*$%. Example: snake_case1");
        Column::create(Column::TYPE_BLOB, 'two words');
    }
    
    public function testInvalidNameSet5()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$name argument contains invalid value: 'camelCase'. Pattern: %^[a-z][a-z0-9_]*$%. Example: snake_case1");
        Column::create(Column::TYPE_DATE, 'camelCase');
    }
    
    public function testInvalidNameSet6()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$name argument contains invalid value: 'UpperCase'. Pattern: %^[a-z][a-z0-9_]*$%. Example: snake_case1");
        Column::create(Column::TYPE_EMAIL, 'UpperCase');
    }
    
    public function testDoubleNameSetter()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Column name alteration is forbidden");
        $obj = Column::create(Column::TYPE_ENUM)
            ->setName('test');
        $obj->setName('test');
    }
    
    public function testFileTypes()
    {
        $obj = Column::create(Column::TYPE_FILE);
        static::assertEquals(Column::TYPE_FILE, $obj->getType());
        static::assertTrue($obj->isItAFile());
        $obj = Column::create(Column::TYPE_IMAGE);
        static::assertEquals(Column::TYPE_IMAGE, $obj->getType());
        static::assertTrue($obj->isItAFile());
        static::assertTrue($obj->isItAnImage());
    }
    
    public function testEnumType()
    {
        $obj = Column::create(Column::TYPE_ENUM);
        static::assertEquals(Column::TYPE_ENUM, $obj->getType());
        static::assertTrue($obj->isEnum());
    }
    
    public function testInvalidValueFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'time' is not supported for column 'login'. Supported formats: none");
        $rec = TestingAdmin::newEmptyRecord();
        $rec->updateValue('login', 'test', false);
        static::assertEquals('test', $rec->getValue('login'));
        /** @var \PeskyORM\ORM\RecordValue $value */
        $value = $this->getObjectPropertyValue($rec, 'values')['login'];
        static::assertEquals(
            '11:00:00',
            call_user_func(
                $value->getColumn()->getValueFormatter(),
                $value,
                'time'
            )
        );
    }
    
    public function testFormattersDetectedByType()
    {
        $obj = Column::create(Column::TYPE_TIMESTAMP);
        static::assertEquals(Column::TYPE_TIMESTAMP, $obj->getType());
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        $rec = TestingAdmin::fromArray(['created_at' => '2016-11-21 11:00:00']);
        /** @var \PeskyORM\ORM\RecordValue $value */
        $value = $this->getObjectPropertyValue($rec, 'values')['created_at'];
        static::assertEquals(
            '11:00:00',
            call_user_func(
                $value->getColumn()
                    ->getValueFormatter(),
                $value,
                'time'
            )
        );
    }
    
    public function testInvalidDefaultValueGet1()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Default value for column 'name' is not set");
        Column::create(Column::TYPE_BOOL, 'name')
            ->getDefaultValueAsIs();
    }
    
    public function testInvalidDefaultValueGet2()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Default value for column Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type.");
        Column::create(Column::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->setDefaultValue(-1)
            ->getValidDefaultValue();
    }
    
    public function testInvalidDefaultValueGet3()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Fallback value of the default value for column Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type."
        );
        Column::create(Column::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->getValidDefaultValue(-1);
    }
    
    public function testInvalidDefaultValueGet4()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Default value received from validDefaultValueGetter Closure for column Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type."
        );
        Column::create(Column::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->setValidDefaultValueGetter(function ($fallback) {
                return -1;
            })
            ->getValidDefaultValue(true);
    }
    
    public function testDefaultValues()
    {
        $obj = Column::create(Column::TYPE_BOOL, 'name');
        static::assertFalse($obj->hasDefaultValue());
        static::assertFalse($obj->getValidDefaultValue(false));
        static::assertTrue(
            $obj->getValidDefaultValue(function () {
                return true;
            })
        );
        
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
        static::assertTrue($obj->getDefaultValueAsIs());
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
    
    public function testInvalidGetAllowedValues()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues1()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues2()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(function () {
                return [];
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$allowedValues argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(-1);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$allowedValues argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(false);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues5()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$allowedValues argument cannot be empty");
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues([]);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues6()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$allowedValues argument cannot be empty");
        /** @noinspection PhpParamsInspection */
        $obj = Column::create(Column::TYPE_BOOL)
            ->setAllowedValues(null);
        $obj->getAllowedValues();
    }
    
    public function testAllwedValues()
    {
        $obj = Column::create(Column::TYPE_ENUM);
        static::assertEquals([], $obj->getAllowedValues());
        $obj->setAllowedValues(['test']);
        static::assertEquals(['test'], $obj->getAllowedValues());
        $obj->setAllowedValues(function () {
            return ['test2'];
        });
        static::assertEquals(['test2'], $obj->getAllowedValues());
    }
    
    public function testInvalidGetRelation()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Column 'id' is not linked with 'Test' relation");
        Column::create(Column::TYPE_ENUM)
            ->setName('id')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->getRelation('Test');
    }
    
    public function testInvalidSetClosuresClass1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$className argument must be a string and contain a full name of a class that implements ColumnClosuresInterface");
        Column::create(Column::TYPE_STRING)
            ->setClosuresClass(TestingAdmin::class);
    }
    
    public function testInvalidSetClosuresClass2()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Column::setClosuresClass(): Argument #1 ($className) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        Column::create(Column::TYPE_STRING)
            ->setClosuresClass(new DefaultColumnClosures());
    }
    
    public function testSetClosuresClass()
    {
        $obj = Column::create(Column::TYPE_STRING)
            ->setClosuresClass(DefaultColumnClosures::class);
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
        static::assertEquals([], call_user_func($obj->getValueValidatorExtender(), '1', false, false, $obj));
        static::assertEquals([], call_user_func($obj->getValueValidatorExtender(), '1', false, true, $obj));
    }
    
    public function testInvalidSetClassNameForValueToObjectFormatter()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$className argument must be a string and contain a full name of a class that implements PeskyORM\ORM\ValueToObjectConverterInterface');
        Column::create(Column::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(DefaultColumnClosures::class);
    }
    
    public function testSetClassNameForValueToObjectFormatter()
    {
        $obj = Column::create(Column::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(TestingValueToObjectConverter::class);
        
        static::assertEquals(TestingValueToObjectConverter::class, $obj->getObjectClassNameForValueToObjectFormatter());
    
        $obj = Column::create(Column::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(null);
        static::assertNull($obj->getObjectClassNameForValueToObjectFormatter());
    }
    
}
