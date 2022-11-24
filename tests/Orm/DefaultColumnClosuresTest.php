<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class DefaultColumnClosuresTest extends BaseTestCase
{
    
    public function testValueNormalizer(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_BOOL, 'test');
        static::assertTrue(DefaultColumnClosures::valueNormalizer('1', false, $column));
    }
    
    public function testValueExistenceChecker(): void
    {
        $column = TestingAdminsTableStructure::getColumn('parent_id');
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create($column, TestingAdmin::_());
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
        
        $column->setDefaultValue(1);
        static::assertFalse(\PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
        
        $valueObj->setRawValue(1, 1, true)
            ->setValidValue(1, 1);
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
    }
    
    public function testValuePreprocessor(): void
    {
        $column = TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_BOOL, 'test');
        static::assertEquals('', DefaultColumnClosures::valuePreprocessor('', false, false, $column));
        static::assertEquals(' ', \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valuePreprocessor(' ', false, false, $column));
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor(null, false, false, $column));
        static::assertEquals([], DefaultColumnClosures::valuePreprocessor([], false, false, $column));
        static::assertEquals(['arr'], DefaultColumnClosures::valuePreprocessor(['arr'], false, false, $column));
        static::assertEquals(true, DefaultColumnClosures::valuePreprocessor(true, false, false, $column));
        static::assertEquals(false, DefaultColumnClosures::valuePreprocessor(false, false, false, $column));
        static::assertEquals(1, \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valuePreprocessor(1, false, false, $column));
        static::assertEquals(-1.23, DefaultColumnClosures::valuePreprocessor(-1.23, false, false, $column));
        static::assertEquals('1.23', DefaultColumnClosures::valuePreprocessor('1.23', false, false, $column));
        $column->trimsValue()
            ->lowercasesValue()
            ->convertsEmptyStringToNull();
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor('', false, false, $column));
        static::assertEquals(null, \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valuePreprocessor(' ', false, false, $column));
        static::assertEquals('a', DefaultColumnClosures::valuePreprocessor(' a ', false, false, $column));
        static::assertEquals('b', DefaultColumnClosures::valuePreprocessor(' B ', false, false, $column));
    }
    
    public function testIsValueAllowedValidator(): void
    {
        $column = TableColumn::create(\PeskyORM\ORM\TableStructure\TableColumn\TableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b']);
        static::assertEquals([], DefaultColumnClosures::valueIsAllowedValidator('a', false, $column));
        static::assertEquals(
            ['Value is not allowed: c.'],
            DefaultColumnClosures::valueIsAllowedValidator('c', false, $column)
        );
    }
    
    public function testValueValidator(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b'])
            ->setValueValidatorExtender(function ($value) {
                return $value === 'a' ? ['extender!!!'] : [];
            });
        static::assertEquals(
            ['Value is not allowed: c.'],
            DefaultColumnClosures::valueValidator('c', false, false, $column)
        );
        static::assertEquals(
            ['Value must be a string or a number.'],
            \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueValidator(true, false, false, $column)
        );
        static::assertEquals(
            ['extender!!!'],
            \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueValidator('a', false, false, $column)
        );
        static::assertEquals([], DefaultColumnClosures::valueValidator('b', false, false, $column));
    }
    
    public function testInvalidFormatterInValueGetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'nooooo!' is not supported for column 'parent_id'. Supported formats: none");
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }
    
    public function testInvalidFormatInValueGetter1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'nooooo!' is not supported for column 'created_at'. Supported formats: date, time, unix_ts");
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }
    
    public function testInvalidFormatInValueGetter2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$format) must be of type ?string");
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        DefaultColumnClosures::valueGetter($valueObj, false);
    }
    
    public function testValueGetter(): void
    {
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        $valueObj->setRawValue('2016-09-01', '2016-09-01', true)
            ->setValidValue('2016-09-01', '2016-09-01');
        static::assertEquals('2016-09-01', DefaultColumnClosures::valueGetter($valueObj));
        static::assertEquals(strtotime('2016-09-01'), \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueGetter($valueObj, 'unix_ts'));
    }
    
    public function testValueSetIsForbidden(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("TableColumn 'test2' restricts value modification");
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test1')
            ->valueCannotBeSetOrChanged();
        $valueObj = RecordValue::create($column, TestingAdmin::_());
        DefaultColumnClosures::valueSetter('1', true, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());
        
        $column = \PeskyORM\ORM\TableStructure\TableColumn\TableColumn::create(TableColumn::TYPE_STRING, 'test2')
            ->valueCannotBeSetOrChanged();
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create($column, TestingAdmin::_());
        DefaultColumnClosures::valueSetter('2', false, $valueObj, false);
    }
    
    public function testValueSetter(): void
    {
        $valueObj = \PeskyORM\ORM\Record\RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        // new value
        DefaultColumnClosures::valueSetter('1', false, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertFalse($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change 'isItFromDb' status to true (should not be any changes other than $valueObj->isItFromDb())
        \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueSetter(1, true, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change value
        \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueSetter('2', true, $valueObj, false);
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals(2, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(1, $valueObj->getOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        // invalid value
        \PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures::valueSetter(false, true, $valueObj, false);
        static::assertEquals(false, $valueObj->getRawValue());
        static::assertEquals(false, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(2, $valueObj->getOldValue());
        static::assertTrue($valueObj->isValidated());
        static::assertFalse($valueObj->isValid());
        static::assertEquals(['Value must be of an integer data type.'], $valueObj->getValidationErrors());
    }
    
}
