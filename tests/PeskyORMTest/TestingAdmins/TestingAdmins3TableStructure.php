<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableStructure;

class TestingAdmins3TableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(TestingAdminsTable::getExpressionToSetDefaultValueForAColumn());
    }
    
    private function parent_id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->convertsEmptyStringToNull()
            ->allowsNullValues();
    }
    
    private function login(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->trimsValue()
            ->setValueSavingExtender(function ($valueContainer, $isUpdate) {
                if ($isUpdate) {
                    throw new \UnexpectedValueException('login: update!');
                }
            });
    }
    
    private function password(): Column
    {
        return Column::create(Column::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->trimsValue()
            ->setValuePreprocessor(function ($value, $isDbValue, $isForValidation, Column $column) {
                $value = DefaultColumnClosures::valuePreprocessor($value, $isDbValue, $isForValidation, $column);
                if (!$isDbValue && !empty($value)) {
                    return password_hash($value, PASSWORD_BCRYPT);
                }
                return $value;
            })
            ->extendValueValidator(function ($value) {
                if (mb_strlen($value) !== 60) {
                    return ['Password hash length does not match bcrypt hash length'];
                }
                return [];
            });
    }
    
    private function created_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
    
    private function updated_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }
    
    private function remember_token(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->allowsNullValues()
            ->trimsValue();
    }
    
    private function is_superadmin(): Column
    {
        return Column::create(Column::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(false);
    }
    
    private function language(): Column
    {
        return Column::create(Column::TYPE_ENUM)
            ->setAllowedValues(['en', 'ru', 'de'])
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('en');
    }
    
    private function ip(): Column
    {
        return Column::create(Column::TYPE_IPV4_ADDRESS)
            ->allowsNullValues()
            ->convertsEmptyStringToNull();
    }
    
    private function role(): Column
    {
        return Column::create(Column::TYPE_ENUM)
            ->setAllowedValues(['admin', 'manager', 'guest'])
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('guest');
    }
    
    private function is_active(): Column
    {
        return Column::create(Column::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
    
    private function name(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->disallowsNullValues()
            ->setDefaultValue('');
    }
    
    private function email(): Column
    {
        return Column::create(Column::TYPE_EMAIL)
            ->allowsNullValues();
    }
    
    private function timezone(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('UTC');
    }
    
    private function avatar(): Column
    {
        return Column::create(Column::TYPE_IMAGE)
            ->doesNotExistInDb()
            ->allowsNullValues()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }
    
    private function some_file(): Column
    {
        return Column::create(Column::TYPE_FILE)
            ->doesNotExistInDb()
            ->allowsNullValues()
            ->setValueFormatter(function () {
                return 'not implemented';
            })
            ->setValueSavingExtender(function () {
                throw new \UnexpectedValueException('some_file: here');
            });
    }
    
    private function big_data(): Column
    {
        return Column::create(Column::TYPE_TEXT)
            ->disallowsNullValues()
            ->setDefaultValue('this is big data value! really! I\'m not joking!')
            ->valueIsHeavy();
    }
    
    private function not_changeable_column(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->valueCannotBeSetOrChanged();
    }
    
    private function not_existing_column(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->doesNotExistInDb();
    }
    
    private function Parent(): Relation
    {
        return Relation::create('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }
    
    private function Children(): Relation
    {
        return Relation::create('id', Relation::HAS_MANY, TestingAdminsTable::class, 'parent_id');
    }
    
    private function VeryLongRelationNameSoItMustBeShortened(): Relation
    {
        return Relation::create('login', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }
    
    
}