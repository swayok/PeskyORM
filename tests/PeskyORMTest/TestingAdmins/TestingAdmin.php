<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\RecordsCollection\SelectedRecordsArray;

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
 * @property SelectedRecordsArray $Children
 * @property Record $VeryLongRelationNameSoItMustBeShortened
 *
 * @method $this setId($value, $isFromDb = false)
 * @method $this setParentId($value, $isFromDb = false)
 * @method $this setParent($value, $isFromDb = false)
 * @method $this setChildren($value, $isFromDb = false)
 */
class TestingAdmin extends Record
{
    public function __construct()
    {
        parent::__construct(TestingAdminsTable::getInstance());
    }

    public function getColumnsWithUpdatableValues(): array
    {
        return parent::getColumnsWithUpdatableValues();
    }
    
    public function collectValuesForSave(array &$columnsToSave, bool $isUpdate): array
    {
        return parent::collectValuesForSave($columnsToSave, $isUpdate);
    }

    public function getColumnValueForToArray(
        string $columnName,
        ?string &$columnAlias = null,
        ?\Closure $valueModifier = null,
        bool $returnNullForFiles = false,
        ?bool &$isset = null,
        bool $skipPrivateValueCheck = false
    ): mixed {
        return parent::getColumnValueForToArray($columnName, $columnAlias, $valueModifier, $returnNullForFiles, $isset, $skipPrivateValueCheck);
    }
    
    
}