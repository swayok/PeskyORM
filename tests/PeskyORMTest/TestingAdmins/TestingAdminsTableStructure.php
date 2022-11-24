<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\DefaultColumnClosures;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableStructure;

class TestingAdminsTableStructure extends TableStructure
{
    
    public static function getTableName(): string
    {
        return 'admins';
    }
    
    public static function getConnectionName(bool $writable): string
    {
        return $writable ? 'writable' : parent::getConnectionName(false);
    }
    
    private function id(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->primaryKey()
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(TestingAdminsTable::getExpressionToSetDefaultValueForAColumn());
    }
    
    private function parent_id(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_INT)
            ->convertsEmptyStringToNull()
            ->allowsNullValues();
    }
    
    private function login(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->trimsValue();
    }
    
    private function password(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->trimsValue()
            ->setValuePreprocessor(function ($value, $isDbValue, $isForValidation, TableColumnInterface $column) {
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
            })
            ->privateValue();
    }
    
    private function created_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
    
    private function updated_at(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }
    
    private function remember_token(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->allowsNullValues()
            ->trimsValue();
    }
    
    private function is_superadmin(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(false);
    }
    
    private function language(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_ENUM)
            ->setAllowedValues(['en', 'ru', 'de'])
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('en');
    }
    
    private function ip(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_IPV4_ADDRESS)
            ->allowsNullValues()
            ->convertsEmptyStringToNull();
    }
    
    private function role(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_ENUM)
            ->setAllowedValues(['admin', 'manager', 'guest'])
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('guest');
    }
    
    private function is_active(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_BOOL)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
    
    private function name(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->disallowsNullValues()
            ->setDefaultValue('');
    }
    
    private function email(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_EMAIL)
            ->allowsNullValues();
    }
    
    private function timezone(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->convertsEmptyStringToNull()
            ->disallowsNullValues()
            ->setDefaultValue('UTC');
    }
    
    private function avatar(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_IMAGE)
            ->doesNotExistInDb()
            ->allowsNullValues()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }
    
    private function some_file(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_FILE)
            ->doesNotExistInDb()
            ->allowsNullValues()
            ->setValueFormatter(function () {
                return 'not implemented';
            });
    }
    
    private function not_changeable_column(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->valueCannotBeSetOrChanged();
    }
    
    private function not_existing_column(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->doesNotExistInDb();
    }
    
    private function not_existing_column_with_default_value(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->doesNotExistInDb()
            ->disallowsNullValues()
            ->setDefaultValue('default');
    }
    
    private function not_existing_column_with_calculated_value(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_STRING)
            ->doesNotExistInDb()
            ->valueCannotBeSetOrChanged()
            ->disallowsNullValues()
            ->setValueGetter(function (RecordValue $value) {
                $record = $value->getRecord();
                return 'calculated-' . ($record->existsInDb() ? $value->getRecord()->getPrimaryKeyValue() : '');
            })
            ->setValueExistenceChecker(function () {
                return true;
            });
    }
    
    private function big_data(): TableColumn
    {
        return TableColumn::create(TableColumn::TYPE_TEXT)
            ->disallowsNullValues()
            ->setDefaultValue('this is big data value! really! I\'m not joking!')
            ->valueIsHeavy();
    }
    
    private function Parent(): Relation
    {
        return new Relation('parent_id', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }
    
    private function HasOne(): Relation
    {
        return new Relation('id', Relation::HAS_ONE, TestingAdminsTable::class, 'parent_id');
    }
    
    private function Children(): Relation
    {
        return new Relation('id', Relation::HAS_MANY, TestingAdminsTable::class, 'parent_id');
    }
    
    private function VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters(): Relation
    {
        return new Relation('login', Relation::BELONGS_TO, TestingAdminsTable::class, 'id');
    }
    
    
}
