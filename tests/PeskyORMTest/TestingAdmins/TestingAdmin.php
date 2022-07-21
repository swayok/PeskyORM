<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;

/**
 * @method $this setId($value, $isFromDb = false)
 * @method $this setParent($value, $isFromDb = false)
 * @method $this setChildren($value, $isFromDb = false)
 *
 * @property string $created_at_as_date
 * @property integer $parent_id
 *
 * @property Record $Parent
 * @property Record $HasOne
 * @property Record $Children
 * @property Record $VeryLongRelationNameSoItMustBeShortened
 */
class TestingAdmin extends Record
{
    
    /**
     * @return TestingAdminsTable
     */
    static public function getTable()
    {
        return TestingAdminsTable::getInstance();
    }
}