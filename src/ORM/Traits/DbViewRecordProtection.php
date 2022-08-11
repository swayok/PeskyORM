<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Traits;

/**
 * This trait should be used for records that represent database view rows.
 * Usually in this case records cannot be added, updated or deleted.
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait DbViewRecordProtection
{
    
    public function saveToDb(array $columnsToSave = [])
    {
        throw new \BadMethodCallException('Saving data to a DB View is impossible');
    }
    
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true)
    {
        throw new \BadMethodCallException('Deleting data from a DB View is impossible');
    }
    
}