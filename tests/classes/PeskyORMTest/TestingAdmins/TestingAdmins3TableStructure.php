<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\ColumnDefaultClosures;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;

class TestingAdmins3TableStructure extends TableStructure {

    static public function getTableName() {
        return 'admins';
    }

    private function id() {
        return Column::create(Column::TYPE_INT)
            ->itIsPrimaryKey()
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(TestingAdminsTable::getExpressionToSetDefaultValueForAColumn())
        ;
    }

    private function parent_id() {
        return Column::create(Column::TYPE_INT)
            ->convertsEmptyStringToNull()
            ->valueIsNullable()
        ;
    }

    private function login() {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
            ->setValueSavingExtender(function ($valueContainer, $isUpdate) {
                if ($isUpdate) {
                    throw new \UnexpectedValueException('login: update!');
                }
            })
        ;
    }

    private function password() {
        return Column::create(Column::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
            ->setValuePreprocessor(function ($value, $isDbValue, Column $column) {
                $value = ColumnDefaultClosures::valuePreprocessor($value, $isDbValue, $column);
                if ($isDbValue) {
                    return $value;
                } else {
                    if (!empty($value)) {
                        return password_hash($value, PASSWORD_BCRYPT);
                    }
                    return $value;
                }
            })
            ->extendValueValidator(function ($value) {
                if (mb_strlen($value) !== 60) {
                    return ['Password hash length does not match bcrypt hash length'];
                }
                return [];
            })
        ;
    }

    private function created_at() {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }

    private function updated_at() {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }

    private function remember_token() {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNullable()
            ->mustTrimValue();
    }

    private function is_superadmin() {
        return Column::create(Column::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(false);
    }

    private function language() {
        return Column::create(Column::TYPE_ENUM)
            ->setAllowedValues(['en', 'ru', 'de'])
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('en');
    }

    private function ip() {
        return Column::create(Column::TYPE_IPV4_ADDRESS)
            ->valueIsNullable()
            ->convertsEmptyStringToNull();
    }

    private function role() {
        return Column::create(Column::TYPE_ENUM)
            ->setAllowedValues(['admin', 'manager', 'guest'])
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('guest');
    }

    private function is_active() {
        return Column::create(Column::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(true);
    }

    private function name() {
        return Column::create(Column::TYPE_STRING)
            ->valueIsNotNullable()
            ->setDefaultValue('');
    }

    private function email() {
        return Column::create(Column::TYPE_EMAIL)
            ->valueIsNullable();
    }

    private function timezone() {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('UTC');
    }

    private function avatar() {
        return Column::create(Column::TYPE_IMAGE)
            ->itDoesNotExistInDb()
            ->valueIsNullable()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }

    private function some_file() {
        return Column::create(Column::TYPE_FILE)
            ->itDoesNotExistInDb()
            ->valueIsNullable()
            ->setValueFormatter(function () {
                return 'not implemented';
            })
            ->setValueSavingExtender(function () {
                throw new \UnexpectedValueException('some_file: here');
            });
    }

    private function not_changeable_column() {
        return Column::create(Column::TYPE_STRING)
            ->valueCannotBeSetOrChanged();
    }

    private function not_existing_column() {
        return Column::create(Column::TYPE_STRING)
            ->itDoesNotExistInDb();
    }

    private function Parent() {
        return Relation::create('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }

    private function Children() {
        return Relation::create('id', Relation::HAS_MANY, TestingAdminsTable::class, 'parent_id');
    }

    private function VeryLongRelationNameSoItMustBeShortened() {
        return Relation::create('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }


}