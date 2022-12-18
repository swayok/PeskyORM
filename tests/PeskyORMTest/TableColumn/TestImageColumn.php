<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TableColumn;

use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;

class TestImageColumn extends VirtualTableColumnAbstract
{
    public function getValue(RecordValueContainerInterface $valueContainer, ?string $format): mixed
    {
        return 'image';
    }

    public function hasValue(RecordValueContainerInterface $valueContainer, bool $allowDefaultValue): bool
    {
        return $valueContainer->getRecord()->existsInDb();
    }

    public function isFile(): bool
    {
        return true;
    }
}