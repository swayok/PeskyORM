<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\TableInterface;

class TestingAdmin2 extends Record
{
    
    /**
     * @return TableInterface
     */
    public static function getTable(): TableInterface
    {
        return TestingAdminsTable::getInstance();
    }
    
    protected function beforeSave(array $columnsToSave, array $data, bool $isUpdate): array
    {
        if ($isUpdate) {
            return ['login' => ['error']];
        }
        return [];
    }
    
    protected function afterSave(bool $isCreated, array $updatedColumns = []): void
    {
        throw new \BadMethodCallException('after: no-no-no!');
    }
    
    protected function beforeDelete(): void
    {
        if ($this->getPrimaryKeyValue() !== 0) {
            throw new \BadMethodCallException('before delete: no-no-no!');
        }
    }
    
    protected function afterDelete(): void
    {
        throw new \BadMethodCallException('after delete: no-no-no!');
    }
    
    
}
