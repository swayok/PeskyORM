<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableColumnDefaultClosures;
use PeskyORM\ORM\DbTableRelation;
use PeskyORM\ORM\DbTableStructure;

class TestingAdminsTableStructure extends DbTableStructure {

    static public function getTableName() {
        return 'admins';
    }

    private function id() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey()
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(TestingAdminsTable::getExpressionToSetDefaultValueForAColumn())
        ;
    }

    private function parent_id() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->convertsEmptyStringToNull()
            ->valueIsNullable()
        ;
    }

    private function login() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
        ;
    }

    private function password() {
        return DbTableColumn::create(DbTableColumn::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
            ->setValuePreprocessor(function ($value, $isDbValue, DbTableColumn $column) {
                $value = DbTableColumnDefaultClosures::valuePreprocessor($value, $isDbValue, $column);
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
        return DbTableColumn::create(DbTableColumn::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }

    private function updated_at() {
        return DbTableColumn::create(DbTableColumn::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }

    private function remember_token() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNullable()
            ->mustTrimValue();
    }

    private function is_superadmin() {
        return DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(false);
    }

    private function language() {
        return DbTableColumn::create(DbTableColumn::TYPE_ENUM)
            ->setAllowedValues(['en', 'ru', 'de'])
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('en');
    }

    private function ip() {
        return DbTableColumn::create(DbTableColumn::TYPE_IPV4_ADDRESS)
            ->valueIsNullable()
            ->convertsEmptyStringToNull();
    }

    private function role() {
        return DbTableColumn::create(DbTableColumn::TYPE_ENUM)
            ->setAllowedValues(['admin', 'manager', 'guest'])
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('guest');
    }

    private function is_active() {
        return DbTableColumn::create(DbTableColumn::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(true);
    }

    private function name() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->valueIsNotNullable()
            ->setDefaultValue('');
    }

    private function email() {
        return DbTableColumn::create(DbTableColumn::TYPE_EMAIL)
            ->valueIsNullable();
    }

    private function timezone() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('UTC');
    }

    private function avatar() {
        return DbTableColumn::create(DbTableColumn::TYPE_IMAGE)
            ->itDoesNotExistInDb()
            ->valueIsNullable()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }

    private function some_file() {
        return DbTableColumn::create(DbTableColumn::TYPE_FILE)
            ->itDoesNotExistInDb()
            ->valueIsNullable()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }

    private function not_changeable_column() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->valueCannotBeSetOrChanged();
    }

    private function not_existing_column() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->itDoesNotExistInDb();
    }

    private function Parent() {
        return DbTableRelation::create('parent_id', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }

    private function Children() {
        return DbTableRelation::create('id', DbTableRelation::HAS_MANY, TestingAdminsTable::class, 'parent_id');
    }

    private function VeryLongRelationNameSoItMustBeShortened() {
        return DbTableRelation::create('parent_id', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }


}