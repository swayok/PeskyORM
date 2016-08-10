<?php

namespace PeskyORMTest\TestingAdmin;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\DbTable;
use PeskyORM\ORM\DbTableColumn;
use PeskyORM\ORM\DbTableRelation;
use PeskyORM\ORM\DbTableStructure;

class TestingAdminsTableStructure extends DbTableStructure {

    private function id() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->itIsPrimaryKey()
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue(DbTable::getExpressionToSetDefaultValueForAColumn())
        ;
    }

    private function parent_id() {
        return DbTableColumn::create(DbTableColumn::TYPE_INT)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
        ;
    }

    private function login() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
            ->valueIsRequired()
        ;
    }

    private function password() {
        return DbTableColumn::create(DbTableColumn::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->mustTrimValue()
            ->valueIsRequired()
            ->setNewValuePreprocessor(function ($value, DbTableColumn $column) {
                $value = $column->defaultNewValuePreprocessor($value);
                if (!empty($value)) {
                    return password_hash($value, PASSWORD_BCRYPT);
                }
                return $value;
            })
            ->setDbValuePreprocessor(function ($value, DbTableColumn $column) {
                return $column->defaultNewValuePreprocessor($value);
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
            ->valueIsRequired()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }

    private function updated_at() {
        return DbTableColumn::create(DbTableColumn::TYPE_TIMESTAMP)
            ->valueIsNotNullable()
            ->valueIsRequired()
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
            ->valueIsRequired()
            ->setDefaultValue(false);
    }

    private function language() {
        return DbTableColumn::create(DbTableColumn::TYPE_ENUM)
            ->setAllowedValues(['en', 'ru'])
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
            ->valueIsRequired()
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
            ->valueIsNotNullable()
            ->setDefaultValue('');
    }

    private function timezone() {
        return DbTableColumn::create(DbTableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->valueIsNotNullable()
            ->setDefaultValue('UTC');
    }

    private function Parent() {
        return DbTableRelation::create($this, 'parent_id', DbTableRelation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }


}