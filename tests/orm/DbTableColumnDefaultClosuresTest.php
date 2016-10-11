<?php


use PeskyORM\ORM\DbRecordValue;
use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableColumnDefaultClosures;
use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class DbTableColumnDefaultClosuresTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        \PeskyORMTest\TestingApp::init();
    }

    public function testValueNormalizer() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'test');
        static::assertTrue(DbTableColumnDefaultClosures::valueNormalizer('1', false, $column));
    }

    public function testValueExistenceChecker() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        static::assertFalse(DbTableColumnDefaultClosures::valueExistenceChecker($valueObj));
        $valueObj->setRawValue(1, 1, true)->setValidValue(1, 1);
        static::assertTrue(DbTableColumnDefaultClosures::valueExistenceChecker($valueObj));
    }

    public function testValuePreprocessor() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_BOOL, 'test');
        static::assertEquals('', DbTableColumnDefaultClosures::valuePreprocessor('', false, $column));
        static::assertEquals(' ', DbTableColumnDefaultClosures::valuePreprocessor(' ', false, $column));
        static::assertEquals(null, DbTableColumnDefaultClosures::valuePreprocessor(null, false, $column));
        static::assertEquals([], DbTableColumnDefaultClosures::valuePreprocessor([], false, $column));
        static::assertEquals(['arr'], DbTableColumnDefaultClosures::valuePreprocessor(['arr'], false, $column));
        static::assertEquals(true, DbTableColumnDefaultClosures::valuePreprocessor(true, false, $column));
        static::assertEquals(false, DbTableColumnDefaultClosures::valuePreprocessor(false, false, $column));
        static::assertEquals(1, DbTableColumnDefaultClosures::valuePreprocessor(1, false, $column));
        static::assertEquals(-1.23, DbTableColumnDefaultClosures::valuePreprocessor(-1.23, false, $column));
        static::assertEquals('1.23', DbTableColumnDefaultClosures::valuePreprocessor('1.23', false, $column));
        $column->mustTrimValue()->mustLowercaseValue()->convertsEmptyStringToNull();
        static::assertEquals(null, DbTableColumnDefaultClosures::valuePreprocessor('', false, $column));
        static::assertEquals(null, DbTableColumnDefaultClosures::valuePreprocessor(' ', false, $column));
        static::assertEquals('a', DbTableColumnDefaultClosures::valuePreprocessor(' a ', false, $column));
        static::assertEquals('b', DbTableColumnDefaultClosures::valuePreprocessor(' B ', false, $column));
    }

    public function testIsValueAllowedValidator() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b']);
        static::assertEquals([], DbTableColumnDefaultClosures::valueIsAllowedValidator('a', false, $column));
        static::assertEquals(
            ['Value is not allowed'],
            DbTableColumnDefaultClosures::valueIsAllowedValidator('c', false, $column)
        );
    }

    public function testValueValidator() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_ENUM, 'test')
            ->setAllowedValues(['a', 'b'])
            ->setValueValidatorExtender(function ($value, $isFromDb, DbTableColumn $column) {
                return $value === 'a' ? ['extender!!!'] : [];
            });
        static::assertEquals(
            ['Value is not allowed'],
            DbTableColumnDefaultClosures::valueValidator('c', false, $column)
        );
        static::assertEquals(
            ['Value must be a string or a number'],
            DbTableColumnDefaultClosures::valueValidator(true, false, $column)
        );
        static::assertEquals(
            ['extender!!!'],
            DbTableColumnDefaultClosures::valueValidator('a', false, $column)
        );
        static::assertEquals([], DbTableColumnDefaultClosures::valueValidator('b', false, $column));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument is not supported for column 'parent_id'
     */
    public function testInvalidFormatterInValueGetter() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        DbTableColumnDefaultClosures::valueGetter($valueObj, 'nooooo!');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Value format named 'nooooo!' is not supported for column 'created_at'
     */
    public function testInvalidFormatInValueGetter1() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DbTableColumnDefaultClosures::valueGetter($valueObj, 'nooooo!');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $format argument for column 'created_at' must be a string or a number.
     */
    public function testInvalidFormatInValueGetter2() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        DbTableColumnDefaultClosures::valueGetter($valueObj, false);
    }

    public function testValueGetter() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('created_at'), TestingAdmin::_());
        $valueObj->setRawValue('2016-09-01', '2016-09-01', true)->setValidValue('2016-09-01', '2016-09-01');
        static::assertEquals('2016-09-01', DbTableColumnDefaultClosures::valueGetter($valueObj));
        static::assertEquals(strtotime('2016-09-01'), DbTableColumnDefaultClosures::valueGetter($valueObj, 'unix_ts'));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Column 'test' restricts value setting and modification
     */
    public function testValueSetIsForbidden() {
        $column = DbTableColumn::create(DbTableColumn::TYPE_STRING, 'test')
            ->valueCannotBeSetOrChanged();
        $valueObj = DbRecordValue::create($column, TestingAdmin::_());
        DbTableColumnDefaultClosures::valueSetter('test', false, $valueObj);
    }

    public function testValueSetter() {
        $valueObj = DbRecordValue::create(TestingAdminsTableStructure::getColumn('parent_id'), TestingAdmin::_());
        // new value
        DbTableColumnDefaultClosures::valueSetter('1', false, $valueObj);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertFalse($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change 'isItFromDb' status to true (should not be any changes other than $valueObj->isItFromDb())
        DbTableColumnDefaultClosures::valueSetter(1, true, $valueObj);
        static::assertEquals('1', $valueObj->getRawValue());
        static::assertEquals(1, $valueObj->getValue());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        static::assertFalse($valueObj->hasOldValue());
        // change value
        DbTableColumnDefaultClosures::valueSetter('2', true, $valueObj);
        static::assertEquals('2', $valueObj->getRawValue());
        static::assertEquals(2, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(1, $valueObj->getOldValue());
        static::assertTrue($valueObj->isOldValueWasFromDb());
        static::assertTrue($valueObj->isValidated());
        static::assertTrue($valueObj->isValid());
        static::assertTrue($valueObj->isItFromDb());
        // invalid value
        DbTableColumnDefaultClosures::valueSetter(false, true, $valueObj);
        static::assertEquals(false, $valueObj->getRawValue());
        static::assertEquals(false, $valueObj->getValue());
        static::assertTrue($valueObj->hasOldValue());
        static::assertEquals(2, $valueObj->getOldValue());
        static::assertTrue($valueObj->isValidated());
        static::assertFalse($valueObj->isValid());
        static::assertEquals(['Value must be of an integer data type'], $valueObj->getValidationErrors());
    }

}
