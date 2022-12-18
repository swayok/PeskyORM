<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TableColumn;

use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;

class TestVirtualColumnWithAfterSave extends VirtualTableColumnAbstract
{
    public function getValue(RecordValueContainerInterface $valueContainer, ?string $format): mixed
    {
        return 'virtual';
    }

    public function hasValue(RecordValueContainerInterface $valueContainer, bool $allowDefaultValue): bool
    {
        return true;
    }

    public function setValue(
        RecordValueContainerInterface $currentValueContainer,
        mixed $newValue,
        bool $isFromDb,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        $currentValueContainer->addPayload(
            static::AFTER_SAVE_PAYLOAD_KEY,
            $newValue
        );
        return $currentValueContainer;
    }

    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate,
    ): void {
        $payload = $valueContainer->pullPayload(static::AFTER_SAVE_PAYLOAD_KEY);
        if ($payload) {
            throw new \UnexpectedValueException(json_encode($payload));
        }
    }
}