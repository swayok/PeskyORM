<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

// todo: delete this
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
        $valueObj = new RecordValue($column, TestingAdmin::_());
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
        
        $column->setDefaultValue(1);
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
        
        $valueObj->setValue(1, 1, true);
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
    }
    
    public function testValuePreprocessor(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_BOOL, 'test');
        static::assertEquals('', DefaultColumnClosures::valuePreprocessor('', false, false, $column));
        static::assertEquals(' ', DefaultColumnClosures::valuePreprocessor(' ', false, false, $column));
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor(null, false, false, $column));
        static::assertEquals([], DefaultColumnClosures::valuePreprocessor([], false, false, $column));
        static::assertEquals(['arr'], DefaultColumnClosures::valuePreprocessor(['arr'], false, false, $column));
        static::assertEquals(true, DefaultColumnClosures::valuePreprocessor(true, false, false, $column));
        static::assertEquals(false, DefaultColumnClosures::valuePreprocessor(false, false, false, $column));
        static::assertEquals(1, DefaultColumnClosures::valuePreprocessor(1, false, false, $column));
        static::assertEquals(-1.23, DefaultColumnClosures::valuePreprocessor(-1.23, false, false, $column));
        static::assertEquals('1.23', DefaultColumnClosures::valuePreprocessor('1.23', false, false, $column));
        $column->trimsValue()
            ->lowercasesValue()
            ->convertsEmptyStringToNull();
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor('', false, false, $column));
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor(' ', false, false, $column));
        static::assertEquals('a', DefaultColumnClosures::valuePreprocessor(' a ', false, false, $column));
        static::assertEquals('b', DefaultColumnClosures::valuePreprocessor(' B ', false, false, $column));
    }
    
    public function testValueValidator(): void
    {
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test')
            ->setValueValidatorExtender(function ($value) {
                return $value === 'a' ? ['extender!!!'] : [];
            });
        static::assertEquals(
            ['String value expected.'],
            DefaultColumnClosures::valueValidator(true, false, false, $column)
        );
        static::assertEquals(
            ['extender!!!'],
            DefaultColumnClosures::valueValidator('a', false, false, $column)
        );
        static::assertEquals([], DefaultColumnClosures::valueValidator('b', false, false, $column));
    }
    
    public function testInvalidFormatterInValueGetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'nooooo!' is not supported for column 'parent_id'. Supported formats: none");
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }
    
    public function testInvalidFormatInValueGetter1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value format 'nooooo!' is not supported for column 'created_at'. Supported formats: date, time, unix_ts");
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }
    
    public function testInvalidFormatInValueGetter2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument #2 (\$format) must be of type ?string");
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        /** @noinspection PhpStrictTypeCheckingInspection */
        DefaultColumnClosures::valueGetter($valueObj, false);
    }
    
    public function testValueGetter(): void
    {
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        $valueObj->setValue('2016-09-01', '2016-09-01', true);
        static::assertEquals('2016-09-01', DefaultColumnClosures::valueGetter($valueObj));
        static::assertEquals(strtotime('2016-09-01'), DefaultColumnClosures::valueGetter($valueObj, 'unix_ts'));
    }
    
    public function testValueSetIsForbidden(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Column 'test2' is read only");
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test1')
            ->valueCannotBeSetOrChanged();
        $valueObj = new RecordValue($column, TestingAdmin::_());
        $valueObj = DefaultColumnClosures::valueSetter('1', true, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());
        
        $column = TableColumn::create(TableColumn::TYPE_STRING, 'test2')
            ->valueCannotBeSetOrChanged();
        $valueObj = new RecordValue($column, TestingAdmin::_());
        DefaultColumnClosures::valueSetter('2', false, $valueObj, false);
    }
    
    public function testValueSetter(): void
    {
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        // new value
        $valueObj = DefaultColumnClosures::valueSetter('1', false, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertFalse($valueObj->isItFromDb());
        // change 'isItFromDb' status to true (should not be any changes other than $valueObj->isItFromDb())
        $valueObj = DefaultColumnClosures::valueSetter(1, true, $valueObj, false);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isItFromDb());
        // change value
        $valueObj = DefaultColumnClosures::valueSetter('2', true, $valueObj, false);
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals(2, $valueObj->getValue());
    }

    public function testValueSetterWithInvalidValue(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage('Validation errors: [parent_id] Integer value expected.');
        $valueObj = new RecordValue(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        // invalid value
        $valueObj = DefaultColumnClosures::valueSetter(false, true, $valueObj, false);
        static::assertEquals(false, $valueObj->getRawValue());
        static::assertEquals(false, $valueObj->getValue());
    }
    
}
