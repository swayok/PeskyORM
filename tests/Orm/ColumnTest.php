<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingValueToObjectConverter;
use Swayok\Utils\NormalizeValue;

class ColumnTest extends BaseTestCase
{
    
    public function testInvalidConstructor1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(null);
    }
    
    public function testInvalidConstructor2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create([]);
    }
    
    public function testInvalidConstructor3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create($this);
    }
    
    public function testInvalidConstructor4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(true);
    }
    
    public function testInvalidConstructor5(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($type) must be of type string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(false);
    }
    
    public function testConstructor(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_BOOL);
        static::assertInstanceOf(TableColumn::class, $column);
        static::assertEquals(TableColumn::TYPE_BOOL, $column->getType());
        static::assertFalse($column->hasName());
        static::assertEquals('id', $column->setName('id')->getName());
        static::assertTrue($column->hasName());
        static::assertInstanceOf(\Closure::class, $column->getValueGetter());
        static::assertInstanceOf(\Closure::class, $column->getValueExistenceChecker());
        static::assertInstanceOf(\Closure::class, $column->getValueSetter());
        static::assertInstanceOf(\Closure::class, $column->getValueValidator());
        static::assertInstanceOf(\Closure::class, $column->getValueIsAllowedValidator());
        static::assertInstanceOf(\Closure::class, $column->getValueValidatorExtender());
        static::assertInstanceOf(\Closure::class, $column->getValueNormalizer());
        static::assertInstanceOf(\Closure::class, $column->getValuePreprocessor());
        static::assertInstanceOf(\Closure::class, $column->getValueSavingExtender());
        static::assertInstanceOf(\Closure::class, $column->getValueDeleteExtender());
        static::assertTrue($column->isItExistsInDb());
        static::assertFalse($column->isItPrimaryKey());
        static::assertTrue($column->isValueCanBeSetOrChanged());
        static::assertFalse($column->isValueLowercasingRequired());
        static::assertFalse($column->isValueMustBeUnique());
        static::assertFalse($column->isValuePrivate());
        static::assertFalse($column->isValueTrimmingRequired());
        static::assertFalse($column->isAutoUpdatingValue());
        static::assertFalse($column->isEnum());
        static::assertFalse($column->isItAFile());
        static::assertFalse($column->isItAnImage());
        static::assertTrue($column->isValueCanBeNull());
        static::assertTrue($column->isEmptyStringMustBeConvertedToNull());
        $column->disallowsNullValues();
        static::assertFalse($column->isValueCanBeNull());
        static::assertFalse($column->isEmptyStringMustBeConvertedToNull());
    
        $column->setTableStructure(TestingAdminsTableStructure::getInstance());
        static::assertFalse($column->isItAForeignKey());
        $column->primaryKey();
        static::assertTrue($column->isItPrimaryKey());
    
        $column = TableColumn::create(TableColumn::TYPE_BOOL, 'parent_id');
        $column->setTableStructure(TestingAdminsTableStructure::getInstance());
        static::assertTrue($column->isItAForeignKey());
    }
    
    public function testTableStructureNotSet1(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_BOOL);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableColumn::class, $obj);
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::getTableStructure(): Return value must be of type PeskyORM\ORM\TableStructure\TableStructureInterface, null returned');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $obj->getTableStructure();
    }
    
    public function testInvalidName1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("DB column name is not provided");
        TableColumn::create(TableColumn::TYPE_STRING, null)->getName();
    }
    
    public function testInvalidName2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(TableColumn::TYPE_STRING, false)->getName();
    }
    
    public function testInvalidName3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        TableColumn::create(TableColumn::TYPE_STRING, [])->getName();
    }
    
    public function testInvalidNameSet1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        TableColumn::create(TableColumn::TYPE_INT, ['arr']);
    }
    
    public function testInvalidNameSet2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        /** @noinspection PhpParamsInspection */
        TableColumn::create(TableColumn::TYPE_FLOAT, $this);
    }
    
    public function testInvalidNameSet3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::create(): Argument #2 ($name) must be of type ?string');
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(TableColumn::TYPE_IPV4_ADDRESS, true);
    }
    
    public function testInvalidNameSet4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name argument contains invalid value: \'two words\'');
        TableColumn::create(TableColumn::TYPE_BLOB, 'two words');
    }
    
    public function testInvalidNameSet5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name argument contains invalid value: \'camelCase\'');
        TableColumn::create(TableColumn::TYPE_DATE, 'camelCase');
    }
    
    public function testInvalidNameSet6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name argument contains invalid value: \'UpperCase\'');
        TableColumn::create(TableColumn::TYPE_EMAIL, 'UpperCase');
    }
    
    public function testDoubleNameSetter(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("TableColumn name changing is forbidden");
        $obj = TableColumn::create(TableColumn::TYPE_ENUM)->setName('test');
        $obj->setName('test');
    }
    
    public function testFileTypes(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_FILE);
        static::assertEquals(TableColumn::TYPE_FILE, $obj->getType());
        static::assertTrue($obj->isItAFile());
        $obj = TableColumn::create(TableColumn::TYPE_IMAGE);
        static::assertEquals(TableColumn::TYPE_IMAGE, $obj->getType());
        static::assertTrue($obj->isItAFile());
        static::assertTrue($obj->isItAnImage());
    }
    
    public function testEnumType(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_ENUM);
        static::assertEquals(TableColumn::TYPE_ENUM, $obj->getType());
        static::assertTrue($obj->isEnum());
    }
    
    public function testInvalidValueFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'time' is not supported for column 'login'. Supported formats: none");
        $rec = TestingAdmin::newEmptyRecord();
        $rec->updateValue('login', 'test', false);
        static::assertEquals('test', $rec->getValue('login'));
        /** @var \PeskyORM\ORM\Record\RecordValue $value */
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
    
    public function testFormattersDetectedByType(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_TIMESTAMP);
        static::assertEquals(TableColumn::TYPE_TIMESTAMP, $obj->getType());
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        $rec = TestingAdmin::fromArray(['created_at' => '2016-11-21 11:00:00']);
        /** @var \PeskyORM\ORM\Record\RecordValue $value */
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
    
    public function testInvalidDefaultValueGet1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Default value for column 'name' is not set");
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->getDefaultValueAsIs();
    }
    
    public function testInvalidDefaultValueGet2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type.");
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->setDefaultValue(-1)
            ->getValidDefaultValue();
    }
    
    public function testInvalidDefaultValueGet3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Fallback value of the default value for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type."
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->getValidDefaultValue(-1);
    }
    
    public function testInvalidDefaultValueGet4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Default value received from validDefaultValueGetter Closure for column PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure->name is not valid. Errors: Value must be of a boolean data type."
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->setValidDefaultValueGetter(function () {
                return -1;
            })
            ->getValidDefaultValue(true);
    }
    
    public function testDefaultValues(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_BOOL, 'name');
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
        $obj->setValidDefaultValueGetter(function ($fallbackValue) {
            return $fallbackValue;
        });
        $obj->setDefaultValue(true);
        static::assertTrue($obj->hasDefaultValue());
        static::assertTrue($obj->getDefaultValueAsIs());
        static::assertFalse($obj->getValidDefaultValue(false));
        
        // default value that needs normalization
        $nowTs = time();
        $obj = TableColumn::create(TableColumn::TYPE_TIMESTAMP, 'name')
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
    
    public function testInvalidGetAllowedValues(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return -1;
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Allowed values closure must return a not-empty array");
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(function () {
                return [];
            });
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues3(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($allowedValues) must be of type Closure|array');
        /** @noinspection PhpParamsInspection */
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(-1);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($allowedValues) must be of type Closure|array');
        /** @noinspection PhpParamsInspection */
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(false);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$allowedValues argument cannot be empty");
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues([]);
        $obj->getAllowedValues();
    }
    
    public function testInvalidSetAllowedValues6(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($allowedValues) must be of type Closure|array');
        /** @noinspection PhpParamsInspection */
        $obj = TableColumn::create(TableColumn::TYPE_BOOL)
            ->setAllowedValues(null);
        $obj->getAllowedValues();
    }
    
    public function testAllwedValues(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_ENUM);
        static::assertEquals([], $obj->getAllowedValues());
        $obj->setAllowedValues(['test']);
        static::assertEquals(['test'], $obj->getAllowedValues());
        $obj->setAllowedValues(function () {
            return ['test2'];
        });
        static::assertEquals(['test2'], $obj->getAllowedValues());
    }
    
    public function testInvalidGetRelation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("TableColumn 'id' is not linked with 'Test' relation");
        TableColumn::create(TableColumn::TYPE_ENUM)
            ->setName('id')
            ->setTableStructure(TestingAdminsTableStructure::getInstance())
            ->getRelation('Test');
    }
    
    public function testInvalidSetClosuresClass1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$className argument must be a string and contain a full name of a class that implements ColumnClosuresInterface");
        TableColumn::create(TableColumn::TYPE_STRING)
            ->setClosuresClass(TestingAdmin::class);
    }
    
    public function testInvalidSetClosuresClass2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('TableColumn::setClosuresClass(): Argument #1 ($className) must be of type string');
        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpStrictTypeCheckingInspection */
        TableColumn::create(TableColumn::TYPE_STRING)
            ->setClosuresClass(new DefaultColumnClosures());
    }
    
    public function testSetClosuresClass(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_STRING)
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
    
    public function testInvalidSetClassNameForValueToObjectFormatter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$className argument must be a string and contain a full name of a class that implements PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ValueToObjectConverterInterface');
        TableColumn::create(TableColumn::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(DefaultColumnClosures::class);
    }
    
    public function testSetClassNameForValueToObjectFormatter(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(TestingValueToObjectConverter::class);
        
        static::assertEquals(TestingValueToObjectConverter::class, $obj->getObjectClassNameForValueToObjectFormatter());
    
        $obj = TableColumn::create(TableColumn::TYPE_STRING)
            ->setClassNameForValueToObjectFormatter(null);
        static::assertNull($obj->getObjectClassNameForValueToObjectFormatter());
    }
    
}
