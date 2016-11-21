<?php

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordValue;
use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;

class DbTableColumnDefaultClosuresTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::getPgsqlConnection();
    }

    public function testValueNormalizer() {
        $column = Column::create(Column::TYPE_BOOL, 'test');
        static::assertTrue(DefaultColumnClosures::valueNormalizer('1', false, $column));
    }

    public function testValueExistenceChecker() {
        $column = TestingAdminsTableStructure::getColumn('parent_id');
        $valueObj = RecordValue::create($column, TestingAdmin::_());
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, true));

        $column->setDefaultValue(1);
        static::assertFalse(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));

        $valueObj->setRawValue(1, 1, true)->setValidValue(1, 1);
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, false));
        static::assertTrue(DefaultColumnClosures::valueExistenceChecker($valueObj, true));
    }

    public function testValuePreprocessor() {
        $column = Column::create(Column::TYPE_BOOL, 'test');
        static::assertEquals('', DefaultColumnClosures::valuePreprocessor('', false, $column));
        static::assertEquals(' ', DefaultColumnClosures::valuePreprocessor(' ', false, $column));
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor(null, false, $column));
        static::assertEquals([], DefaultColumnClosures::valuePreprocessor([], false, $column));
        static::assertEquals(['arr'], DefaultColumnClosures::valuePreprocessor(['arr'], false, $column));
        static::assertEquals(true, DefaultColumnClosures::valuePreprocessor(true, false, $column));
        static::assertEquals(false, DefaultColumnClosures::valuePreprocessor(false, false, $column));
        static::assertEquals(1, DefaultColumnClosures::valuePreprocessor(1, false, $column));
        static::assertEquals(-1.23, DefaultColumnClosures::valuePreprocessor(-1.23, false, $column));
        static::assertEquals('1.23', DefaultColumnClosures::valuePreprocessor('1.23', false, $column));
        $column->mustTrimValue()->mustLowercaseValue()->convertsEmptyStringToNull();
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor('', false, $column));
        static::assertEquals(null, DefaultColumnClosures::valuePreprocessor(' ', false, $column));
        static::assertEquals('a', DefaultColumnClosures::valuePreprocessor(' a ', false, $column));
        static::assertEquals('b', DefaultColumnClosures::valuePreprocessor(' B ', false, $column));
    }

    public function testIsValueAllowedValidator() {
        $column = Column::create(Column::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b']);
        static::assertEquals([], DefaultColumnClosures::valueIsAllowedValidator('a', false, $column));
        static::assertEquals(
            ['Value is not allowed'],
            DefaultColumnClosures::valueIsAllowedValidator('c', false, $column)
        );
    }

    public function testValueValidator() {
        $column = Column::create(Column::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b'])
            ->setValueValidatorExtender(function ($value, $isFromDb, Column $column) {
                return $value === 'a' ? ['extender!!!'] : [];
            });
        static::assertEquals(
            ['Value is not allowed'],
            DefaultColumnClosures::valueValidator('c', false, $column)
        );
        static::assertEquals(
            ['Value must be a string or a number'],
            DefaultColumnClosures::valueValidator(true, false, $column)
        );
        static::assertEquals(
            ['extender!!!'],
            DefaultColumnClosures::valueValidator('a', false, $column)
        );
        static::assertEquals([], DefaultColumnClosures::valueValidator('b', false, $column));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Value format 'nooooo!' is not supported for column 'parent_id'. Supported formats: none
     */
    public function testInvalidFormatterInValueGetter() {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Value format 'nooooo!' is not supported for column 'created_at'
     */
    public function testInvalidFormatInValueGetter1() {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, 'nooooo!');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument for column 'created_at' must be a string or a number.
     */
    public function testInvalidFormatInValueGetter2() {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DefaultColumnClosures::valueGetter($valueObj, false);
    }

    public function testValueGetter() {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        $valueObj->setRawValue('2016-09-01', '2016-09-01', true)->setValidValue('2016-09-01', '2016-09-01');
        static::assertEquals('2016-09-01', DefaultColumnClosures::valueGetter($valueObj));
        static::assertEquals(strtotime('2016-09-01'), DefaultColumnClosures::valueGetter($valueObj, 'unix_ts'));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Column 'test2' restricts value modification
     */
    public function testValueSetIsForbidden() {
        $column = Column::create(Column::TYPE_STRING, 'test1')
            ->valueCannotBeSetOrChanged();
        $valueObj = RecordValue::create($column, TestingAdmin::_());
        DefaultColumnClosures::valueSetter('1', true, $valueObj);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals('1', $valueObj->getValue());

        $column = Column::create(Column::TYPE_STRING, 'test2')
            ->valueCannotBeSetOrChanged();
        $valueObj = RecordValue::create($column, TestingAdmin::_());
        DefaultColumnClosures::valueSetter('2', false, $valueObj);
    }

    public function testValueSetter() {
        $valueObj = RecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        // new value
        DefaultColumnClosures::valueSetter('1', false, $valueObj);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertFalse($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change 'isItFromDb' status to true (should not be any changes other than $valueObj->isItFromDb())
        DefaultColumnClosures::valueSetter(1, true, $valueObj);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change value
        DefaultColumnClosures::valueSetter('2', true, $valueObj);
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals(2, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(1, $valueObj->getOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        // invalid value
        DefaultColumnClosures::valueSetter(false, true, $valueObj);
        static::assertEquals(false, $valueObj->getRawValue());
        static::assertEquals(false, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(2, $valueObj->getOldValue());
        static::assertTrue($valueObj->isValidated());
        static::assertFalse($valueObj->isValid());
        static::assertEquals(['Value must be of an integer data type'], $valueObj->getValidationErrors());
    }

}
