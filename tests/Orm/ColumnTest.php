<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
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
        static::assertEquals(TableColumn::TYPE_BOOL, $column->getDataType());
        static::assertFalse($column->hasName());
        static::assertEquals('id', $column->setName('id')->getName());
        static::assertTrue($column->hasName());
        static::assertInstanceOf(\Closure::class, $column->getValueGetter());
        static::assertInstanceOf(\Closure::class, $column->getValueExistenceChecker());
        static::assertInstanceOf(\Closure::class, $column->getValueSetter());
        static::assertInstanceOf(\Closure::class, $column->getValueValidator());
        static::assertInstanceOf(\Closure::class, $column->getValueValidatorExtender());
        static::assertInstanceOf(\Closure::class, $column->getValueNormalizer());
        static::assertInstanceOf(\Closure::class, $column->getValuePreprocessor());
        static::assertInstanceOf(\Closure::class, $column->getValueSavingExtender());
        static::assertInstanceOf(\Closure::class, $column->getValueDeleteExtender());
        static::assertTrue($column->isReal());
        static::assertFalse($column->isPrimaryKey());
        static::assertFalse($column->isReadonly());
        static::assertFalse($column->shouldLowercaseValues());
        static::assertFalse($column->isValueMustBeUnique());
        static::assertFalse($column->isPrivateValues());
        static::assertFalse($column->shouldTrimValues());
        static::assertFalse($column->isAutoUpdatingValues());
        static::assertFalse($column->isFile());
        static::assertTrue($column->isNullableValues());
        static::assertTrue($column->shouldConvertEmptyStringToNull());
        $column->disallowsNullValues();
        static::assertFalse($column->isNullableValues());
        static::assertFalse($column->shouldConvertEmptyStringToNull());
    
        static::assertFalse($column->isForeignKey());
        $column->primaryKey();
        static::assertTrue($column->isPrimaryKey());
    
        $column = TableColumn::create(TableColumn::TYPE_BOOL, 'parent_id');
        $relation = new Relation(
            'parent_id',
            Relation::BELONGS_TO,
            TestingAdminsTable::getInstance(),
            'id'
        );
        $column->addRelation($relation->setName('Parent'));
        static::assertTrue($column->isForeignKey());
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
        $this->expectExceptionMessage('$name argument value (two words) has invalid format.');
        TableColumn::create(TableColumn::TYPE_BLOB, 'two words');
    }
    
    public function testInvalidNameSet5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name argument value (camelCase) has invalid format.');
        TableColumn::create(TableColumn::TYPE_DATE, 'camelCase');
    }
    
    public function testInvalidNameSet6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name argument value (UpperCase) has invalid format.');
        TableColumn::create(TableColumn::TYPE_EMAIL, 'UpperCase');
    }
    
    public function testDoubleNameSetter(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("TableColumn name changing is forbidden");
        $obj = TableColumn::create(TableColumn::TYPE_STRING)->setName('test');
        $obj->setName('test');
    }
    
    public function testFileTypes(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_FILE);
        static::assertEquals(TableColumn::TYPE_FILE, $obj->getDataType());
        static::assertTrue($obj->isFile());
        $obj = TableColumn::create(TableColumn::TYPE_IMAGE);
        static::assertEquals(TableColumn::TYPE_IMAGE, $obj->getDataType());
        static::assertTrue($obj->isFile());
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
        static::assertEquals(TableColumn::TYPE_TIMESTAMP, $obj->getDataType());
        static::assertInstanceOf(\Closure::class, $obj->getValueFormatter());
        $rec = TestingAdmin::fromArray(['created_at' => '2016-11-21 11:00:00']);
        /** @var \PeskyORM\ORM\Record\RecordValue $value */
        $value = $this->getObjectPropertyValue($rec, 'values')['created_at'];
        static::assertEquals(
            '11:00:00',
            call_user_func(
                $value->getColumn()->getValueFormatter(),
                $value,
                'time'
            )
        );
    }
    
    public function testInvalidDefaultValueGet1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'name'.*? is not set%"
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->getDefaultValue();
    }
    
    public function testInvalidDefaultValueGet2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'name'.*? is not valid\. Errors: Value must be of a boolean data type\.%"
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->setDefaultValue(-1)
            ->getValidDefaultValue();
    }
    
    public function testInvalidDefaultValueGet3(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Default value for column .*?'name'.*? is not set%"
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->getValidDefaultValue();
    }
    
    public function testInvalidDefaultValueGet4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches(
            "%Default value received from validDefaultValueGetter Closure for column .*?'name'.*? is not valid\. Errors: Value must be of a boolean data type\.%"
        );
        TableColumn::create(TableColumn::TYPE_BOOL, 'name')
            ->setValidDefaultValueGetter(function () {
                return -1;
            })
            ->getValidDefaultValue();
    }
    
    public function testDefaultValues(): void
    {
        $obj = TableColumn::create(TableColumn::TYPE_BOOL, 'name');
        static::assertFalse($obj->hasDefaultValue());

        $obj->setDefaultValue(function () {
            return false;
        });
        static::assertTrue($obj->hasDefaultValue());
        static::assertInstanceOf(\Closure::class, $obj->getDefaultValue());
        static::assertFalse($obj->getValidDefaultValue());
        
        $obj->setDefaultValue(false);
        static::assertTrue($obj->hasDefaultValue());
        static::assertFalse($obj->getDefaultValue());
        static::assertFalse($obj->getValidDefaultValue());
        
        $obj->setDefaultValue(null);
        static::assertTrue($obj->hasDefaultValue());
        static::assertNull($obj->getDefaultValue());
        static::assertNull($obj->getValidDefaultValue());
        
        // default value getter
        $obj->setValidDefaultValueGetter(function () {
            return false;
        });
        $obj->setDefaultValue(true);
        static::assertTrue($obj->hasDefaultValue());
        static::assertTrue($obj->getDefaultValue());
        static::assertFalse($obj->getValidDefaultValue());
        
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
    
    public function testInvalidGetRelation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*?'id'.*? is not linked with 'Test' relation%"
        );
        TableColumn::create(TableColumn::TYPE_STRING)
            ->setName('id')
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
