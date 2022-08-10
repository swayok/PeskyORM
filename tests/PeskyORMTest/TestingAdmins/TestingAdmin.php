<?php

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordsSet;

/**
 * @property int $id
 * @property int $parent_id
 * @property string $login
 * @property string $email
 * @property string $password
 * @property string $created_at
 * @property string $created_at_as_date
 *
 * @property TestingAdmin $Parent
 * @property Record $HasOne
 * @property RecordsSet $Children
 * @property Record $VeryLongRelationNameSoItMustBeShortened
 *
 * @method $this setId($value, $isFromDb = false)
 * @method $this setParentId($value, $isFromDb = false)
 * @method $this setParent($value, $isFromDb = false)
 * @method $this setChildren($value, $isFromDb = false)
 */
class TestingAdmin extends Record
{
    
    public static function getTable(): TestingAdminsTable
    {
        return TestingAdminsTable::getInstance();
    }

    public function getColumnsNamesWithUpdatableValues(): array
    {
        return parent::getColumnsNamesWithUpdatableValues();
    }
    
    public function collectValuesForSave(array &$columnsToSave, bool $isUpdate): array
    {
        return parent::collectValuesForSave($columnsToSave, $isUpdate);
    }

    public function validateNewData(array $data, array $columnsNames = [], bool $isUpdate = false)
    {
        return parent::validateNewData($data, $columnsNames, $isUpdate);
    }
    
    
}