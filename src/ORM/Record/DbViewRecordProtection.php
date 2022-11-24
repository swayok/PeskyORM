<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

/**
 * This trait should be used for records that represent database view rows.
 * Usually in this case records cannot be added, updated or deleted.
 * @psalm-require-implements \PeskyORM\ORM\Record\RecordInterface
 */
trait DbViewRecordProtection
{
    
    public function saveToDb(array $columnsToSave = []): void
    {
        throw new \BadMethodCallException('Saving data to a DB View is impossible');
    }
    
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true): static
    {
        throw new \BadMethodCallException('Deleting data from a DB View is impossible');
    }
    
}