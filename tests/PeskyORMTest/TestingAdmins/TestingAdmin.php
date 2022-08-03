<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

/**
 * @property int $id
 * @property int $parent_id
 * @property string $login
 * @property string $email
 * @property string $password
 * @property string $created_at_as_date
 *
 * @property Record $Parent
 * @property Record $HasOne
 * @property Record $Children
 * @property Record $VeryLongRelationNameSoItMustBeShortened
 *
 * @method $this setId($value, $isFromDb = false)
 * @method $this setParent($value, $isFromDb = false)
 * @method $this setChildren($value, $isFromDb = false)
 */
class TestingAdmin extends Record
{
    
    static public function getTable(): TestingAdminsTable
    {
        return TestingAdminsTable::getInstance();
    }
}